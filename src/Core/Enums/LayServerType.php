<?php

namespace BrickLayer\Lay\Core\Enums;

use BrickLayer\Lay\Libs\Primitives\Enums\EnumHelper;

enum LayServerType
{
    case APACHE;
    case NGINX;
    case CADDY;
    case PHP;
    case OTHER;
    case CLI;

    use EnumHelper;
}
