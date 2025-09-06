<?php
declare(strict_types=1);
namespace BrickLayer\Lay\Core;

use BrickLayer\Lay\Core\View\Domain;
use BrickLayer\Lay\Libs\LayArray;
use BrickLayer\Lay\Libs\LayFn;
use BrickLayer\Lay\Libs\Primitives\Traits\IsSingleton;
use BrickLayer\Lay\Orm\SQL;

// If you want to measure the run time, just call this constant and subtract the new `microtime(true)` from it
define("LAY_START", microtime(true));

final class Startup {
    use IsSingleton;

    public const SESSION_KEY = "__LAY_STAT__";

    private static array $cached_cors;
    private static bool $cors_active = false;
    private static SQL $orm;

    public function name(string $short, string $long) : self
    {
        App::__set_data("name", [
            "short" => $short,
            "long" => $long
        ]);

        return $this;
    }

    public function color(string $pry, string $sec) : self
    {
        App::__set_data("color", [
            "pry" => $pry,
            "sec" => $sec
        ]);

        return $this;
    }

    public function email(string ...$email) : self
    {
        App::__set_data("email", $email);
        return $this;
    }

    public function tel(string ...$tel) : self
    {
        App::__set_data("tel", $tel);
        return $this;
    }

    public function author(string $author) : self
    {
        App::__set_data("author", $author);
        return $this;
    }

    public function copyright(string $copy) : self
    {
        App::__set_data("copy", $copy);
        return $this;
    }

    public function set_globals(array $globals) : self
    {
        App::__set_data("globals", $globals);
        return $this;
    }

    /**
     * Request to files that don't exist on the server is handled by `Lay`.
     * If the file is a static asset like `css`, a `404` response code and a
     * json response body is returned.
     * Hence, this method instructs `Lay` to ignore the specified files.
     *
     * You can instruct `Lay` to ignore files like: `xml`, `json`; and treat them
     * like a regular html page, maybe because you want to further process it in
     * the `Plaster` class.
     *
     * Please don't add dot (.), simply use the file extension directly.
     *
     * @param string ...$extensions
     * @return self
     * @example ignore_file_extensions("xml", "json")
     */
    public function ignore_file_extensions(string ...$extensions): self
    {
        App::__set_data("ext_ignore_list", $extensions);
        return $this;
    }

    public function connect_db() : self
    {
        self::__orm();

        return $this;
    }

    public static function __orm() : SQL
    {
        if(!isset(self::$orm))
            self::$orm = SQL::init();

        return self::$orm;
    }

    /**
     * @param array $allowed_origins String[] of allowed origins like "http://example.com"
     * @param bool $allow_all
     * @param callable|null $fun example `function(){ header("Access-Control-Allow-Origin: Origin, X-Requested-With, Content-Type, Accept"); }`
     * @param bool $lazy_cors
     * @return bool
     */
    public static function cors(
        array $allowed_origins = [],
        bool $allow_all = false,
        ?callable $fun = null,
        bool $lazy_cors = true,
    ) : bool
    {
        if(App::is_cli())
            return true;

        if ($lazy_cors) {
            self::$cached_cors = [$allowed_origins, $allow_all, $fun];
            return true;
        }

        self::$cors_active = true;

        $http_origin = rtrim($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['HTTP_REFERER'] ?? "", "/");

        if ($allow_all) {
            $http_origin = "*";
        } else {
            if (
                !LayArray::any(
                    $allowed_origins,
                    fn($v) => rtrim($v, "/") === $http_origin
                )
            ) return false;
        }

        // in an ideal word, this variable will only be empty if it's the same origin
        if (empty($http_origin)) return true;

        LayFn::header("Access-Control-Allow-Credentials: true");
        LayFn::header("Access-Control-Allow-Origin: $http_origin");
        LayFn::header('Access-Control-Max-Age: 86400');    // cache for 1 day

        // Access-Control headers are received during OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                LayFn::header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                LayFn::header("Access-Control-Allow-Headers:{$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

            exit(0);
        }

        if ($fun !== null) $fun();

        return true;
    }

    public static function cors_active(): bool
    {
        return self::$cors_active;
    }

    /**
     * @param array{
     *     assume_prod?: bool,
     *     expose_php?: bool,
     *     timezone?: string,
     *     only_cookies?: bool,
     *     http_only?: bool,
     *     secure?: bool,
     *     same_site?: string,
     *     samesite?: string,
     *     domain?: string,
     *     path?: string,
     *     lifetime?: int,
     * } $flags
     * @return void
     */
    public static function session(array $flags = []): void
    {
        if(App::is_cli())
            return;

        $cookie_opt = [];
        $assume_prod = $flags['assume_prod'] ?? App::is_prod();
        $flags['expose_php'] ??= false;
        $flags['timezone'] ??= LayFn::env('DEFAULT_TIMEZONE', 'Africa/Lagos');

        date_default_timezone_set($flags['timezone']);

        if (!$flags['expose_php']) {
            header_remove('X-Powered-By');
            header_remove('Server');
        }

        if (isset($flags['only_cookies']))
            ini_set("session.use_only_cookies", ((int)$flags['only_cookies']) . "");

        if ($assume_prod && (isset($flags['http_only']) || isset($flags['httponly'])))
            $cookie_opt['httponly'] = filter_var($flags['httponly'] ?? $flags['http_only'], FILTER_VALIDATE_BOOL);

        if ($assume_prod  && isset($flags['secure']))
            $cookie_opt['secure'] = filter_var($flags['secure'], FILTER_VALIDATE_BOOL);

        if ($assume_prod  && (isset($flags['samesite']) || isset($flags['same_site'])))
            $cookie_opt['samesite'] = ucfirst($flags['samesite'] ?? $flags['same_site']);

        if (isset($flags['domain']))
            $cookie_opt['domain'] = "." . $flags['domain'];

        if (isset($flags['path']))
            $cookie_opt['path'] = $flags['path'];

        if (isset($flags['lifetime']))
            $cookie_opt['lifetime'] = $flags['lifetime'];

        if (!empty($cookie_opt))
            session_set_cookie_params($cookie_opt);

        if (isset($_SESSION)) return;

        session_start();
        $_SESSION[self::SESSION_KEY] ??= [];
    }

    public static function call_lazy_cors(bool $about_to_die = false): void
    {
        if (isset(self::$cached_cors)) {
            self::cors(...self::$cached_cors, lazy_cors: false);
            return;
        }

        if($about_to_die && Domain::is_in_use()) {
            Domain::current_route_data("*");
        }
    }

    public static function validate_lay() : void
    {
        self::boot();

        if (!defined("SAFE_TO_INIT_LAY") || !SAFE_TO_INIT_LAY)
            Exception::throw_exception("This script cannot be accessed this way, please return home", "BadRequest");

        Exception::new()->capture_errors();
    }

    private static function boot() : void
    {
        Server::__start__();

        $slash          = DIRECTORY_SEPARATOR;
        $base           = $_SERVER['DOCUMENT_ROOT'];

        $dir = Server::new()->root;
        $pin = $base;
        $string = $dir;

        if(strlen($pin) > strlen($string)) {
            $pin = $dir;
            $string = $base;
        }

        $pin            = rtrim($pin, "/");
        $base           = $pin ? explode($pin, $string) : ["", ""];

        $options['using_web'] = str_starts_with($base[1], "/web");
        $options['using_domain'] = str_starts_with($base[1], "/web/domain");

        if($options['using_domain'] || $options['using_web'])
            $base = [""];

        $base           = str_replace($slash, "/", end($base));
        $http_host      = $_SERVER['HTTP_HOST'] ?? $_ENV['LAY_SERVER_HOST'] ?? "cli";
        $proto          = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? (@$_ENV['LAY_SERVER_HOSTS'] ? 'https' : 'http')) . "://";
        $base_no_proto  = rtrim(str_replace($slash,"/", $base),"/");

        $base = $proto . $http_host . $base_no_proto . "/";

        $base_no_proto  = $http_host . $base_no_proto;
        $base_no_proto_no_www  = str_replace("www.","", $base_no_proto);

        $options['base'] = $base;
        $options['proto'] = $proto;
        $options['base_no_proto'] = $base_no_proto;
        $options['base_no_proto_no_www'] = $base_no_proto_no_www;

        $options['server_mocked'] = false;
        $web = "";

        if(!$options['using_domain']) {
            $web = $options['using_web'] ? "" : "/web/";
            $options['use_domain_file'] = true;
        }

        if(@$_ENV['LAY_SERVER_MOCKING']) {
            $options['server_mocked'] = true;
            $options['use_domain_file'] = false;

            $web = "";
            $options['using_web'] = "";
            $options['using_domain'] = "";
        }

        $options['domain'] = $base . ltrim($web, "/");
        $options['domain_no_proto'] = $base_no_proto . $web;
        $options['domain_no_proto_no_www'] = $base_no_proto_no_www . $web;

        App::__up__($options);
    }
}
