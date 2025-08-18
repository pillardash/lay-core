<?php

namespace BrickLayer\Lay\Core\Resources;

use BrickLayer\Lay\Libs\Dir\LayDir;
use BrickLayer\Lay\Libs\ID\Gen;
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
class Server
{
    protected static self $instance;

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

    /**
     * @var array<string>
     */
    private array $data;
    private bool $env_loaded = false;

    private function __init_res__() : void
    {
        if(isset($this->data)) return;

        $slash = DIRECTORY_SEPARATOR;

        $dir = explode("{$slash}vendor{$slash}pillardash{$slash}lay-core", __DIR__ . $slash)[0] . $slash;

        $obj = [
            "root" => $dir,

            "framework" => $dir       .   "vendor"   . $slash . "pillardash" . $slash .   "lay-core" . $slash,
            "lay" => $dir  .   ".lay"     .   $slash,

            "bricks" => $dir  .   "bricks"        .   $slash,
            "db" => $dir  .   "db"     .   $slash,
            "utils" => $dir  .   "utils"     .   $slash,
            "web" => $dir  .   "web"     .   $slash,
        ];

        $obj['lay_static']        =   $obj['framework']  . "src"        . $slash . "static"     . $slash;

        $obj['temp']        =   $obj['lay']  . "temp" . $slash;
        $obj['exceptions']        =   $obj['temp']  . "exceptions" . $slash;
        $obj['cron_outputs']        =   $obj['temp']  . "cron_outputs" . $slash;

        $obj['shared']        =   $obj['web']  . "shared" . $slash;
        $obj['domains']        =   $obj['web']  . "domains" . $slash;
        $obj['uploads']        =   $obj['web']  . "uploads" . $slash;
        $obj['uploads_no_root']        =   "uploads" . $slash;

        $this->data = $obj;
    }

    public final function __get(string $key) : ?string
    {
        return $this->data[$key] ?? null;
    }

    public final function __isset(string $key) : bool
    {
        return isset($this->data[$key]);
    }

    public function make_temp_dir () : string
    {
        $dir = $this->temp;

        LayDir::make($dir, 0755, true);

        return $dir;
    }

    public function load_env() : void {
        if($this->env_loaded)
            return;

        if (!file_exists($this->root . ".env")) {
            if(file_exists($this->root . ".env.example"))
                copy($this->root . ".env.example", $this->root . ".env");
            else
                file_put_contents($this->root . ".env", "");
        }

        Dotenv::createImmutable($this->root)->load();
    }

    /**
     * Returns a generated project ID if it is generated or found, else returns null
     * @param bool $overwrite
     * @return string|null
     */
    public function project_id(bool $overwrite = false) : ?string
    {
        $identity_file = $this->lay . "identity";

        $gen_id = function () use ($identity_file): string {
            $new_id = Gen::uuid(32);
            $this->data['project_id'] = $new_id;

            file_put_contents($identity_file, $new_id);
            return $new_id;
        };

        if($overwrite)
            return $gen_id();

        if($this->project_id ?? null)
            return $this->project_id;

        if(!file_exists($identity_file))
            return $gen_id();

        $static_id = file_get_contents($identity_file);

        if(empty($static_id))
            return $gen_id();

        $this->data['project_id'] = $static_id;

        return $static_id;
    }


}