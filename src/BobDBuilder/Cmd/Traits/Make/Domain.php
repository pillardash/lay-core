<?php

namespace BrickLayer\Lay\BobDBuilder\Cmd\Traits\Make;

use BrickLayer\Lay\BobDBuilder\BobExec;
use BrickLayer\Lay\Libs\Dir\LayDir;


trait Domain
{
    public function domain(): void
    {
        if (!isset($this->tags['make_domain']))
            return;

        $domain = $this->tags['make_domain'][0] ?? null;
        $pattern = $this->tags['make_domain'][1] ?? null;

        if (!$domain)
            $this->plug->write_fail("No domain specified");

        $pattern ??= strtolower($domain);

        if (!$this->plug->is_internal && (trim($pattern) == "*" || trim($pattern) == "api")) {
            $this->plug->failed();
            $this->plug->write_warn(
                "Pattern cannot be an empty quote or '*' or 'api'\n"
                . "\n"
                . "See pattern examples below:\n"
                . "Example: 'blog'\n"
                . "Example: 'case-study'\n"
                . "Example: docs\n"
            );
        }

        $domain = explode(" ", ucwords($domain));
        $domain_id = strtolower(implode("-", $domain) . "-id");
        $domain = implode("", $domain);
        $domain_dir = $this->plug->server->domains . $domain;
        $exists = is_dir($domain_dir);

        if (!$this->plug->is_internal && in_array($domain, ["Default", "Api"])) {
            $this->plug->failed();
            $this->plug->write_warn(
                "Unfortunately you cannot create a domain with this name!\n"
                . "To reset your *Default* and *Api* domains, you can run the command *php bob project:create --force-refresh*"
            );
        }

        if (!$this->plug->force && $exists)
            $this->plug->write_fail(
                "Domain directory *$domain_dir* exists already!\n"
                . "If you wish to force this action, pass the tag --force with the command\n"
                . "Note, using --force will delete the existing directory and this process cannot be reversed!"
            );

        if ($exists) {
            $this->talk(
                "- Directory *$domain_dir* exists but --force tag detected\n"
                . "- Deleting existing *$domain_dir*"
            );

            LayDir::unlink($domain_dir);
        }

        $internal_domain = "Domain";

        if ($domain == "Default") {
            $domain_id = "default";
            $pattern = "*";
        }

        if ($domain == "Api") {
            $domain_id = "api-endpoint";
            $pattern = "api";
            $internal_domain = "ApiDomain";
        }

        $this->talk("- Creating new Domain directory in *$domain_dir*");
        LayDir::copy($this->internal_dir . $internal_domain, $domain_dir);

        $this->talk("- Copying default files");
        $this->domain_default_files($domain, $domain_id, $domain_dir);

        if ($domain != 'Api') {
            $this->talk("- Linking shared directory *{$this->plug->server->shared}*");
            new BobExec("link:dir web{$this->plug->s}shared web{$this->plug->s}domains{$this->plug->s}$domain{$this->plug->s}public{$this->plug->s}shared --silent");
        }

        $this->talk("- Updating domains entry in *{$this->plug->server->web}index.php*");
        $this->update_general_domain_entry($domain, $domain_id, $pattern);

        new BobExec("make:server_config --silent");
    }

    public function is_api_domain(string $domain_id): bool
    {
        return $domain_id == 'api-endpoint';
    }

    public function domain_default_files(string $domain_name, string $domain_id, string $domain_dir): void
    {
        // root index.php
        file_put_contents(
            $domain_dir . $this->plug->s . (!$this->is_api_domain($domain_id) ? "public" . $this->plug->s : '') . "index.php",
            <<<FILE
            <?php
            const SAFE_TO_INIT_LAY = true;
            include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "foundation.php";
            
            \BrickLayer\Lay\Core\View\Domain::new()->index("$domain_id");
            
            include_once \BrickLayer\Lay\Core\Server::new()->web . "index.php";
            
            FILE
        );

        // Plaster.php for handling routes
        file_put_contents(
            $domain_dir . $this->plug->s .
            "Plaster.php",
            <<<FILE
            <?php
            namespace Web\\$domain_name;
            
            use BrickLayer\Lay\Core\View\DomainResource;
            use BrickLayer\Lay\Core\View\ViewBuilder;
            use BrickLayer\Lay\Core\View\ViewCast;
            
            class Plaster extends ViewCast
            {
                protected function init_pages(): void
                {
                    \$this->builder->init_start()
                        ->body_attr("dark", 'id="body-id"')
                        ->local("logo", DomainResource::get()->shared->img_default->logo)
                        ->local("section", "app")
                    ->init_end();
                }
            
                protected function pages(): void
                {
                    \$this->route("index")->bind(function (ViewBuilder \$builder) {
                        \$builder->page("title", "Homepage")
                            ->page("desc", "This is the default homepage description")
                            ->assets(
                                "@css/another.css",
                            )
                            ->body("homepage");
                    });
            
                    \$this->route("another-page")->bind(function (ViewBuilder \$builder) {
                        \$builder->page("title", "Another Page")
                            ->page("desc", "This is another page's description")
                            ->assets(
                                "@css/another.css",
                            )
                            ->body("another");
                    });
                }
            }
            
            FILE
        );

        if (!$this->is_api_domain($domain_id)) {

            // favicon.ico
            if (file_exists($this->plug->server->web . "favicon.ico")) {
                copy(
                    $this->plug->server->web . "favicon.ico",
                    $domain_dir . $this->plug->s . "public" . $this->plug->s . "favicon.ico"
                );

                return;
            }

            copy(
                $this->plug->server->lay_static . "img" . $this->plug->s . "favicon.ico",
                $domain_dir . $this->plug->s . "public" . $this->plug->s . "favicon.ico"
            );
        }
    }

    public function update_general_domain_entry(string $domain, string $domain_id, string $pattern): void
    {
        $main_file = $this->plug->server->web . "index.php";
        $index_page = file_get_contents($main_file);

        // Default Domain Entry
        preg_match(
            '/Domain::new\(\)->create\([^)]*default[^)]*\);/s',
            $index_page, $data
        );

        $default_domain = $data[0] ?? <<<DEF
        Domain::new()->create(
            id: "default",
            builder: \Web\Default\Plaster::class,
            pattern: "*",
            type: DomainType::REGULAR,
        );
        DEF;

        $current_domain = "";

        if ($domain_id != 'default') {
            // Current Domain being created
            preg_match(
                '/Domain::new\(\)->create\([^)]*' . $domain_id . '[^)]*\);/s',
                $index_page, $data
            );

            // Create the new domain patterns as specified from the terminal

            $old_pattern = null;

            if (!empty($data)) {
                preg_match('/"([^"]+)"/', $data[0], $old_pattern);
                $old_pattern = $old_pattern[0] ?? null;
            }

            $pattern = $old_pattern == $pattern ? $old_pattern : $pattern;

            $current_domain = <<<CUR
            
            Domain::new()->create(
                id: "$domain_id",
                builder: \Web\\$domain\\Plaster::class,
                pattern: "$pattern",
                type: DomainType::REGULAR,
            );
            
            CUR;
        }

        // Remove any duplicate from the domain entry
        $index_page = trim(preg_replace(
            ['/Domain::new\(\)->create\([^)]*default[^)]*\);/s',
                '/Domain::new\(\)->create\([^)]*' . $domain_id . '[^)]*\);/s'],
            "",
            $index_page
        ));

        // Replace the index file
        file_put_contents(
            $main_file,
            <<<INDEX
            $index_page
            $current_domain
            $default_domain
            INDEX
        );
    }
}