<?php
/**
 * Provides special column functionality for fActiveRecord classes.
 *
 * @copyright  Copyright (c) 2008-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fORMColumn
 *
 * @version    1.0.0b14
 * @changes    1.0.0b14  Updated code to work with the new fORM API [wb, 2010-08-06]
 * @changes    1.0.0b13  Fixed ::reflect() to include some missing parameters [wb, 2010-06-08]
 * @changes    1.0.0b12  Changed validation messages array to use column name keys [wb, 2010-05-26]
 * @changes    1.0.0b11  Fixed a bug with ::prepareLinkColumn() returning `http://` for empty link columns and not adding `http://` to links that contained a /, but did not start with it [wb, 2010-03-16]
 * @changes    1.0.0b10  Fixed ::reflect() to specify the value returned from `set` and `generate` methods, changed ::generate() methods to return the newly generated string [wb, 2010-03-15]
 * @changes    1.0.0b9   Changed email columns to be automatically trimmed if they are a value email address surrounded by whitespace [wb, 2010-03-14]
 * @changes    1.0.0b8   Made the validation on link columns a bit more strict [wb, 2010-03-09]
 * @changes    1.0.0b7   Updated code for the new fORMDatabase and fORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b6   Changed SQL statements to use value placeholders, identifier escaping and schema support [wb, 2009-10-22]
 * @changes    1.0.0b5   Updated to use new fORM::registerInspectCallback() method [wb, 2009-07-13]
 * @changes    1.0.0b4   Updated code for new fORM API [wb, 2009-06-15]
 * @changes    1.0.0b3   Updated code to use new fValidationException::formatField() method [wb, 2009-06-04]
 * @changes    1.0.0b2   Fixed a bug with objectifying number columns [wb, 2008-11-24]
 * @changes    1.0.0b    The initial implementation [wb, 2008-05-27]
 */
class fORMColumn
{
    // The following constants allow for nice looking callbacks to static methods
    public const configureEmailColumn = 'fORMColumn::configureEmailColumn';

    public const configureLinkColumn = 'fORMColumn::configureLinkColumn';

    public const configureNumberColumn = 'fORMColumn::configureNumberColumn';

    public const configureRandomColumn = 'fORMColumn::configureRandomColumn';

    public const encodeNumberColumn = 'fORMColumn::encodeNumberColumn';

    public const inspect = 'fORMColumn::inspect';

    public const generate = 'fORMColumn::generate';

    public const objectifyNumber = 'fORMColumn::objectifyNumber';

    public const prepareLinkColumn = 'fORMColumn::prepareLinkColumn';

    public const prepareNumberColumn = 'fORMColumn::prepareNumberColumn';

    public const reflect = 'fORMColumn::reflect';

    public const reset = 'fORMColumn::reset';

    public const setEmailColumn = 'fORMColumn::setEmailColumn';

    public const setRandomStrings = 'fORMColumn::setRandomStrings';

    public const validateEmailColumns = 'fORMColumn::validateEmailColumns';

    public const validateLinkColumns = 'fORMColumn::validateLinkColumns';

    /**
     * Columns that should be formatted as email addresses.
     *
     * @var array
     */
    private static $email_columns = [];

    /**
     * Columns that should be formatted as links.
     *
     * @var array
     */
    private static $link_columns = [];

    /**
     * Columns that should be returned as fNumber objects.
     *
     * @var array
     */
    private static $number_columns = [];

    /**
     * Columns that should be formatted as a random string.
     *
     * @var array
     */
    private static $random_columns = [];

    /**
     * Forces use as a static class.
     *
     * @return fORMColumn
     */
    private function __construct()
    {
    }

    /**
     * Sets a column to be formatted as an email address.
     *
     * @param mixed  $class  The class name or instance of the class to set the column format
     * @param string $column The column to format as an email address
     */
    public static function configureEmailColumn($class, $column): void
    {
        $class = fORM::getClass($class);
        $table = fORM::tablize($class);
        $schema = fORMSchema::retrieve($class);
        $data_type = $schema->getColumnInfo($table, $column, 'type');

        $valid_data_types = ['varchar', 'char', 'text'];
        if (! in_array($data_type, $valid_data_types)) {
            throw new fProgrammerException(
                'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as an email column.',
                $column,
                $data_type,
                implode(', ', $valid_data_types)
            );
        }

        fORM::registerActiveRecordMethod(
            $class,
            'set'.fGrammar::camelize($column, true),
            self::setEmailColumn
        );

        if (! fORM::checkHookCallback($class, 'post::validate()', self::validateEmailColumns)) {
            fORM::registerHookCallback($class, 'post::validate()', self::validateEmailColumns);
        }

        fORM::registerInspectCallback($class, $column, self::inspect);

        if (empty(self::$email_columns[$class])) {
            self::$email_columns[$class] = [];
        }

        self::$email_columns[$class][$column] = true;
    }

    /**
     * Sets a column to be formatted as a link.
     *
     * @param mixed  $class  The class name or instance of the class to set the column format
     * @param string $column The column to format as a link
     */
    public static function configureLinkColumn($class, $column): void
    {
        $class = fORM::getClass($class);
        $table = fORM::tablize($class);
        $schema = fORMSchema::retrieve($class);
        $data_type = $schema->getColumnInfo($table, $column, 'type');

        $valid_data_types = ['varchar', 'char', 'text'];
        if (! in_array($data_type, $valid_data_types)) {
            throw new fProgrammerException(
                'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a link column.',
                $column,
                $data_type,
                implode(', ', $valid_data_types)
            );
        }

        fORM::registerActiveRecordMethod(
            $class,
            'prepare'.fGrammar::camelize($column, true),
            self::prepareLinkColumn
        );

        if (! fORM::checkHookCallback($class, 'post::validate()', self::validateLinkColumns)) {
            fORM::registerHookCallback($class, 'post::validate()', self::validateLinkColumns);
        }

        fORM::registerReflectCallback($class, self::reflect);
        fORM::registerInspectCallback($class, $column, self::inspect);

        if (empty(self::$link_columns[$class])) {
            self::$link_columns[$class] = [];
        }

        self::$link_columns[$class][$column] = true;
    }

    /**
     * Sets a column to be returned as an fNumber object from calls to `get{ColumnName}()`.
     *
     * @param mixed  $class  The class name or instance of the class to set the column format
     * @param string $column The column to return as an fNumber object
     */
    public static function configureNumberColumn($class, $column): void
    {
        $class = fORM::getClass($class);
        $table = fORM::tablize($class);
        $schema = fORMSchema::retrieve($class);
        $data_type = $schema->getColumnInfo($table, $column, 'type');

        $valid_data_types = ['integer', 'float'];
        if (! in_array($data_type, $valid_data_types)) {
            throw new fProgrammerException(
                'The column specified, %1$s, is a %2$s column. Must be %3$s to be set as a number column.',
                $column,
                $data_type,
                implode(', ', $valid_data_types)
            );
        }

        $camelized_column = fGrammar::camelize($column, true);

        fORM::registerActiveRecordMethod(
            $class,
            'encode'.$camelized_column,
            self::encodeNumberColumn
        );

        fORM::registerActiveRecordMethod(
            $class,
            'prepare'.$camelized_column,
            self::prepareNumberColumn
        );

        fORM::registerReflectCallback($class, self::reflect);
        fORM::registerInspectCallback($class, $column, self::inspect);
        fORM::registerObjectifyCallback($class, $column, self::objectifyNumber);

        if (empty(self::$number_columns[$class])) {
            self::$number_columns[$class] = [];
        }

        self::$number_columns[$class][$column] = true;
    }

    /**
     * Sets a column to be a random string column - a random string will be generated when the record is saved.
     *
     * @param mixed  $class  The class name or instance of the class
     * @param string $column The column to set as a random column
     * @param string $type   The type of random string, must be one of: `'alphanumeric'`, `'alpha'`, `'numeric'`, `'hexadecimal'`
     * @param int    $length The length of the random string
     */
    public static function configureRandomColumn($class, $column, $type, $length): void
    {
        $class = fORM::getClass($class);
        $table = fORM::tablize($class);
        $schema = fORMSchema::retrieve($class);
        $data_type = $schema->getColumnInfo($table, $column, 'type');

        $valid_data_types = ['varchar', 'char', 'text'];
        if (! in_array($data_type, $valid_data_types)) {
            throw new fProgrammerException(
                'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a random string column.',
                $column,
                $data_type,
                implode(', ', $valid_data_types)
            );
        }

        $valid_types = ['alphanumeric', 'alpha', 'numeric', 'hexadecimal'];
        if (! in_array($type, $valid_types)) {
            throw new fProgrammerException(
                'The type specified, %1$s, is an invalid type. Must be one of: %2$s.',
                $type,
                implode(', ', $valid_types)
            );
        }

        if (! is_numeric($length) || $length < 1) {
            throw new fProgrammerException(
                'The length specified, %s, needs to be an integer greater than zero.',
                $length
            );
        }

        fORM::registerActiveRecordMethod(
            $class,
            'generate'.fGrammar::camelize($column, true),
            self::generate
        );

        if (! fORM::checkHookCallback($class, 'pre::validate()', self::setRandomStrings)) {
            fORM::registerHookCallback($class, 'pre::validate()', self::setRandomStrings);
        }

        fORM::registerInspectCallback($class, $column, self::inspect);

        if (empty(self::$random_columns[$class])) {
            self::$random_columns[$class] = [];
        }

        self::$random_columns[$class][$column] = ['type' => $type, 'length' => (int) $length];
    }

    /**
     * Encodes a number column by calling fNumber::__toString().
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
     * @return string The encoded number
     */
    public static function encodeNumberColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        [$action, $subject] = fORM::parseMethod($method_name);

        $column = fGrammar::underscorize($subject);
        $class = get_class($object);
        $schema = fORMSchema::retrieve($class);
        $table = fORM::tablize($class);
        $column_info = $schema->getColumnInfo($table, $column);
        $value = $values[$column];

        if ($value instanceof fNumber) {
            if ($column_info['type'] == 'float') {
                $decimal_places = (isset($parameters[0])) ? (int) $parameters[0] : $column_info['decimal_places'];
                $value = $value->trunc($decimal_places)->__toString();
            } else {
                $value = $value->__toString();
            }
        }

        return fHTML::prepare($value);
    }

    /**
     * Generates a new random value for the column.
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
     * @return string The newly generated random value
     */
    public static function generate($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        [$action, $subject] = fORM::parseMethod($method_name);

        $column = fGrammar::underscorize($subject);
        $class = get_class($object);
        $table = fORM::tablize($class);

        $schema = fORMSchema::retrieve($class);
        $db = fORMDatabase::retrieve($class, 'read');

        $settings = self::$random_columns[$class][$column];

        // Check to see if this is a unique column
        $unique_keys = $schema->getKeys($table, 'unique');
        $is_unique_column = false;
        foreach ($unique_keys as $unique_key) {
            if ($unique_key == [$column]) {
                $is_unique_column = true;
                $sql = 'SELECT %r FROM %r WHERE %r = %s';
                do {
                    $value = fCryptography::randomString($settings['length'], $settings['type']);
                } while ($db->query($sql, $column, $table, $column, $value)->countReturnedRows());
            }
        }

        // If is is not a unique column, just generate a value
        if (! $is_unique_column) {
            $value = fCryptography::randomString($settings['length'], $settings['type']);
        }

        fActiveRecord::assign($values, $old_values, $column, $value);

        return $value;
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
        if (! empty(self::$email_columns[$class][$column])) {
            $metadata['feature'] = 'email';
        }

        if (! empty(self::$link_columns[$class][$column])) {
            $metadata['feature'] = 'link';
        }

        if (! empty(self::$random_columns[$class][$column])) {
            $metadata['feature'] = 'random';
        }

        if (! empty(self::$number_columns[$class][$column])) {
            $metadata['feature'] = 'number';
        }
    }

    /**
     * Turns a numeric value into an fNumber object.
     *
     * @internal
     *
     * @param string $class  The class this value is for
     * @param string $column The column the value is in
     * @param mixed  $value  The value
     *
     * @return mixed The fNumber object or raw value
     */
    public static function objectifyNumber($class, $column, $value)
    {
        if ((! is_string($value) && ! is_numeric($value) && ! is_object($value)) || ! strlen(trim($value))) {
            return $value;
        }

        try {
            return new fNumber($value);
            // If there was some error creating the number object, just return the raw value
        } catch (fExpectedException $e) {
            return $value;
        }
    }

    /**
     * Prepares a link column so that the link will work properly in an `a` tag.
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
     * @return string The formatted link
     */
    public static function prepareLinkColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        [$action, $subject] = fORM::parseMethod($method_name);

        $column = fGrammar::underscorize($subject);
        $value = $values[$column];

        // Fix domains that don't have the protocol to start
        if (strlen($value) && ! preg_match('#^https?://|^/#iD', $value)) {
            $value = 'http://'.$value;
        }

        $value = fHTML::prepare($value);

        if (isset($parameters[0]) && $parameters[0] === true) {
            return '<a href="'.$value.'">'.$value.'</a>';
        }

        return $value;
    }

    /**
     * Prepares a number column by calling fNumber::format().
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
     * @return string The formatted link
     */
    public static function prepareNumberColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        [$action, $subject] = fORM::parseMethod($method_name);

        $column = fGrammar::underscorize($subject);
        $class = get_class($object);
        $table = fORM::tablize($class);
        $schema = fORMSchema::retrieve($class);
        $column_info = $schema->getColumnInfo($table, $column);
        $value = $values[$column];

        if ($value instanceof fNumber) {
            if ($column_info['type'] == 'float') {
                $decimal_places = (isset($parameters[0])) ? (int) $parameters[0] : $column_info['decimal_places'];
                if ($decimal_places !== null) {
                    $value = $value->trunc($decimal_places)->format();
                } else {
                    $value = $value->format();
                }
            } else {
                $value = $value->format();
            }
        }

        return fHTML::prepare($value);
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
        if (isset(self::$link_columns[$class])) {
            foreach (self::$link_columns[$class] as $column => $enabled) {
                $signature = '';
                if ($include_doc_comments) {
                    $signature .= "/**\n";
                    $signature .= ' * Prepares the value of '.$column." for output into HTML\n";
                    $signature .= " * \n";
                    $signature .= " * This method will ensure all links that start with a domain name are preceeded by http://\n";
                    $signature .= " * \n";
                    $signature .= " * @param  boolean \$create_link  Will cause link to be automatically converted into an [a] tag\n";
                    $signature .= " * @return string  The HTML-ready value\n";
                    $signature .= " */\n";
                }
                $prepare_method = 'prepare'.fGrammar::camelize($column, true);
                $signature .= 'public function '.$prepare_method.'($create_link=FALSE)';

                $signatures[$prepare_method] = $signature;
            }
        }

        if (isset(self::$number_columns[$class])) {
            $table = fORM::tablize($class);
            $schema = fORMSchema::retrieve($class);

            foreach (self::$number_columns[$class] as $column => $enabled) {
                $camelized_column = fGrammar::camelize($column, true);
                $type = $schema->getColumnInfo($table, $column, 'type');

                // Get and set methods
                $signature = '';
                if ($include_doc_comments) {
                    $signature .= "/**\n";
                    $signature .= ' * Gets the current value of '.$column."\n";
                    $signature .= " * \n";
                    $signature .= " * @return fNumber  The current value\n";
                    $signature .= " */\n";
                }
                $get_method = 'get'.$camelized_column;
                $signature .= 'public function '.$get_method.'()';

                $signatures[$get_method] = $signature;

                $signature = '';
                if ($include_doc_comments) {
                    $signature .= "/**\n";
                    $signature .= ' * Sets the value for '.$column."\n";
                    $signature .= " * \n";
                    $signature .= ' * @param  fNumber|string|integer $'.$column."  The new value - don't use floats since they are imprecise\n";
                    $signature .= " * @return fActiveRecord  The record object, to allow for method chaining\n";
                    $signature .= " */\n";
                }
                $set_method = 'set'.$camelized_column;
                $signature .= 'public function '.$set_method.'($'.$column.')';

                $signatures[$set_method] = $signature;

                $signature = '';
                if ($include_doc_comments) {
                    $signature .= "/**\n";
                    $signature .= ' * Encodes the value of '.$column." for output into an HTML form\n";
                    $signature .= " * \n";
                    $signature .= " * If the value is an fNumber object, the ->__toString() method will be called\n";
                    $signature .= " * resulting in the value without any thousands separators\n";
                    $signature .= " * \n";
                    if ($type == 'float') {
                        $signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n";
                    }
                    $signature .= " * @return string  The HTML form-ready value\n";
                    $signature .= " */\n";
                }
                $encode_method = 'encode'.$camelized_column;
                $signature .= 'public function '.$encode_method.'(';
                if ($type == 'float') {
                    $signature .= '$decimal_places=NULL';
                }
                $signature .= ')';

                $signatures[$encode_method] = $signature;

                $signature = '';
                if ($include_doc_comments) {
                    $signature .= "/**\n";
                    $signature .= ' * Prepares the value of '.$column." for output into HTML\n";
                    $signature .= " * \n";
                    $signature .= " * If the value is an fNumber object, the ->format() method will be called\n";
                    $signature .= " * resulting in the value including thousands separators\n";
                    $signature .= " * \n";
                    if ($type == 'float') {
                        $signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n";
                    }
                    $signature .= " * @return string  The HTML-ready value\n";
                    $signature .= " */\n";
                }
                $prepare_method = 'prepare'.$camelized_column;
                $signature .= 'public function '.$prepare_method.'(';
                if ($type == 'float') {
                    $signature .= '$decimal_places=NULL';
                }
                $signature .= ')';

                $signatures[$prepare_method] = $signature;
            }
        }

        if (isset(self::$random_columns[$class])) {
            foreach (self::$random_columns[$class] as $column => $settings) {
                $signature = '';
                if ($include_doc_comments) {
                    $signature .= "/**\n";
                    $signature .= ' * Generates a new random '.$settings['type'].' character '.$settings['type'].' string for '.$column."\n";
                    $signature .= " * \n";
                    $signature .= " * If there is a UNIQUE constraint on the column and the value is not unique it will be regenerated until unique\n";
                    $signature .= " * \n";
                    $signature .= " * @return string  The randomly generated string\n";
                    $signature .= " */\n";
                }
                $generate_method = 'generate'.fGrammar::camelize($column, true);
                $signature .= 'public function '.$generate_method.'()';

                $signatures[$generate_method] = $signature;
            }
        }
    }

    /**
     * Resets the configuration of the class.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$email_columns = [];
        self::$link_columns = [];
        self::$number_columns = [];
        self::$random_columns = [];
    }

    /**
     * Sets the value for an email column, trimming the value if it is a valid email.
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
    public static function setEmailColumn($object, &$values, &$old_values, &$related_records, &$cache, $method_name, $parameters)
    {
        [$action, $subject] = fORM::parseMethod($method_name);

        $column = fGrammar::underscorize($subject);
        $class = get_class($object);

        if (count($parameters) < 1) {
            throw new fProgrammerException(
                'The method, %s(), requires at least one parameter',
                $method_name
            );
        }

        $email = $parameters[0] ?? '';

        if (! empty($email) && preg_match('#^\s*[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}\s*$#iD', $email)) {
            $email = trim($email);
        }

        fActiveRecord::assign($values, $old_values, $column, $email);

        return $object;
    }

    /**
     * Sets the appropriate column values to a random string if the object is new.
     *
     * @internal
     *
     * @param fActiveRecord $object           The fActiveRecord instance
     * @param array         &$values          The current values
     * @param array         &$old_values      The old values
     * @param array         &$related_records Any records related to this record
     * @param array         &$cache           The cache array for the record
     *
     * @return void The formatted link
     */
    public static function setRandomStrings($object, &$values, &$old_values, &$related_records, &$cache): void
    {
        if ($object->exists()) {
            return;
        }

        $class = get_class($object);
        $table = fORM::tablize($class);

        foreach (self::$random_columns[$class] as $column => $settings) {
            if (fActiveRecord::hasOld($old_values, $column) && $values[$column]) {
                continue;
            }
            self::generate(
                $object,
                $values,
                $old_values,
                $related_records,
                $cache,
                'generate'.fGrammar::camelize($column, true),
                []
            );
        }
    }

    /**
     * Validates all email columns.
     *
     * @internal
     *
     * @param fActiveRecord $object               The fActiveRecord instance
     * @param array         &$values              The current values
     * @param array         &$old_values          The old values
     * @param array         &$related_records     Any records related to this record
     * @param array         &$cache               The cache array for the record
     * @param array         &$validation_messages An array of ordered validation messages
     *
     * @return void
     */
    public static function validateEmailColumns($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
    {
        $class = get_class($object);

        if (empty(self::$email_columns[$class])) {
            return;
        }

        foreach (self::$email_columns[$class] as $column => $enabled) {
            if ($values[$column] === null || ! strlen($values[$column])) {
                continue;
            }
            if (! preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#iD', $values[$column])) {
                $validation_messages[$column] = self::compose(
                    '%sPlease enter an email address in the form name@example.com',
                    fValidationException::formatField(fORM::getColumnName($class, $column))
                );
            }
        }
    }

    /**
     * Validates all link columns.
     *
     * @internal
     *
     * @param fActiveRecord $object               The fActiveRecord instance
     * @param array         &$values              The current values
     * @param array         &$old_values          The old values
     * @param array         &$related_records     Any records related to this record
     * @param array         &$cache               The cache array for the record
     * @param array         &$validation_messages An array of ordered validation messages
     *
     * @return void
     */
    public static function validateLinkColumns($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
    {
        $class = get_class($object);

        if (empty(self::$link_columns[$class])) {
            return;
        }

        foreach (self::$link_columns[$class] as $column => $enabled) {
            if (! strlen($values[$column] ?? '')) {
                continue;
            }

            $ip_regex = '(?:(?:[01]?\d?\d|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d?\d|2[0-4]\d|25[0-5])';
            $hostname_regex = '[a-z]+(?:[a-z0-9\-]*[a-z0-9]\.?|\.)*';
            $domain_regex = '([a-z]+([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}';
            if (! preg_match('#^(https?://('.$ip_regex.'|'.$hostname_regex.')(?=/|$)|'.$domain_regex.'(?=/|$)|/)#i', $values[$column])) {
                $validation_messages[$column] = self::compose(
                    '%sPlease enter a link in the form http://www.example.com',
                    fValidationException::formatField(fORM::getColumnName($class, $column))
                );
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
