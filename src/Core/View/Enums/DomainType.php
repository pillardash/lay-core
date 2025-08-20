<?php

namespace BrickLayer\Lay\Core\View\Enums;

enum DomainType : string
{
    // Local or Sub Domain
    case LOCAL = "LOCAL_DOMAIN";
    case SUB = "SUB_DOMAIN";

    // Domain Create Type
    case REGULAR = "REGULAR";
    case SPECIAL = "SPECIAL";
    case CUSTOM = "CUSTOM";
}
