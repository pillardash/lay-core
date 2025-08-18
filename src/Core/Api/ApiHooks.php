<?php
declare(strict_types=1);

namespace BrickLayer\Lay\Core\Api;

use BrickLayer\Lay\Core\App;
use BrickLayer\Lay\Core\LayException;

abstract class ApiHooks extends ApiEngine
{
    protected static bool $is_invalidating = false;

    final public function __construct() {
        $this->start($this::class);

        if(App::is_dev())
            self::set_debug_mode();
    }

    /**
     * Only used by Brick classes, not used by Apex class
     * @return void
     */
    abstract protected function hooks() : void;

    /**
     * Operations to run before loading the hooks for both apex and bricks
     * @return void
     */
    protected function pre_hook() : void {}

    /**
     * Operations to run after loading the hooks for both apex and bricks
     * @return void
     */
    protected function post_hook() : void {}

    /**
     * This is public on purpose, so don't change the visibility
     * @return void
     * @throws \Exception
     */
    public final function exec_hooks() : void
    {
        if(!self::$is_invalidating && !str_starts_with(static::class, "Bricks\\"))
            LayException::throw("You can only use this method in a Brick Hook class, not: " . static::class);

        $this->pre_hook();
        $this->hooks();
        $this->post_hook();
    }
}
