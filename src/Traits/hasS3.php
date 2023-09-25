<?php

namespace Traits;

use Aws\S3\S3Client;

trait hasS3
{
    private static $s3;

    public static function getS3(): S3Client
    {
        return static::$s3;
    }

    public static function setS3(S3Client $s3)
    {
        static::$s3 = $s3;
    }
}
