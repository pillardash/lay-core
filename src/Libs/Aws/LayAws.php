<?php

namespace BrickLayer\Lay\Libs\Aws;

use Aws\Credentials\Credentials;
use BrickLayer\Lay\Core\Exception;
use BrickLayer\Lay\Libs\Aws\Enums\AwsS3Client;
use BrickLayer\Lay\Libs\LayFn;
use BrickLayer\Lay\Libs\Primitives\Traits\IsSingleton;

final class LayAws
{
    use IsSingleton;
    
    public static AwsS3Client $type;
    public static array $credentials;

    public static function exception(string $title, string $message) : void
    {
        Exception::throw_exception($message, "AWS_" . $title);
    }

    public static function init( AwsS3Client $type = AwsS3Client::R2 ) : self
    {
        $credentials = new Credentials(LayFn::env('AWS_ACCESS_KEY_ID'),LayFn::env('AWS_ACCESS_KEY_SECRET'));
        $region = LayFn::env('AWS_REGION', 'auto');

        if($type == AwsS3Client::S3)
            $region = LayFn::env('AWS_REGION', 'us-east-1');

        $options = [
            'region' => $region,
            'version' => 'latest',
            'credentials' => $credentials,

            'use_path_style_endpoint' => LayFn::env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',

        ];

        if($type == AwsS3Client::R2) {
            $cf = LayFn::env('CLOUDFLARE_ACCOUNT_ID');

            if (!$cf) {
                self::exception("RequiredKeyNotSet", "`CLOUDFLARE_ACCOUNT_ID` env variable is not set. Please update your .env file and include it");
            }

            $options['endpoint'] = "https://{$cf}.r2.cloudflarestorage.com";
        }

        self::$credentials = $options;

        return self::new();
    }
}