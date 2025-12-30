<?php
namespace BrickLayer\Lay\Core\View;

final class SrcFilter
{
    public static function go(string $src, bool $use_client = true): string
    {
        $client = DomainResource::get();

        $x = str_replace(
            [
                "@shared/",
                "@#/",
                "@static/",
                "@shared_js/",
                "@js/",
                "@static_env/",
                "@shared_img/",
                "@img/",
                "@ui/",
                "@shared_css/",
                "@css/",
                "@shared_plugins/",
                "@plugins/",
            ],
            [
                $client->shared->root,
                $client->root,
                $client->static,
                $client->shared->js,
                $client->js,
                $client->static_env,
                $client->shared->img,
                $client->img,
                $client->ui,
                $client->shared->css,
                $client->css,
                $client->shared->plugins,
                $client->plugins,
            ],
            $src,
        );

        if ($use_client) {
            return $x;
        }

        return str_replace($client->root, $client->domain->public, $x);
    }
}
