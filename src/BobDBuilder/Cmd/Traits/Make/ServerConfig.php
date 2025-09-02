<?php

namespace BrickLayer\Lay\BobDBuilder\Cmd\Traits\Make;

use BrickLayer\Lay\BobDBuilder\BobExec;
use BrickLayer\Lay\Core\Enums\LayServerType;
use BrickLayer\Lay\Core\View\Domain as AppDomain;
use BrickLayer\Lay\Core\View\Enums\DomainType;
use BrickLayer\Lay\Libs\LayArray;
use BrickLayer\Lay\Libs\LayFn;
use WeakMap;

trait ServerConfig
{
    public function server_config(): void
    {
        if (!isset($this->tags['make_server_config'])) {
            return;
        }

        $type = LayServerType::to_enum($this->tags['make_server_config'][0] ?? LayFn::env('SERVER') ?? '', case_sensitive: false);

        if (empty($type)) {
            $this->plug->write_fail(
                "Cannot generate server config files! No valid server type was specified\n"
                . "Example usage is: php bob make:server_config apache\n"
                . "Available server types: " . LayArray::map(LayServerType::cases(), fn($v) => strtolower($v->name), ",")
            );
        }

        if(!file_exists($this->plug->server->root . "bob.config.json"))
            new BobExec("make:config --silent");

        $this->dump_server_config_file($type);
    }

    private function dump_server_config_file(LayServerType $type): void
    {
        $server_rules = json_decode(file_get_contents($this->plug->server->root . "bob.config.json"), true);

        if (!$server_rules)
            $this->plug->write_fail("bob.config.json is invalid");

        $server_rules = $server_rules['server_rules'] ?? null;

        if (!$server_rules) {
            $this->plug->write_fail("bob.config.json 'server_rules' is missing or invalid");
        }

        $conf_types = new WeakMap();

        $conf_types[LayServerType::APACHE] = [
            "file" => ".htaccess",
            "gen" => fn(array $rules) => $this->gen_apache($rules),
        ];

        $conf_types[LayServerType::CADDY] = [
            "file" => "Caddyfile",
            "gen" => fn(array $rules) => $this->gen_caddy($rules),
        ];

        $conf_types[LayServerType::NGINX] = [
            "file" => "nginx.conf",
            "gen" => fn(array $rules) => $this->gen_nginx($rules),
        ];

        $file = $conf_types[$type]['file'];
        $generator = $conf_types[$type]['gen'];

        $this->plug->write_info("Server: " . $type->name . "\n");

        $put_conf = function ($dest, $rules) use ($conf_types, $file, $generator) {
            if (file_exists($dest . $file) && !$this->plug->force) {
                $this->plug->write_warn(
                    "File exists in destination: *$dest$file*\nUse the --force tag to overwrite it\n",
                    ['kill' => false]
                );

                return;
            }

            foreach ($conf_types as $conf) {
//                if ($file == $conf['file']) continue;

                @unlink($dest . $conf['file']);
            }

            file_put_contents($dest . $file, $generator($rules));
        };

        // Project root server config file
        $dest = $this->plug->server->root;
        $put_conf($dest, $server_rules['root']);

        // Web folder server config file
        $dest = $this->plug->server->web;
        $put_conf($dest, $server_rules['regular']);

        // Domain server config file
        $domain_root = $this->plug->server->domains;

        foreach (AppDomain::new()->list() as $domain) {
            $name = AppDomain::from_builder($domain['builder']);
            $dest = $domain_root . $name . DIRECTORY_SEPARATOR;
            $rules = $server_rules['special'];

            if ($domain['create_type'] == DomainType::REGULAR->name) {
                $dest = $domain_root . $name . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR;
                $rules = $server_rules['regular'];
            }

            $put_conf($dest, $rules);
        }
    }

    /**
     * Generate Apache (.htaccess) from declarative server_rules array
     * $rules: array of directive objects (each with 'type' and other fields)
     */
    private function gen_apache(array $rules): string
    {
        $lines = [];

        foreach ($rules as $d) {
            $type = $d['type'] ?? '';

            if(isset($d['desc']))
                $lines[] = "# " . $d['desc'];

            switch ($type) {
                case 'server_signature':
                    $val = $d['value'] ?? 'Off';
                    $lines[] = "ServerSignature $val";
                    break;

                case 'options':
                    $flags = $d['flags'] ?? [];

                    if (!empty($flags))
                        $lines[] = "Options " . implode(" ", $flags);
                    break;

                case 'if_module':
                    $module = $d['module'] ?? '';
                    $lines[] = "<IfModule {$module}>";

                    foreach ($d['directives'] ?? [] as $child) {
                        $lines = array_merge($lines, $this->render_apache_child($child, 1));
                    }

                    $lines[] = "</IfModule>";
                    break;

                case 'rewrite_engine':
                    $state = $d['state'] ?? 'On';
                    $lines[] = "RewriteEngine {$state}";
                    break;

                case 'files':
                    $lines[] = "<Files \"{$d['pattern']}\">";
                    foreach ($d['directives'] ?? [] as $child) {
                        $lines = array_merge($lines, $this->render_apache_child($child, 1));
                    }
                    $lines[] = "</Files>";
                    break;

                case 'files_match':
                    $lines[] = "<FilesMatch \"{$d['pattern']}\">";
                    foreach ($d['directives'] ?? [] as $child) {
                        $lines = array_merge($lines, $this->render_apache_child($child, 1));
                    }
                    $lines[] = "</FilesMatch>";
                    break;

                case 'rewrite_rule':
                    $pat = $d['pattern'] ?? "''";
                    $target = $d['target'] ?? '';
                    $flags = $this->apache_flags($d['flags'] ?? []);
                    $lines[] = "RewriteRule $pat $target$flags";
                    break;

                case 'rewrite_cond_chain':
                    foreach ($d['conds'] ?? [] as $cond) {
                        $x = "RewriteCond {$cond['variable']}";
                        $x .= " " . ($cond['check'] ?? $cond['pattern']);

                        if(isset($cond['logic']))
                            $x .= " [{$cond['logic']}]";

                        $lines[] = $x;
                    }

                    if (!empty($d['rule'])) {
                        $r = $d['rule'];
                        $pat = $r['pattern'] ?? "''";
                        $flags = $this->apache_flags($r['flags'] ?? []);
                        $lines[] = "RewriteRule {$pat} {$r['target']}{$flags}";
                    }
                    break;

                default:
                    // Preserve unknown directives as comments to avoid silent loss
                    $lines[] = "# skipped unknown directive: " . var_export($d, true);
                    break;
            }

            $lines[] = "";
        }

        // ensure final newline
        return implode("\n", $lines) . "\n";
    }

    private function render_apache_child(array $child, int $indent = 0): array
    {
        $pad = str_repeat("  ", $indent);
        $lines = [];
        $type = $child['type'] ?? '';

        if(isset($child['desc']))
            $lines[] = "$pad# " . $child['desc'];

        switch ($type) {
            case 'add_output_filter_by_type':
                $filter = $child['filter'] ?? 'DEFLATE';
                foreach ($child['mime_types'] ?? [] as $mt) {
                    $lines[] = "{$pad}AddOutputFilterByType {$filter} {$mt}";
                }
                break;

            case 'files_match':
                $pattern = $child['pattern'] ?? '';
                $lines[] = "{$pad}<FilesMatch \"{$pattern}\">";
                foreach (($child['headers'] ?? []) as $k => $v) {
                    $lines[] = "{$pad}  Header set {$k} \"{$v}\"";
                }
                foreach (($child['unset'] ?? []) as $h) {
                    $lines[] = "{$pad}  Header unset {$h}";
                }
                foreach (($child['directives'] ?? []) as $grand) {
                    $lines = array_merge($lines, $this->render_apache_child($grand, $indent + 1));
                }
                $lines[] = "{$pad}</FilesMatch>";
                break;

            case 'header_unset':
                foreach (($child['headers'] ?? []) as $h) {
                    $lines[] = "{$pad}Header unset {$h}";
                    $lines[] = "{$pad}Header always unset {$h}";
                }
                break;

            case 'expires_by_type':
                $lines[] = "{$pad}ExpiresActive On";
                foreach (($child['rules'] ?? []) as $mime => $phrase) {
                    $val = $this->format_expires_phrase_for_apache($phrase);
                    $lines[] = "{$pad}ExpiresByType {$mime} \"{$val}\"";
                }
                break;

            case 'rewrite_rule':
                $pat = $child['pattern'] ?: "''";
                $target = $child['target'] ?? '';
                $flags = $this->apache_flags($child['flags'] ?? []);
                $lines[] = "{$pad}RewriteRule {$pat} {$target}{$flags}";
                break;

            case 'deny':
                $lines[] = "{$pad}Order allow,deny";
                $lines[] = "{$pad}Deny from all";
                break;

            default:
                $lines[] = "{$pad}# skipped child directive: " . var_export($child, true);
                break;
        }

        $lines[] = "";

        return $lines;
    }

    private function format_expires_phrase_for_apache(string $phrase): string
    {
        if (stripos($phrase, 'access') !== false) return $phrase;

        if (preg_match('/^(\d+)([ymdh])$/i', $phrase, $m)) {
            $n = (int)$m[1];
            $u = strtolower($m[2]);
            return match ($u) {
                'y' => $n === 1 ? 'access plus 1 year' : "access plus {$n} years",
                'm' => $n === 1 ? 'access plus 1 month' : "access plus {$n} months",
                'd' => $n === 1 ? 'access plus 1 day' : "access plus {$n} days",
                'h' => $n === 1 ? 'access plus 1 hour' : "access plus {$n} hours",
                default => 'access plus 1 year',
            };
        }
        return $phrase;
    }

    private function apache_flags(array $flags): string
    {
        if (empty($flags)) return '';
        return ' [' . implode(',', $flags) . ']';
    }

    /**
     * Generate Caddyfile fragment (best-effort)
     */
    private function gen_caddy(array $rules): string
    {
        $lines = [];

        foreach ($rules as $idx => $d) {
            $type = $d['type'] ?? '';

            if(isset($d['desc']))
                $lines[] = "# " . $d['desc'];

            switch ($type) {
                // Caddy hides server version by default; nothing to emit
                case 'server_signature': break;

                case 'options':
                    if (in_array('-Indexes', $d['flags'] ?? [], true)) {
                        $lines[] = "file_server browse off";
                    }
                    break;

                case 'if_module':
                    $module = $d['module'] ?? '';

                    if ($module === 'mod_deflate.c') {
                        $lines[] = "encode gzip";
                    }

                    if ($module === 'mod_headers.c') {
                        foreach ($d['directives'] ?? [] as $child) {
                            if (($child['type'] ?? '') === 'files_match') {
                                $pat = $child['pattern'] ?? '';
                                $name = "@hdr_{$idx}";
                                $lines[] = "{$name} {";
                                $lines[] = "  path_regexp re{$idx} {$pat}";
                                $lines[] = "}";

                                foreach (($child['headers'] ?? []) as $k => $v) {
                                    $lines[] = "header {$name} {$k} \"{$v}\"";
                                }

                                foreach (($child['unset'] ?? []) as $h) {
                                    $lines[] = "# Caddy cannot unset header {$h} without advanced config";
                                }

                            } elseif (($child['type'] ?? '') === 'header_unset') {
                                foreach (($child['headers'] ?? []) as $h) {
                                    $lines[] = "# header unset requested: {$h}";
                                }
                            }
                        }
                    }

                    if ($module === 'mod_expires.c') {
                        foreach ($d['directives'] ?? [] as $child) {
                            if (($child['type'] ?? '') === 'expires_by_type') {
                                foreach (($child['rules'] ?? []) as $mime => $phrase) {
                                    $exts = $this->mime_to_extensions($mime);
                                    $secs = $this->ttl_seconds_from_phrase($phrase);

                                    if (!empty($exts)) {
                                        $name = "@cache_{$idx}_" . preg_replace('/[^a-z0-9]+/i', '_', $mime);
                                        $lines[] = "{$name} {";
                                        foreach ($exts as $e) $lines[] = "  path *.$e";
                                        $lines[] = "}";
                                        $lines[] = "header {$name} Cache-Control \"public, max-age={$secs}\"";
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'files':
                    $pattern = str_replace(".htaccess", "Caddyfile", $d['pattern'] ?? '');
                    $name = "@deny_{$idx}";

                    $lines[] = "{$name} {";
                    $lines[] = "  path /" . ltrim($pattern, '/');
                    $lines[] = "}";
                    $lines[] = "respond {$name} 403";

                    foreach ($d['directives'] ?? [] as $child) {
                        if (($child['type'] ?? '') === 'rewrite_rule') {
                            $lines[] = "# rewrite {$child['pattern']} -> {$child['target']} (Caddy: approximate)";
                            $target = $this->caddy_safe_target($child['target']);
                            $lines[] = "rewrite * " . ltrim($target, '/');
                        }
                    }
                    break;

                case 'files_match':
                    $pattern = $d['pattern'] ?? '';
                    $name = "@deny_m_{$idx}";
                    $lines[] = "{$name} {";
                    $lines[] = "  path_regexp re{$idx} {$pattern}";
                    $lines[] = "}";
                    $lines[] = "respond {$name} 403";
                    break;

                case 'rewrite_rule':
                    $target = $d['target'] ?? '';

                    // common catch-all pattern
                    $target = $this->caddy_safe_target($target);
                    $lines[] = "rewrite * /" . ltrim($target, '/');
                    break;

                case 'rewrite_cond_chain':
                    $lines[] = "# NOTE: rewrite_cond_chain cannot be directly expressed in Caddyfile";
                    $lines[] = "# Conditions: " . json_encode($d['conds']);
                    if (!empty($d['rule'])) {
                        $lines[] = "# Intended rewrite: {$d['rule']['pattern']} -> {$d['rule']['target']}";
                    }
                    break;

                default:
                    $lines[] = "# skipped Caddy mapping for: " . ($d['type'] ?? 'unknown');
                    break;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function mime_to_extensions(string $mime): array
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => ['jpg', 'jpeg'],
            'image/gif' => ['gif'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
            'image/x-icon', 'image/vnd.microsoft.icon' => ['ico'],
            'video/webm' => ['webm'],
            'video/mp4' => ['mp4'],
            'video/mpeg' => ['mpeg', 'mpg'],
            'font/ttf' => ['ttf'],
            'font/otf' => ['otf'],
            'font/woff' => ['woff'],
            'font/woff2', 'application/font-woff2' => ['woff2'],
            'text/css' => ['css'],
            'text/javascript', 'application/javascript' => ['js'],
            'application/pdf' => ['pdf'],
            default => [],
        };
    }

    private function ttl_seconds_from_phrase(string $phrase): int
    {
        if (preg_match('/(\d+)\s*year/i', $phrase, $m)) return (int)$m[1] * 31536000;
        if (preg_match('/^(\d+)([ymdh])$/i', $phrase, $m)) {
            $n = (int)$m[1];
            return match (strtolower($m[2])) {
                'y' => $n * 31536000,
                'm' => $n * 2592000,
                'd' => $n * 86400,
                'h' => $n * 3600,
                default => 31536000,
            };
        }
        return 31536000;
    }

    // Map Apache-style placeholders to Caddy-ish placeholders where possible.
    private function caddy_safe_target(string $target): string
    {
        $t = str_replace('%{REQUEST_URI}', '{uri}', $target);
        return str_replace('$1', '{uri}', $t);
    }

    /**
     * Generate Nginx snippet (best-effort)
     */
    private function gen_nginx(array $rules): string
    {
        $lines = [];
        $lines[] = "server {";
        $lines[] = "    listen 80;"; // TODO: When using NGINX, come back and check if this is necessary
        $lines[] = "    server_tokens off;";

        foreach ($rules as $d) {
            $type = $d['type'] ?? '';

            if(isset($d['desc']))
                $lines[] = "# " . $d['desc'];

            switch ($type) {
                // handled by server_tokens above
                case 'server_signature': break;

                case 'options':
                    if (in_array('-Indexes', $d['flags'] ?? [], true)) {
                        $lines[] = "    autoindex off;";
                    }
                    break;

                case 'if_module':
                    $module = $d['module'] ?? '';
                    if ($module === 'mod_deflate.c') {
                        $mime_types = [];
                        foreach ($d['directives'] ?? [] as $child) {
                            if (($child['type'] ?? '') === 'add_output_filter_by_type') {
                                $mime_types = array_merge($mime_types, $child['mime_types'] ?? []);
                            }
                        }
                        if (!empty($mime_types)) {
                            $lines[] = "    gzip on;";
                            $lines[] = "    gzip_types " . implode(" ", array_unique($mime_types)) . ";";
                        }
                    } elseif ($module === 'mod_headers.c') {
                        foreach ($d['directives'] ?? [] as $child) {
                            if (($child['type'] ?? '') === 'files_match') {
                                $pat = $child['pattern'] ?? '';
                                $lines[] = "    location ~ {$pat} {";
                                foreach (($child['headers'] ?? []) as $k => $v) {
                                    $lines[] = "        add_header {$k} \"{$v}\" always;";
                                }
                                foreach (($child['unset'] ?? []) as $h) {
                                    $lines[] = "        # To unset {$h} require headers_more module: more_clear_headers {$h};";
                                }
                                $lines[] = "    }";
                            } elseif (($child['type'] ?? '') === 'header_unset') {
                                foreach (($child['headers'] ?? []) as $h) {
                                    $lines[] = "    # header unset requested for {$h} (requires headers_more)";
                                }
                            }
                        }
                    } elseif ($module === 'mod_expires.c') {
                        foreach ($d['directives'] ?? [] as $child) {
                            if (($child['type'] ?? '') === 'expires_by_type') {
                                foreach (($child['rules'] ?? []) as $mime => $phrase) {
                                    $exts = $this->mime_to_extensions($mime);
                                    $secs = $this->ttl_seconds_from_phrase($phrase);
                                    if (!empty($exts)) {
                                        $lines[] = "    location ~* \\.(?:" . implode("|", $exts) . ")$ {";
                                        $lines[] = "        expires " . $this->ttl_nginx($phrase) . ";";
                                        $lines[] = "        add_header Cache-Control \"public, max-age={$secs}\" always;";
                                        $lines[] = "    }";
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'files':
                    $pattern = str_replace(".htaccess", "nginx.conf", $d['pattern'] ?? '');

                    $lines[] = "    location = /" . ltrim($pattern, '/') . " {";

                    foreach ($d['directives'] ?? [] as $child) {
                        if (($child['type'] ?? '') === 'rewrite_rule') {
                            $pat = $child['pattern'] ?: "''";
                            $lines[] = "        rewrite {$pat} {$child['target']} last;";
                        } elseif (($child['type'] ?? '') === 'deny') {
                            $lines[] = "        deny all;";
                        }
                    }
                    $lines[] = "    }";
                    break;

                case 'files_match':
                    $pattern = $d['pattern'] ?? '';
                    $lines[] = "    location ~ {$pattern} {";
                    foreach ($d['directives'] ?? [] as $child) {
                        if (($child['type'] ?? '') === 'rewrite_rule') {
                            $pat = $child['pattern'] ?: "''";
                            $lines[] = "        rewrite {$pat} {$child['target']} last;";
                        } elseif (($child['type'] ?? '') === 'deny') {
                            $lines[] = "        deny all;";
                        }
                    }
                    $lines[] = "    }";
                    break;

                case 'rewrite_rule':
                    $pat = $d['pattern'] ?? "''";
                    $target = $d['target'] ?? '';
                    $flags = implode(",", $d['flags'] ?? []);
                    if (str_contains($flags, 'R=403')) {
                        $lines[] = "    location ~ {$pat} { return 403; }";
                    } elseif (str_contains($flags, 'R=301')) {
                        $lines[] = "    rewrite {$pat} {$target} permanent;";
                    } else {
                        // place in location / to avoid top-level rewrite ordering issues
                        $lines[] = "    location / {";
                        $lines[] = "        rewrite {$pat} {$target} last;";
                        $lines[] = "    }";
                    }
                    break;

                case 'rewrite_cond_chain':
                    // Best-effort: convert to nested if blocks (approximate)
                    $conds = $d['conds'] ?? [];
                    foreach ($conds as $cond) {
                        if (isset($cond['check'])) {
                            // e.g. -f, -d, -l -> we cannot replicate exactly; comment
                            $lines[] = "    # condition check {$cond['variable']} {$cond['check']} (not directly translatable)";
                        } else {
                            $var = $this->nginx_var_from_apache_var($cond['variable']);
                            $pat = $cond['pattern'] ?? '';
                            if (str_starts_with($pat, '!')) {
                                $val = substr($pat, 1);
                                $lines[] = "    if ({$var} != {$val}) {";
                            } else {
                                $lines[] = "    if ({$var} ~ {$pat}) {";
                            }
                        }
                    }
                    // inner rule
                    if (!empty($d['rule'])) {
                        $lines[] = $this->nginx_rewrite_from_rule($d['rule'], "        ");
                    }
                    // close ifs (approximate)
                    for ($i = 0; $i < count($conds); $i++) {
                        $lines[] = "    }";
                    }
                    break;

                default:
                    $lines[] = "    # skipped directive for nginx: " . ($d['type'] ?? 'unknown');
                    break;
            }
        }

        // php-fpm stub
        $lines[] = "";
        $lines[] = "    location ~ \\.php$ {";
        $lines[] = "        include fastcgi_params;";
        $lines[] = "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;";
        $lines[] = "        fastcgi_pass unix:/run/php/php-fpm.sock;";
        $lines[] = "    }";

        $lines[] = "}";
        return implode("\n", $lines) . "\n";
    }

    private function ttl_nginx(string $phrase): string
    {
        if (preg_match('/^(\d+)([ymdh])$/i', $phrase)) return $phrase;
        if (preg_match('/(\d+)\s*year/i', $phrase, $m)) return ((int)$m[1]) . 'y';
        return '1y';
    }

    private function nginx_var_from_apache_var(string $apache_var): string
    {
        $v = $apache_var;
        if (str_starts_with($v, '%{') && str_ends_with($v, '}')) $v = substr($v, 2, -1);
        $map = [
            'REMOTE_HOST' => '$remote_addr',
            'REMOTE_ADDR' => '$remote_addr',
            'SERVER_PORT' => '$server_port',
            'REQUEST_FILENAME' => '$request_filename',
            'REQUEST_URI' => '$request_uri',
            'HTTP:X-Forwarded-Proto' => '$http_x_forwarded_proto',
            'HTTP_HOST' => '$host',
        ];
        return $map[$v] ?? ('$' . strtolower(preg_replace('/[^A-Z0-9]/i', '_', $v)));
    }

    private function nginx_rewrite_from_rule(array $rule, string $indent = "    "): string
    {
        $flags = implode(",", $rule['flags'] ?? []);
        if (str_contains($flags, 'R=301')) return $indent . "return 301 {$rule['target']};";
        if (str_contains($flags, 'R=403')) return $indent . "return 403;";
        return $indent . "rewrite {$rule['pattern']} {$rule['target']} last;";
    }
}
