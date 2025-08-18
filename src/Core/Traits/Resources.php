<?php
declare(strict_types=1);
namespace BrickLayer\Lay\Core\Traits;
use BrickLayer\Lay\Libs\Dir\LayDir;
use BrickLayer\Lay\Libs\ID\Gen;
use Dotenv\Dotenv;

trait Resources {
    private static object $site;

    protected static function set_internal_site_data(array $options) : void {
        $to_object = function (&$value) : void {
            $value = (object) $value;
        };

        $obj = array_merge([
            "author" => $options['author'] ?? null,
            "name" => $options['name'] ?? null,
            "color" => $options['color'] ?? null,
            "mail" => [
                ...$options['mail'] ?? []
            ],
            "tel" => $options['tel'] ?? null,
            "others" => $options['others'] ?? null,
        ], $options );

        $to_object($obj['name']);
        $to_object($obj['color']);
        $to_object($obj['mail']);
        $to_object($obj['tel']);
        $to_object($obj['others']);

        self::$site = (object) $obj;
    }


    /**
     * ## Please only use the keys specified here; any key not specified may be removed in future versions
     * @return  object{
     *      base: string,
     *      proto: string,
     *      base_no_proto: string,
     *      base_no_proto_no_www: string,
     *      domain: string,
     *      domain_no_proto: string,
     *      domain_no_proto_no_www: string,
     *      server_mocked: bool,
     *      author: string,
     *      global_api: string,
     *      name: object{
     *          long : string,
     *          short: string
     *      },
     *     color: object{
     *          pry: string,
     *          sec: string
     *     },
     *     mail: object<int>,
     *     tel: object<int>,
     *     others: object
     * }
     */
    public static function site_data() : object
    {
        self::is_init(true);
        return self::$site;
    }

}
