<?php
/**
 * Provides Flysystem file manipulation functionality for fActiveRecord classes.
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fORMFlysystemFile
 */

use Traits\hasTempDir;

class fORMFlysystemFile
{
    use hasTempDir;

    // The following constants allow for nice looking callbacks to static methods
    public const addFImageMethodCall = 'fORMFlysystemFile::addFImageMethodCall';

    public const addFUploadMethodCall = 'fORMFlysystemFile::addFUploadMethodCall';

    public const begin = 'fORMFlysystemFile::begin';

    public const commit = 'fORMFlysystemFile::commit';

    public const configureColumnInheritance = 'fORMFlysystemFile::configureColumnInheritance';

    public const configureFileUploadColumn = 'fORMFlysystemFile::configureFileUploadColumn';

    public const configureImageUploadColumn = 'fORMFlysystemFile::configureImageUploadColumn';

    public const delete = 'fORMFlysystemFile::delete';

    public const deleteOld = 'fORMFlysystemFile::deleteOld';

    public const encode = 'fORMFlysystemFile::encode';

    public const inspect = 'fORMFlysystemFile::inspect';

    public const moveFromTemp = 'fORMFlysystemFile::moveFromTemp';

    public const objectify = 'fORMFlysystemFile::objectify';

    public const populate = 'fORMFlysystemFile::populate';

    public const prepare = 'fORMFlysystemFile::prepare';

    public const process = 'fORMFlysystemFile::process';

    public const processImage = 'fORMFlysystemFile::processImage';

    public const reflect = 'fORMFlysystemFile::reflect';

    public const replicate = 'fORMFlysystemFile::replicate';

    public const reset = 'fORMFlysystemFile::reset';

    public const rollback = 'fORMFlysystemFile::rollback';

    public const set = 'fORMFlysystemFile::set';

    public const upload = 'fORMFlysystemFile::upload';

    public const validate = 'fORMFlysystemFile::validate';

    /**
     * The temporary directory to use for various tasks.
     *
     * @internal
     *
     * @var string
     */
    public const TEMP_DIRECTORY = '__flourish_temp';

    private fFlysystem $filesystem;

    /**
     * Defines how columns can inherit uploaded files.
     *
     * @var array
     */
    private static $column_inheritence = [];

    /**
     * Methods to be called on fUpload before the file is uploaded.
     *
     * @var array
     */
    private static $fupload_method_calls = [];

    /**
     * Columns that can be filled by file uploads.
     *
     * @var array
     */
    private static $file_upload_columns = [];

    /**
     * Methods to be called on the fImage instance.
     *
     * @var array
     */
    private static $fimage_method_calls = [];

    /**
     * Columns that can be filled by image uploads.
     *
     * @var array
     */
    private static $image_upload_columns = [];

    /**
     * Keeps track of the nesting level of the filesystem transaction so we know when to start, commit, rollback, etc.
     *
     * @var int
     */
    private static $transaction_level = 0;

    /**
     * @return fORMFlysystemFile
     */
    private function __construct(fFlysystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Adds an fImage method call to the image manipulation for a column if an image file is uploaded.
     *
     * @param mixed  $class      The class name or instance of the class
     * @param string $column     The column to call the method for
     * @param string $method     The fImage method to call
     * @param array  $parameters The parameters to pass to the method
     */
    public static function addFImageMethodCall($class, $column, $method, $parameters = []): void
    {
        $class = fORM::getClass($class);

        if (empty(self::$file_upload_columns[$class][$column])) {
            throw new fProgrammerException(
                'The column specified, %s, has not been configured as a file or image upload column',
                $column
            );
        }

        if (empty(self::$fimage_method_calls[$class])) {
            self::$fimage_method_calls[$class] = [];
        }
        if (empty(self::$fimage_method_calls[$class][$column])) {
            self::$fimage_method_calls[$class][$column] = [];
        }

        self::$fimage_method_calls[$class][$column][] = [
            'method' => $method,
            'parameters' => $parameters,
        ];
    }

    /**
     * Adds an fUpload method call to the fUpload initialization for a column.
     *
     * @param mixed  $class      The class name or instance of the class
     * @param string $column     The column to call the method for
     * @param string $method     The fUpload method to call
     * @param array  $parameters The parameters to pass to the method
     */
    public static function addFUploadMethodCall($class, $column, $method, $parameters = []): void
    {
        if ($method == 'enableOverwrite') {
            throw new fProgrammerException(
                'The method specified, %1$s, is not compatible with how %2$s stores and associates files with records',
                $method,
                'fORMFlysystemFile'
            );
        }

        $class = fORM::getClass($class);

        if (empty(self::$file_upload_columns[$class][$column])) {
            throw new fProgrammerException(
                'The column specified, %s, has not been configured as a file or image upload column',
                $column
            );
        }

        if (empty(self::$fupload_method_calls[$class])) {
            self::$fupload_method_calls[$class] = [];
        }
        if (empty(self::$fupload_method_calls[$class][$column])) {
            self::$fupload_method_calls[$class][$column] = [];
        }

        self::$fupload_method_calls[$class][$column][] = [
            'method' => $method,
            'parameters' => $parameters,
        ];
    }

    /**
     * Begins a transaction, or increases the level.
     *
     * @internal
     *
     * @return void
     */
    public static function begin()
    {
        // If the transaction was started by something else, don't even track it
        if (self::$transaction_level == 0 && fFlysystem::isInsideTransaction()) {
            return;
        }

        self::$transaction_level++;

        if (! fFlysystem::isInsideTransaction()) {
            fFlysystem::begin();
        }
    }

    /**
     * Commits a transaction, or decreases the level.
     *
     * @internal
     *
     * @return void
     */
    public static function commit()
    {
        // If the transaction was started by something else, don't even track it
        if (self::$transaction_level == 0) {
            return;
        }

        self::$transaction_level--;

        if (! self::$transaction_level) {
            fFlysystem::commit();
        }
    }

    /**
     * Sets a column to be a file upload column.
     *
     * Configuring a column to be a file upload column means that whenever
     * fActiveRecord::populate() is called for an fActiveRecord object, any
     * appropriately named file uploads (via `$_FILES`) will be moved into
     * the directory for this column.
     *
     * Setting the column to a file path will cause the specified file to
     * be copied into the directory for this column.
     *
     * @param mixed             $class     The class name or instance of the class
     * @param string            $column    The column to set as a file upload column
     * @param fDirectory|string $directory The directory to upload/move to
     */
    public static function configureFileUploadColumn($class, $column, $directory): void
    {
        $class = fORM::getClass($class);
        $table = fORM::tablize($class);
        $schema = fORMSchema::retrieve($class);
        $data_type = $schema->getColumnInfo($table, $column, 'type');

        $valid_data_types = ['varchar', 'char', 'text'];
        if (! in_array($data_type, $valid_data_types)) {
            throw new fProgrammerException(
                'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a file upload column.',
                $column,
                $data_type,
                implode(', ', $valid_data_types)
            );
        }

        if (! $directory instanceof fFlysystemDirectory) {
            $directory = new fFlysystemDirectory($directory);
        }

        if (! $directory->isWritable()) {
            throw new fEnvironmentException(
                'The file upload directory, %s, is not writable',
                $directory->getPath()
            );
        }

        $camelized_column = fGrammar::camelize($column, true);

        fORM::registerActiveRecordMethod(
            $class,
            'upload'.$camelized_column,
            self::upload
        );

        fORM::registerActiveRecordMethod(
            $class,
            'set'.$camelized_column,
            self::set
        );

        fORM::registerActiveRecordMethod(
            $class,
            'encode'.$camelized_column,
            self::encode
        );

        fORM::registerActiveRecordMethod(
            $class,
            'prepare'.$camelized_column,
            self::prepare
        );

        fORM::registerReflectCallback($class, self::reflect);
        fORM::registerInspectCallback($class, $column, self::inspect);
        fORM::registerReplicateCallback($class, $column, self::replicate);
        fORM::registerObjectifyCallback($class, $column, self::objectify);

        $only_once_hooks = [
            'post-begin::delete()' => self::begin,
            'pre-commit::delete()' => self::delete,
            'post-commit::delete()' => self::commit,
            'post-rollback::delete()' => self::rollback,
            'post::populate()' => self::populate,
            'post-begin::store()' => self::begin,
            'post-validate::store()' => self::moveFromTemp,
            'pre-commit::store()' => self::deleteOld,
            'post-commit::store()' => self::commit,
            'post-rollback::store()' => self::rollback,
            'post::validate()' => self::validate,
        ];

        foreach ($only_once_hooks as $hook => $callback) {
            if (! fORM::checkHookCallback($class, $hook, $callback)) {
                fORM::registerHookCallback($class, $hook, $callback);
            }
        }

        if (empty(self::$file_upload_columns[$class])) {
            self::$file_upload_columns[$class] = [];
        }

        self::$file_upload_columns[$class][$column] = $directory;
    }

    /**
     * Takes one file or image upload columns and sets it to inherit any uploaded/set files from another column.
     *
     * @param mixed  $class               The class name or instance of the class
     * @param string $column              The column that will inherit the uploaded file
     * @param string $inherit_from_column The column to inherit the uploaded file from
     */
    public static function configureColumnInheritance($class, $column, $inherit_from_column): void
    {
        $class = fORM::getClass($class);

        if (empty(self::$file_upload_columns[$class][$column])) {
            throw new fProgrammerException(
                'The column specified, %s, has not been configured as a file upload column',
                $column
            );
        }

        if (empty(self::$file_upload_columns[$class][$inherit_from_column])) {
            throw new fProgrammerException(
                'The column specified, %s, has not been configured as a file upload column',
                $column
            );
        }

        if (empty(self::$column_inheritence[$class])) {
            self::$column_inheritence[$class] = [];
        }

        if (empty(self::$column_inheritence[$class][$inherit_from_column])) {
            self::$column_inheritence[$class][$inherit_from_column] = [];
        }

        self::$column_inheritence[$class][$inherit_from_column][] = $column;
    }

    /**
     * Sets a column to be an image upload column.
     *
     * This method works exactly the same as ::configureFileUploadColumn()
     * except that only image files are accepted.
     *
     * @param mixed             $class      The class name or instance of the class
     * @param string            $column     The column to set as a file upload column
     * @param fDirectory|string $directory  The directory to upload to
     * @param string            $image_type The image type to save the image as: `NULL`, `'gif'`, `'jpg'`, `'png'`
     */
    public static function configureImageUploadColumn($class, $column, $directory, $image_type = null): void
    {
        $valid_image_types = [null, 'gif', 'jpg', 'png'];
        if (! in_array($image_type, $valid_image_types)) {
            $valid_image_types[0] = '{null}';

            throw new fProgrammerException(
                'The image type specified, %1$s, is not valid. Must be one of: %2$s.',
                $image_type,
                implode(', ', $valid_image_types)
            );
        }

        self::configureFileUploadColumn($class, $column, $directory);

        $class = fORM::getClass($class);

        $camelized_column = fGrammar::camelize($column, true);

        fORM::registerActiveRecordMethod(
            $class,
            'process'.$camelized_column,
            self::process
        );

        if (empty(self::$image_upload_columns[$class])) {
            self::$image_upload_columns[$class] = [];
        }

        self::$image_upload_columns[$class][$column] = $image_type;

        self::addFUploadMethodCall(
            $class,
            $column,
            'setMimeTypes',
            [
                [
                    'image/gif',
                    'image/jpeg',
                    'image/pjpeg',
                    'image/png',
                ],
                self::compose('The file uploaded is not an image'),
            ]
        );
    }

    /**
     * Deletes the files for this record.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     */
    public static function delete($object, &$values, &$old_values, &$related_records, &$cache): void
    {
        $class = get_class($object);

        foreach (self::$file_upload_columns[$class] as $column => $directory) {
            // Remove the current file for the column
            if ($values[$column] instanceof fFlysystemFile) {
                $values[$column]->delete();
            }

            // Remove the old files for the column
            foreach (fActiveRecord::retrieveOld($old_values, $column, [], true) as $file) {
                if ($file instanceof fFile) {
                    $file->delete();
                }
            }
        }
    }

    /**
     * Deletes old files for this record that have been replaced by new ones.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     */
    public static function deleteOld($object, &$values, &$old_values, &$related_records, &$cache): void
    {
        $class = get_class($object);

        // Remove the old files for the column
        foreach (self::$file_upload_columns[$class] as $column => $directory) {
            $current_file = $values[$column];
            foreach (fActiveRecord::retrieveOld($old_values, $column, [], true) as $file) {
                if ($file instanceof fFlysystemFile && (! $current_file instanceof fFlysystemFile || $current_file->getPath() != $file->getPath())) {
                    $file->delete();
                }
            }
        }
    }

    /**
     * Encodes a file for output into an HTML `input` tag.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     * @param string        $method_name      The method that was called
     * @param array         $parameters       The parameters passed to the method
     *
     * @return null|string
     */
    public static function encode($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        [$action, $subject] = fORM::parseMethod($method_name);
        $column = fGrammar::underscorize($subject);

        $path = $object->getFileUploadPaths()[$column] ?? null;

        if (! $path) {
            return;
        }

        $value = $values[$column] ?? null;

        if (! $value) {
            return;
        }

        $filename = $path.$values[$column];

        try {
            $file = new fFlysystemFile($filename);
        } catch (Exception $e) {
            return;
        }

        if ($object->getUseFileRoutes()) {
            return fHTML::encode($file->getPath());
        }

        return fHTML::encode(fFlysystemFile::getFlysystem()->getObjectUrl($filename));
    }

    /**
     * Adds metadata about features added by this class.
     *
     * @internal
     *
     * @param string $class     The class being inspected
     * @param string $column    The column being inspected
     * @param array  &$metadata The array of metadata about a column
     */
    public static function inspect($class, $column, &$metadata): void
    {
        if (! empty(self::$image_upload_columns[$class][$column])) {
            $metadata['feature'] = 'image';
        } elseif (! empty(self::$file_upload_columns[$class][$column])) {
            $metadata['feature'] = 'file';
        }

        $metadata['directory'] = self::$file_upload_columns[$class][$column]->getPath();
    }

    /**
     * Moves uploaded files from the temporary directory to the permanent directory.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     */
    public static function moveFromTemp($object, &$values, &$old_values, &$related_records, &$cache): void
    {
        foreach ($values as $column => $value) {
            if (! $value instanceof fFile) {
                continue;
            }

            // If the file is in a temp dir, move it out
            if (strpos($value->getParent()->getPath(), self::TEMP_DIRECTORY.DIRECTORY_SEPARATOR) !== false) {
                $new_filename = str_replace(self::TEMP_DIRECTORY.DIRECTORY_SEPARATOR, '', $value->getPath());
                $new_filename = fFlysystem::makeUniqueName($new_filename);
                $value->rename($new_filename, false);
            }
        }
    }

    /**
     * Turns a filename into an fFile or fImage object.
     *
     * @internal
     *
     * @param string $class  The class this value is for
     * @param string $column The column the value is in
     * @param mixed  $value  The value
     *
     * @return mixed The fFile, fImage or raw value
     */
    public static function objectify($class, $column, $value)
    {
        if ((! is_string($value) && ! is_numeric($value) && ! is_object($value)) || ! strlen(trim($value))) {
            return $value;
        }

        $path = self::$file_upload_columns[$class][$column]->getPath().$value;

        try {
            return fFilesystem::createObject($path);
            // If there was some error creating the file, just return the raw value
        } catch (fExpectedException $e) {
            return $value;
        }
    }

    /**
     * Performs the upload action for file uploads during fActiveRecord::populate().
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     */
    public static function populate($object, &$values, &$old_values, &$related_records, &$cache): void
    {
        $class = get_class($object);

        foreach (self::$file_upload_columns[$class] as $column => $directory) {
            if (
                fUpload::check($column)
                || fRequest::check('existing-'.$column)
                || fRequest::check('delete-'.$column)
            ) {
                $method = 'upload'.fGrammar::camelize($column, true);
                $object->{$method}();
            }
        }
    }

    /**
     * Prepares a file for output into HTML by returning filename or the web server path to the file.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     * @param string        $method_name      The method that was called
     * @param array         $parameters       The parameters passed to the method
     */
    public static function prepare($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters): string
    {
        [$action, $subject] = fORM::parseMethod($method_name);
        $column = fGrammar::underscorize($subject);

        if (count($parameters) > 1) {
            throw new fProgrammerException(
                'The column specified, %s, does not accept more than one parameter',
                $column
            );
        }

        $translate_to_web_path = (empty($parameters[0])) ? false : true;
        $value = $values[$column];

        if ($value instanceof fFlysystemFile) {
            $path = ($translate_to_web_path) ? $value->getObjectUrl() : $value->getName();
        } else {
            try {
                $path = $object->getFileUploadPaths()[$column];
                $value = $path.$values[$column];
                $value = new fFlysystemFile($value);
                $path = ($translate_to_web_path) ? $value->getObjectUrl() : $value->getName();
            } catch (fValidationException $e) {
                $path = null;
            }
        }

        return fHTML::prepare($path);
    }

    /**
     * Handles re-processing an existing image file.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     * @param string        $method_name      The method that was called
     * @param array         $parameters       The parameters passed to the method
     *
     * @return fActiveRecord The record object, to allow for method chaining
     */
    public static function process($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        [$action, $subject] = fORM::parseMethod($method_name);

        $column = fGrammar::underscorize($subject);
        $class = get_class($object);

        self::processImage($class, $column, $values[$column]);

        return $object;
    }

    /**
     * Performs image manipulation on an uploaded/set image.
     *
     * @internal
     *
     * @param string $class  The name of the class we are manipulating the image for
     * @param string $column The column the image is assigned to
     * @param fFile  $image  The image object to manipulate
     *
     * @return void
     */
    public static function processImage($class, $column, $image)
    {
        // If we don't have an image or we haven't set it up to manipulate images, just exit
        if (! $image instanceof fImage || empty(self::$fimage_method_calls[$class][$column])) {
            return;
        }

        // Manipulate the image
        if (! empty(self::$fimage_method_calls[$class][$column])) {
            foreach (self::$fimage_method_calls[$class][$column] as $method_call) {
                $callback = [$image, $method_call['method']];
                $parameters = $method_call['parameters'];
                if (! is_callable($callback)) {
                    throw new fProgrammerException(
                        'The fImage method specified, %s, is not a valid method',
                        $method_call['method'].'()'
                    );
                }
                call_user_func_array($callback, $parameters);
            }
        }

        // Save the changes
        call_user_func(
            [$image, 'saveChanges'],
            self::$image_upload_columns[$class][$column] ?? null
        );
    }

    /**
     * Adjusts the fActiveRecord::reflect() signatures of columns that have been configured in this class.
     *
     * @internal
     *
     * @param string $class                The class to reflect
     * @param array  &$signatures          The associative array of `{method name} => {signature}`
     * @param bool   $include_doc_comments If doc comments should be included with the signature
     */
    public static function reflect($class, &$signatures, $include_doc_comments): void
    {
        $image_columns = (isset(self::$image_upload_columns[$class])) ? array_keys(self::$image_upload_columns[$class]) : [];
        $file_columns = (isset(self::$file_upload_columns[$class])) ? array_keys(self::$file_upload_columns[$class]) : [];

        foreach ($file_columns as $column) {
            $camelized_column = fGrammar::camelize($column, true);

            $noun = 'file';
            if (in_array($column, $image_columns)) {
                $noun = 'image';
            }

            $signature = '';
            if ($include_doc_comments) {
                $signature .= "/**\n";
                $signature .= ' * Encodes the filename of '.$column." for output into an HTML form\n";
                $signature .= " * \n";
                $signature .= " * Only the filename will be returned, any directory will be stripped.\n";
                $signature .= " * \n";
                $signature .= " * @return string  The HTML form-ready value\n";
                $signature .= " */\n";
            }
            $encode_method = 'encode'.$camelized_column;
            $signature .= 'public function '.$encode_method.'()';

            $signatures[$encode_method] = $signature;

            if (in_array($column, $image_columns)) {
                $signature = '';
                if ($include_doc_comments) {
                    $signature .= "/**\n";
                    $signature .= " * Takes the existing image and runs it through the prescribed fImage method calls\n";
                    $signature .= " * \n";
                    $signature .= " * @return fActiveRecord  The record object, to allow for method chaining\n";
                    $signature .= " */\n";
                }
                $process_method = 'process'.$camelized_column;
                $signature .= 'public function '.$process_method.'()';

                $signatures[$process_method] = $signature;
            }

            $signature = '';
            if ($include_doc_comments) {
                $signature .= "/**\n";
                $signature .= ' * Prepares the filename of '.$column." for output into HTML\n";
                $signature .= " * \n";
                $signature .= " * By default only the filename will be returned and any directory will be stripped.\n";
                $signature .= " * The \$include_web_path parameter changes this behaviour.\n";
                $signature .= " * \n";
                $signature .= ' * @param  boolean $include_web_path  If the full web path to the '.$noun." should be included\n";
                $signature .= " * @return string  The HTML-ready value\n";
                $signature .= " */\n";
            }
            $prepare_method = 'prepare'.$camelized_column;
            $signature .= 'public function '.$prepare_method.'($include_web_path=FALSE)';

            $signatures[$prepare_method] = $signature;

            $signature = '';
            if ($include_doc_comments) {
                $signature .= "/**\n";
                $signature .= ' * Takes a file uploaded through an HTML form for '.$column." and moves it into the specified directory\n";
                $signature .= " * \n";
                $signature .= " * Any columns that were designated as inheriting from this column will get a copy\n";
                $signature .= " * of the uploaded file.\n";
                $signature .= " * \n";
                if ($noun == 'image') {
                    $signature .= " * Any fImage calls that were added to the column will be processed on the uploaded image.\n";
                    $signature .= " * \n";
                }
                $signature .= " * @return fFile  The uploaded file\n";
                $signature .= " */\n";
            }
            $upload_method = 'upload'.$camelized_column;
            $signature .= 'public function '.$upload_method.'()';

            $signatures[$upload_method] = $signature;

            $signature = '';
            if ($include_doc_comments) {
                $signature .= "/**\n";
                $signature .= ' * Takes a file that exists on the filesystem and copies it into the specified directory for '.$column."\n";
                $signature .= " * \n";
                if ($noun == 'image') {
                    $signature .= " * Any fImage calls that were added to the column will be processed on the copied image.\n";
                    $signature .= " * \n";
                }
                $signature .= " * @return fActiveRecord  The record object, to allow for method chaining\n";
                $signature .= " */\n";
            }
            $set_method = 'set'.$camelized_column;
            $signature .= 'public function '.$set_method.'()';

            $signatures[$set_method] = $signature;

            $signature = '';
            if ($include_doc_comments) {
                $signature .= "/**\n";
                $signature .= ' * Returns metadata about '.$column."\n";
                $signature .= " * \n";
                $signature .= " * @param  string \$element  The element to return. Must be one of: 'type', 'not_null', 'default', 'valid_values', 'max_length', 'feature', 'directory'.\n";
                $signature .= " * @return mixed  The metadata array or a single element\n";
                $signature .= " */\n";
            }
            $inspect_method = 'inspect'.$camelized_column;
            $signature .= 'public function '.$inspect_method.'($element=NULL)';

            $signatures[$inspect_method] = $signature;
        }
    }

    /**
     * Creates a copy of an uploaded file in the temp directory for the newly cloned record.
     *
     * @internal
     *
     * @param string $class  The class this value is for
     * @param string $column The column the value is in
     * @param mixed  $value  The value
     *
     * @return mixed The cloned fFile object
     */
    public static function replicate($class, $column, $value)
    {
        if (! $value instanceof fFlysystemFile) {
            return $value;
        }

        // If the file we are replicating is in the temp dir, the copy can live there too
        if (strpos($value->getParent()->getPath(), self::TEMP_DIRECTORY.DIRECTORY_SEPARATOR) !== false) {
            $value = clone $value;

            // Otherwise, the copy of the file must be placed in the temp dir so it is properly cleaned up
        } else {
            $upload_dir = self::$file_upload_columns[$class][$column];

            try {
                $temp_dir = new fDirectory($upload_dir->getPath().self::TEMP_DIRECTORY.DIRECTORY_SEPARATOR);
            } catch (fValidationException $e) {
                $temp_dir = fDirectory::create($upload_dir->getPath().self::TEMP_DIRECTORY.DIRECTORY_SEPARATOR);
            }

            $value = $value->duplicate($temp_dir);
        }

        return $value;
    }

    /**
     * Resets the configuration of the class.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$column_inheritence = [];
        self::$fupload_method_calls = [];
        self::$file_upload_columns = [];
        self::$fimage_method_calls = [];
        self::$image_upload_columns = [];
        self::$transaction_level = 0;
    }

    /**
     * Rolls back a transaction, or decreases the level.
     *
     * @internal
     *
     * @return void
     */
    public static function rollback()
    {
        // If the transaction was started by something else, don't even track it
        if (self::$transaction_level == 0) {
            return;
        }

        self::$transaction_level--;

        if (! self::$transaction_level) {
            fFlysystem::rollback();
        }
    }

    /**
     * Copies a file from the filesystem to the file upload directory and sets it as the file for the specified column.
     *
     * This method will perform the fImage calls defined for the column.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     * @param string        $method_name      The method that was called
     * @param array         $parameters       The parameters passed to the method
     *
     * @return fActiveRecord The record object, to allow for method chaining
     */
    public static function set($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        $class = get_class($object);

        [$action, $subject] = fORM::parseMethod($method_name);

        $column = fGrammar::underscorize($subject);
        $doc_root = realpath($_SERVER['DOCUMENT_ROOT']);

        if (! array_key_exists(0, $parameters)) {
            throw new fProgrammerException(
                'The method %s requires exactly one parameter',
                $method_name.'()'
            );
        }

        $file_path = $parameters[0];

        // Handle objects being passed in
        if ($file_path instanceof fFile) {
            $file_path = $file_path->getPath();
        } elseif (is_object($file_path) && is_callable([$file_path, '__toString'])) {
            $file_path = $file_path->__toString();
        } elseif (is_object($file_path)) {
            $file_path = (string) $file_path;
        }

        // if ($file_path !== NULL && $file_path !== '' && $file_path !== FALSE) {
        //  if (!$file_path || (!file_exists($file_path) && !file_exists($doc_root . $file_path))) {
        //      throw new fEnvironmentException(
        //          'The file specified, %s, does not exist. This may indicate a missing enctype="multipart/form-data" attribute in form tag.',
        //          $file_path
        //      );
        //  }
        //
        //  if (!file_exists($file_path) && file_exists($doc_root . $file_path)) {
        //      $file_path = $doc_root . $file_path;
        //  }
        //
        //  if (is_dir($file_path)) {
        //      throw new fProgrammerException(
        //          'The file specified, %s, is not a file but a directory',
        //          $file_path
        //      );
        //  }
        //
        //  $upload_dir = self::$file_upload_columns[$class][$column];
        //
        //  try {
        //      $temp_dir = new fDirectory($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
        //  } catch (fValidationException $e) {
        //      $temp_dir = fDirectory::create($upload_dir->getPath() . self::TEMP_DIRECTORY . DIRECTORY_SEPARATOR);
        //  }
        //
        //  $file     = fFilesystem::createObject($file_path);
        //  $new_file = $file->duplicate($temp_dir);
        //
        // } else {
        //  $new_file = NULL;
        // }
        $new_file = null;
        fActiveRecord::assign($values, $old_values, $column, $file_path);

        // Perform column inheritance
        if (! empty(self::$column_inheritence[$class][$column])) {
            foreach (self::$column_inheritence[$class][$column] as $other_column) {
                self::set($object, $values, $old_values, $related_records, $cache, 'set'.fGrammar::camelize($other_column, true), [$file]);
            }
        }

        if ($new_file) {
            self::processImage($class, $column, $new_file);
        }

        return $object;
    }

    /**
     * Uploads a file.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     * @param string        $method_name      The method that was called
     * @param array         $parameters       The parameters passed to the method
     *
     * @return fDirectory|fFile|null The uploaded file
     */
    public static function upload($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        $class = get_class($object);

        [$action, $subject] = fORM::parseMethod($method_name);
        $column = fGrammar::underscorize($subject);

        $existing_temp_file = false;

        // Try to upload the file putting it in the temp dir incase there is a validation problem with the record
        try {
            $upload_dir = self::$file_upload_columns[$class][$column];
            $temp_dir = self::prepareTempDir(
                $upload_dir
            );

            if (! fUpload::check($column)) {
                throw new fExpectedException('Please upload a file');
            }

            $uploader = self::setUpFUpload($class, $column);

            $temp_file = $uploader->move($temp_dir, $column);

            $file_name = fFlysystem::makeUniqueName($upload_dir.$temp_file->getName());

            $file = fFlysystemFile::create(
                $file_name,
                fopen($temp_file->getPath(), 'r')
            );

            $temp_file->delete();
            // If there was an eror, check to see if we have an existing file
        } catch (fExpectedException $e) {
            // If there is an existing file and none was uploaded, substitute the existing file
            $existing_file = fRequest::get('existing-'.$column);
            $delete_file = fRequest::get('delete-'.$column, 'boolean');
            $no_upload = $e->getMessage() == self::compose('Please upload a file');

            if ($existing_file && $delete_file && $no_upload) {
                $file = null;
            } elseif ($existing_file) {
                $file_path = $upload_dir->getPath().$existing_file;
                $file = fFlysystem::createObject($file_path);

                $current_file = $values[$column];

                // If the existing file is the same as the current file, we can just exit now
                if ($current_file && $file->getPath() == $current_file) {
                    return;
                }

                $existing_temp_file = true;
            } else {
                $file = null;
            }
        }

        // Assign the file
        fActiveRecord::assign($values, $old_values, $column, $file);

        // Perform the file upload inheritance
        if (! empty(self::$column_inheritence[$class][$column])) {
            foreach (self::$column_inheritence[$class][$column] as $other_column) {
                if ($file) {
                    // Image columns will only inherit if it is an fImage object
                    if (! $file instanceof fImage && isset(self::$image_upload_columns[$class]) && array_key_exists($other_column, self::$image_upload_columns[$class])) {
                        continue;
                    }

                    $other_upload_dir = self::$file_upload_columns[$class][$other_column];
                    $other_temp_dir = self::prepareTempDir($other_upload_dir);

                    if ($existing_temp_file) {
                        $other_file = fFilesystem::createObject($other_temp_dir->getPath().$file->getName());
                    } else {
                        $other_file = $file->duplicate($other_temp_dir, false);
                    }
                } else {
                    $other_file = $file;
                }

                fActiveRecord::assign($values, $old_values, $other_column, $other_file);

                if (! $existing_temp_file && $other_file) {
                    self::processImage($class, $other_column, $other_file);
                }
            }
        }

        // Process the file
        if (! $existing_temp_file && $file) {
            self::processImage($class, $column, $file);
        }

        return $file;
    }

    /**
     * Validates uploaded files to ensure they match all of the criteria defined.
     *
     * @internal
     *
     * @param fActiveRecord $object               The fActiveRecord instance
     * @param array         &$values              The current values
     * @param array         &$old_values          The old values
     * @param array         &$related_records     Any records related to this record
     * @param array         &$cache               The cache array for the record
     * @param array         &$validation_messages The existing validation messages
     */
    public static function validate($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages): void
    {
        $class = get_class($object);

        foreach (self::$file_upload_columns[$class] as $column => $directory) {
            $column_name = fORM::getColumnName($class, $column);

            if (isset($validation_messages[$column])) {
                $search_message = self::compose('%sPlease enter a value', fValidationException::formatField($column_name));
                $replace_message = self::compose('%sPlease upload a file', fValidationException::formatField($column_name));
                $validation_messages[$column] = str_replace($search_message, $replace_message, $validation_messages[$column]);
            }

            // Grab the error that occured
            try {
                if (fUpload::check($column)) {
                    $uploader = self::setUpFUpload($class, $column);
                    $uploader->validate($column);
                }
            } catch (fValidationException $e) {
                if ($e->getMessage() != self::compose('Please upload a file')) {
                    $validation_messages[$column] = fValidationException::formatField($column_name).$e->getMessage();
                }
            }
        }
    }

    /**
     * Composes text using fText if loaded.
     *
     * @param string $message   The message to compose
     * @param mixed  $component A string or number to insert into the message
     * @param  mixed   ...
     *
     * @return string The composed and possible translated message
     */
    private static function compose($message)
    {
        $args = array_slice(func_get_args(), 1);

        if (class_exists('fText', false)) {
            return call_user_func_array(
                ['fText', 'compose'],
                [$message, $args]
            );
        }

        return vsprintf($message, $args);
    }

    /**
     * Takes a directory and creates a temporary directory inside of it - if the temporary folder exists, all files older than 6 hours will be deleted.
     *
     * @param string $folder The folder to create a temporary directory inside of
     *
     * @return fDirectory The temporary directory for the folder specified
     */
    private static function prepareTempDir($folder)
    {
        $folder = static::getTempDir()->getPath().$folder.self::TEMP_DIRECTORY.DIRECTORY_SEPARATOR;

        // Let's clean out the upload temp dir
        try {
            $temp_dir = new fDirectory($folder);
        } catch (fValidationException $e) {
            $temp_dir = fDirectory::create($folder);
        }

        $temp_files = $temp_dir->scan();
        foreach ($temp_files as $temp_file) {
            if (filemtime($temp_file->getPath()) < strtotime('-6 hours')) {
                unlink($temp_file->getPath());
            }
        }

        return $temp_dir;
    }

    /**
     * Sets up an fUpload object for a specific column.
     *
     * @param string $class  The class to set up for
     * @param string $column The column to set up for
     *
     * @return fUpload The configured fUpload object
     */
    private static function setUpFUpload($class, $column)
    {
        $upload = new fUpload();

        // Set up the fUpload class
        if (! empty(self::$fupload_method_calls[$class][$column])) {
            foreach (self::$fupload_method_calls[$class][$column] as $method_call) {
                if (! is_callable($upload->{$method_call['method']})) {
                    throw new fProgrammerException(
                        'The fUpload method specified, %s, is not a valid method',
                        $method_call['method'].'()'
                    );
                }
                call_user_func_array($upload->{$method_call['method']}, $method_call['parameters']);
            }
        }

        return $upload;
    }
}

/*
 * Copyright (c) 2008-2010 Will Bond <will@flourishlib.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
