<?php

namespace Traits;

use fFlysystem;
use fGrammar;
use fHTML;
use fORM;
use fORMFlysystemFile;
use fURL;
use fValidationException;

/**
 * Adds fFlysystemFile capabilities to an fActiveRecord class.
 *
 * You must define a $fileColumnPaths attribute on any class using this trait.
 */
trait hasFFlysystemFile
{
    /**
     * a column name => directory mapping for files.
     *
     *  public $fileColumnPaths = [
     *      'column-name' => 's3prefix/',
     * ];
     *
     * @var array
     *
     * protected $fileColumnPaths = [];
     */

    /**
     * Set to true in the class using this trait if you want to generate a url
     * that appears local to the server for file files.
     * For example, if you have an entry in $fileColumnPaths like
     * 'documents' => 'documents/rights-documents', setting this to true will
     * generate urls like https://servername/documents/rights-documents/filename.pdf.
     *
     * **It is up to the programmer to implement handling for the routes.**
     *
     * @var bool false
     *
     * protected $useFileRoutes = false;
     */

    /**
     * Initialize the fileColumnPath as fORMFlysystem file uploads. Should be called
     * in `fActiveRecord::configure()``.
     */
    public function initUploads()
    {
        foreach ($this->fileColumnPaths as $column => $path) {
            $class = get_class($this);

            fORMFlysystemFile::configureFileUploadColumn(
                $class,
                $column,
                $path
            );
        }
    }

    public function registerActiveRecordMethods()
    {
        $flysystem = fFlysystem::getFlysystem();

        foreach ($this->fileColumnPaths as $column => $path) {
            fORM::registerActiveRecordMethod(
                $this,
                'get'.fGrammar::camelize($column, true),
                function ($object, &$values, &$old, &$related, &$cache, $method, $parameters) use ($column, $path) {
                    if ($values[$column]) {
                        try {
                            return fFlysystem::createObject($path.$values[$column]);
                        } catch (fValidationException $e) {
                            return;
                        }
                    }
                }
            );

            fORM::registerActiveRecordMethod(
                $this,
                'get'.fGrammar::camelize($column, true).'Url',
                function ($object, &$values, &$old, &$related, &$cache, $method, $parameters) use ($column, $path, $flysystem) {
                    if ($values[$column]) {
                        return "https://s3.amazonaws.com/{$flysystem->getAdapter()->getBucket()}/{$path}{$values[$column]}";
                    }
                }
            );

            fORM::registerActiveRecordMethod(
                $this,
                'get'.fGrammar::camelize($column, true).'Name',
                function ($object, &$values, &$old, &$related, &$cache, $method, $parameters) use ($column) {
                    if ($values[$column]) {
                        return fHTML::prepare($values[$column]);
                    }
                }
            );

            fORM::registerActiveRecordMethod(
                $this,
                'prepare'.fGrammar::camelize($column, true),
                function ($object, &$values, &$old, &$related, &$cache, $method, $parameters) use ($column) {
                    if (! $object->getUseFileRoutes()) {
                        return fORMFlysystemFile::prepare(
                            $object,
                            $values,
                            $old,
                            $related,
                            $cache,
                            $method,
                            $parameters
                        );
                    }

                    if (empty($parameters)) {
                        $url = false;
                    } else {
                        $url = $parameters[0];
                    }

                    if (! $url) {
                        return $values[$column];
                    }

                    $info = pathinfo($values[$column]);

                    $filename = rawurlencode($info['basename']);

                    $dirname = '';
                    if ($info['dirname'] !== '.') {
                        $dirname = $info['dirname'].'/';
                    }

                    $url = fURL::getDomain().'/'.$this->fileColumnPaths[$column].$dirname.$filename;

                    return $url;
                }
            );

            // fORM::registerActiveRecordMethod(
            //     $this,
            //     'create' . fGrammar::camelize($column, true),
            //     function($object, &$values, &$old, &$related, &$cache, $method, $parameters) use ($column, $path, $flysystem) {
            //         if ($values[$column]) {
            //             $file = fFlysystemFile::create()
            //         }
            //
            //         return null;
            //     }
            // );
        }
    }

    public function getFileUploadPaths()
    {
        return $this->fileColumnPaths ?? [];
    }

    public function getUseFileRoutes()
    {
        if (! isset($this->useFileRoutes)) {
            return false;
        }

        return $this->useFileRoutes;
    }
}
