<?php

namespace Traits;

use League\Flysystem\Filesystem;

/**
 * Gives a class access to a file system property.
 *
 * @var [type]
 */
trait hasFlysystem
{
    private static $flysystem;

    public static function setFlysystem(Filesystem $flysystem)
    {
        static::$flysystem = $flysystem;
    }

    public static function getFlysystem(): Filesystem
    {
        return static::$flysystem;
    }
}
