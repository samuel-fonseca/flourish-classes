<?php

namespace Traits;

use fDirectory;

/**
 * Gives a class access to a file system property.
 *
 * @var [type]
 */
trait hasTempDir
{
    private static $tempDir;

    public static function setTempDir($tempDir)
    {
        if (!$tempDir instanceof fDirectory) {
            $tempDir = new fDirectory($tempDir);
        }

        static::$tempDir = $tempDir;
    }

    public static function getTempDir(): fDirectory
    {
        return static::$tempDir;
    }
}
