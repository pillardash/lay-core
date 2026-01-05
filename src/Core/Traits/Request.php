<?php
declare(strict_types=1);

namespace BrickLayer\Lay\Core\Traits;

use BrickLayer\Lay\Core\App;
use BrickLayer\Lay\Core\Startup;
use BrickLayer\Lay\Libs\LayDate;

trait Request {

    /**
     * Get a header received by this application from an HTTP request using a key.
     * You can pass "*" to retrieve every header received.
     * @param string $key
     * @return mixed
     */
    public static function get_header(string $key): mixed
    {
        $all = [];
        $headers = $_SERVER;
        $is_server = true;

        if(function_exists("getallheaders")) {
            $headers = getallheaders();
            $is_server = false;
        }

        foreach ($headers as $k => $header) {
            if($is_server && !str_starts_with($k, "HTTP_"))
                continue;

            $k = str_replace(["http_", "_"], ["", " "], strtolower($k));

            $all[$k] = $header;
        }

        if ($key === "*") return $all;

        $authorization_header = function ($all): array|null {
            $auth = $all['authorization'] ?? null;

            if(!$auth)
                return null;

            $auth = explode(" ", $auth, 2);

            return [
                "scheme" => $auth[0],
                "data" => $auth[1] ?? null,
            ];
        };

        $key = strtolower($key);

        if($key == "authorization")
            return $authorization_header($all);

        if(in_array($key, ["bearer","basic", "digest"], true)) {
            $auth = $authorization_header($all);

            if(!$auth)
                return null;

            return $auth['data'];
        }


        return $all[$key] ?? null;
    }

    /**
     * @return array{
     *     agent: string,
     *     product?: string,
     *     platform?: string,
     *     engine?: string,
     *     browser?: string
     * }|null
     */
    public static function user_agent() : array|null
    {
        $agent = self::get_header('user-agent');

        if(!$agent)
            return null;

        // regex pattern for chromium
        $pattern = '/^(?<product>.*?)\s\((?<platform>.*?)\)\s(?<engine>.*?)\s\((?<engine2>.*?)\)\s(?<browser>.*?)$/';

        preg_match($pattern, $agent, $matches);

        if(empty($matches)) {
            // regex pattern for firefox
            $pattern = '/^(?<product>.*?)\s\((?<platform>.*?)\)\s(?<engine>.*?)\s(?<browser>.*?)$/';
            preg_match($pattern, $agent, $matches);

            if(empty($matches))
                return [
                    'agent' => $agent
                ];
        }

        $extract_word = function (string $pattern, string $subject) {
            $pattern = '~' . $pattern . '~';

            preg_match($pattern, $subject, $out);

            if(empty($out))
                return null;

            return $out;
        };

        $edge = $extract_word("Edg.*", $matches['browser']);
        $safari = $extract_word("(Version\/[0-9.]+) (Safari\/[0-9.]+)", $matches['browser']);
        $chrome = $extract_word("Chrome\/[0-9.]+", $matches['browser']);

        if($edge)
            $browser = $edge[0];
        elseif($safari)
            $browser = $safari[2];
        elseif($chrome)
            $browser = $chrome[0];
        else
            $browser = null;

        return [
            "agent" => $matches[0],
            "product" => $matches['product'],
            "platform" => $matches['platform'],
            "engine" => $matches['engine'],
            "browser" => $browser ?? $matches['browser'],
        ];
    }

    public static function has_internet(): bool|array
    {
        return @fsockopen("google.com", 443, timeout: 1) !== false;
    }

    public static function geo_data(): bool|object
    {
        $data = false;

        try {
            $data = @json_decode(file_get_contents('https://ipinfo.io/' . self::get_ip()));
        } catch (\TypeError) {}

        if (!$data) return false;

        return $data;
    }

    public static function get_ip(): string
    {
        if (App::is_cli()) {

            if(isset($_SERVER['SSH_CONNECTION']))
                return explode(" ", $_SERVER['SSH_CONNECTION'], 2)[0];

            return "LAY_CLI_MODE";
        }

        $IP_KEY = "PUBLIC_IP";
        $public_ip = $_SESSION[Startup::SESSION_KEY][$IP_KEY] ?? null;

        if (@$public_ip['ip'] && !LayDate::expired($public_ip['exp']))
            return $public_ip['ip'];

        if (App::is_dev() && self::has_internet()) {
            $fetch_data = function($url): bool|string|null {
                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_TIMEOUT => 2,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 Lay-Framework',
                ]);

                $response = curl_exec($ch);
                $err = curl_error($ch);

                // curl_close($ch);

                if ($err)
                    return null;

                return $response;
            };

            $_SESSION[Startup::SESSION_KEY][$IP_KEY] = [
                "ip" => $fetch_data("https://api.ipify.io") ?: "127.0.0.1",
                "exp" => strtotime('3 hours')
            ];

            return $_SESSION[Startup::SESSION_KEY][$IP_KEY]['ip'];
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'] as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip_address) {
                    $ip_address = trim($ip_address);

                    if (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) return $ip_address;
                }
            }

        }

        $ip_address ??= "127.0.0.1-cli";

        $_SESSION[Startup::SESSION_KEY][$IP_KEY] = [
            "ip" => $ip_address,
            "exp" => strtotime('3 hour')
        ];

        return $_SESSION[Startup::SESSION_KEY][$IP_KEY]['ip'];
    }

    public static function is_bot(): bool
    {
        $from = self::get_header("From");
        $user_agent = self::get_header("user-agent");

        if(!$user_agent) return true;

        $exists = preg_match('~(bot|crawl|ai)~i', $from ?? '', flags: PREG_UNMATCHED_AS_NULL);

        if($exists) return true;

        $exists = preg_match('~(bot|crawl|ai)~i', $user_agent, flags: PREG_UNMATCHED_AS_NULL);

        return (bool) $exists;
    }

    public static function is_mobile(): bool
    {
        return
            !empty($_SERVER['HTTP_USER_AGENT']) &&
            preg_match('~(Mobile)~i', $_SERVER['HTTP_USER_AGENT'], flags: PREG_UNMATCHED_AS_NULL);
    }
}
