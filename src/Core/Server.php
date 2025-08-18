<?php

namespace BrickLayer\Lay\Core;

use BrickLayer\Lay\Core\Enums\LayMode;
use BrickLayer\Lay\Core\Enums\LayServerType;
use BrickLayer\Lay\Libs\Dir\LayDir;
use BrickLayer\Lay\Libs\ID\Gen;
use BrickLayer\Lay\Libs\Primitives\Traits\IsSingleton;
use BrickLayer\Lay\Orm\SQL;
use Dotenv\Dotenv;

/**
 * @property string $root
 * @property string $framework
 * @property string $lay
 * @property string $bricks
 * @property string $db
 * @property string $utils
 * @property string $web
 * @property string $lay_static
 * @property string $temp
 * @property string $exceptions
 * @property string $cron_outputs
 * @property string $shared
 * @property string $domains
 * @property string $uploads
 * @property string $uploads_no_root
 */
final class Server
{
    use IsSingleton;

    /**
     * @var array<string>
     */
    private static array $data;
    private static bool $env_loaded = false;
    private static LayMode $server_mode;
    private static bool $ENV_IS_DEV = true;

    public static function __start__(): void
    {
        if (isset(self::$data)) return;

        $slash = DIRECTORY_SEPARATOR;

        $dir = explode("{$slash}vendor{$slash}pillardash{$slash}lay-core", __DIR__ . $slash)[0] . $slash;

        $obj = [
            "root" => $dir,

            "framework" => $dir . "vendor" . $slash . "pillardash" . $slash . "lay-core" . $slash,
            "lay" => $dir . ".lay" . $slash,

            "bricks" => $dir . "bricks" . $slash,
            "db" => $dir . "db" . $slash,
            "utils" => $dir . "utils" . $slash,
            "web" => $dir . "web" . $slash,
        ];

        $obj['lay_static'] = $obj['framework'] . "src" . $slash . "static" . $slash;

        $obj['temp'] = $obj['lay'] . "temp" . $slash;
        $obj['exceptions'] = $obj['temp'] . "exceptions" . $slash;
        $obj['cron_outputs'] = $obj['temp'] . "cron_outputs" . $slash;

        $obj['shared'] = $obj['web'] . "shared" . $slash;
        $obj['domains'] = $obj['web'] . "domains" . $slash;
        $obj['uploads'] = $obj['web'] . "uploads" . $slash;
        $obj['uploads_no_root'] = "uploads" . $slash;

        self::$data = $obj;

        self::__set_dev_env();
        self::__load_env();
    }

    private static function __set_dev_env(): void
    {
        $env_host = $_SERVER['REMOTE_ADDR'] ?? $_ENV['LAY_SERVER_ADDR'] ?? "cli";
        $localhost = ["127.0.", "192.168.", "::1"];

        $env_is_prod = (
            $env_host !== "localhost" &&
            (
                !str_contains($env_host, $localhost[0]) &&
                !str_contains($env_host, $localhost[1]) &&
                !str_contains($env_host, $localhost[2])
            )
        );

        if (self::__is_cli() && !isset($_SERVER['SSH_CONNECTION']))
            $env_is_prod = false;

        self::$ENV_IS_DEV = !$env_is_prod;
    }

    public static function __is_cli(): bool
    {
        return self::__mode() == LayMode::CLI;
    }

    public static function __mode(): LayMode
    {
        if (isset(self::$server_mode))
            return self::$server_mode;

        self::$server_mode = LayMode::HTTP;

        if (empty($_SERVER['DOCUMENT_ROOT']) || (!isset($_SERVER['HTTP_HOST'])))
            self::$server_mode = LayMode::CLI;

        return self::$server_mode;

    }

    private static function __load_env(): void
    {
        if (self::$env_loaded)
            return;

        $root = self::$data['root'];

        if (!file_exists($root . ".env")) {
            if (file_exists($root . ".env.example"))
                copy($root . ".env.example", $root . ".env");
            else
                file_put_contents($root . ".env", "");
        }

        Dotenv::createImmutable($root)->load();
    }

    public static function __toggle_env(bool $dev): bool
    {
        return self::$ENV_IS_DEV = $dev;
    }

    public static function __is_prod(): bool
    {
        return !self::$ENV_IS_DEV;
    }

    public static function mock(string $host, bool $use_https): void
    {
        $_ENV['LAY_SERVER_HOST'] = $host;
        $_ENV['LAY_SERVER_ADDR'] = "mock.lay.pillardash.com";
        $_ENV['LAY_SERVER_MOCKING'] = true;
        $_ENV['LAY_SERVER_HTTPS'] = $use_https;

        $_ENV['LAY_CUSTOM_HOST'] = $host;
        $_ENV['LAY_CUSTOM_REMOTE_ADDR'] = "lay_remote_addr";
    }

    /**
     * Get OS of the application.
     * If you want the OS of the client, use the user_agent function
     * @return string
     */
    public static function os(): string
    {
        $OS = PHP_OS;
        $OS ??= explode(" ", php_uname(), 2)[0];
        $OS = strtoupper($OS);

        if (str_starts_with($OS, "DAR") || str_starts_with($OS, "MAC")) return "MAC";

        if (str_starts_with($OS, "WIN")) return "WINDOWS";

        return $OS;
    }

    public static function type(): LayServerType
    {
        $server_type = $_SERVER['SERVER_SOFTWARE'] ?? "CLI";

        return match (substr(strtolower($server_type), 0, 3)) {
            default => LayServerType::OTHER,
            "cli" => LayServerType::CLI,
            "apa" => LayServerType::APACHE,
            "php" => LayServerType::PHP,
            "ngi" => LayServerType::NGINX,
            "cad" => LayServerType::CADDY,
        };
    }

    public static function orm(): SQL
    {
        return Startup::__orm();
    }

    public final function __get(string $key): ?string
    {
        return self::$data[$key] ?? null;
    }

    public final function __isset(string $key): bool
    {
        return isset(self::$data[$key]);
    }

    public function make_temp_dir(): string
    {
        $dir = $this->temp;

        LayDir::make($dir, 0755, true);

        return $dir;
    }

    /**
     * Returns a generated project ID if it is generated or found, else returns null
     * @param bool $overwrite
     * @return string|null
     */
    public function project_id(bool $overwrite = false): ?string
    {
        $identity_file = $this->lay . "identity";

        $gen_id = function () use ($identity_file): string {
            $new_id = Gen::uuid(32);
            self::$data['project_id'] = $new_id;

            file_put_contents($identity_file, $new_id);
            return $new_id;
        };

        if ($overwrite)
            return $gen_id();

        if ($this->project_id ?? null)
            return $this->project_id;

        if (!file_exists($identity_file))
            return $gen_id();

        $static_id = file_get_contents($identity_file);

        if (empty($static_id))
            return $gen_id();

        self::$data['project_id'] = $static_id;

        return $static_id;
    }
}