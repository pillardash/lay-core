<?php
namespace Web\Api;

use BrickLayer\Lay\Core\Api\ApiHooks;

class Plaster extends ApiHooks
{
    protected function pre_hook(): void
    {
        $this->set_version("v1");

        $this->group_limit(60, "1 minute");
    }

    protected function hooks(): void {}
}