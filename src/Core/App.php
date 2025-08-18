<?php

namespace BrickLayer\Lay\Core;

use BrickLayer\Lay\Core\Traits\Request;
use BrickLayer\Lay\Libs\Primitives\Traits\IsSingleton;
use BrickLayer\Lay\Orm\SQL;

/**
 * @property string $base
 * @property string $proto
 * @property string $base_no_proto
 * @property string $base_no_proto_no_www
 * @property string $server_mocked
 * @property string $domain
 * @property string $domain_no_proto
 * @property string $domain_no_proto_no_www
 * @property array{
 *     short: string,
 *     long: string,
 * } name
 * @property array{
 *     pry: string,
 *     sec: string,
 * } $color
 * @property array<int, string> $mail
 * @property string<int, string> $tel
 * @property string $author
 * @property string $copy
 * @property array<string, mixed> $globals
 * @property array<int, string> $ext_ignore_list
 * @property bool $use_domain_file
 */
final class App
{
    use IsSingleton;
    use Request;

    /**
     * @var array<string, mixed>
     */
    protected static array $options;


    public static function __up__(array $options) : self
    {
        self::$options = isset(self::$options) ? array_merge(self::$options, $options) : $options;

        return self::new();
    }

    public final function __get(string $key) : mixed
    {
        return self::$options[$key] ?? null;
    }

    public final function __isset(string $key) : bool
    {
        return isset(self::$options[$key]);
    }

    public static function __set_data(string $key, mixed $value) : void
    {
        self::$options[$key] = $value;
    }

    public static function globals() : array
    {
        return self::new()->globals;
    }

    public static function id() : string
    {
        return Server::new()->project_id();
    }

    public static function change_env($dev) : void
    {
        Server::__toggle_env($dev);
    }

    public static function is_prod() : bool
    {
        return Server::__is_prod();
    }

    public static function is_dev() : bool
    {
        return !Server::__is_prod();
    }

    public static function is_cli() : bool
    {
        return Server::__is_cli();
    }

    public static function is_not_cli() : bool
    {
        return !Server::__is_cli();
    }

    public static function connect() : void
    {
        Startup::new()->connect_db();
    }

    public static function orm() : SQL
    {
        return Server::orm();
    }

    public function props() : array
    {
        return self::$options;
    }
}