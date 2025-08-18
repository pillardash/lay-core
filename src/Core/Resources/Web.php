<?php

namespace BrickLayer\Lay\Core\Resources;

/**
 *
 * @property array{
 *     short: string,
 *     long: string,
 * } $name
 * @property string $author
 * @property string $copy
 * @property array{
 *     pry: string,
 *     sec: string,
 * } $color
 * @property array<int, string> $mail
 * @property string<int, string> $tel
 * @property string<string, mixed> $others
 */
class Web
{
    protected static self $instance;

    /**
     * @var array<string, mixed>
     */
    protected static array $options;

    /**
     * @var array<string>
     */
    private array $data;

    private function __clone(){}
    private function __construct()
    {
        $this->__init_res__();
    }

    public static function new() : self
    {
        if(!isset(self::$instance))
            self::$instance = new self();

        return self::$instance;
    }

    public static function __up__(array $options) : self
    {
        self::$options = $options;
        return self::new();
    }

    public static function __re_up__(array $options) : self
    {
        self::$options = $options;
        return self::new();
    }

    private function __init_res__() : void
    {
        if(isset($this->data)) return;

        $this->data = self::$options;
    }

    public final function __get(string $key) : ?string
    {
        return $this->data[$key] ?? null;
    }

    public final function __isset(string $key) : bool
    {
        return isset($this->data[$key]);
    }
}