<?php

namespace BrickLayer\Lay\Core\Api\Enums;

enum ApiRequestMethod
{
    case POST;
    case GET;
    case HEAD;
    case PUT;
    case DELETE;
    case PATCH;
}
