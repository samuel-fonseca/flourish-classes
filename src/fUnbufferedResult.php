<?php
/**
 * Representation of an unbuffered result from a query against the fDatabase class.
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fUnbufferedResult
 *
 * @version    1.0.0b11
 * @changes    1.0.0b11  Fixed some bugs with the mysqli extension and prepared statements [wb, 2010-08-28]
 * @changes    1.0.0b10  Backwards Compatibility Break - removed ODBC support [wb, 2010-07-31]
 * @changes    1.0.0b9   Added IBM DB2 support [wb, 2010-04-13]
 * @changes    1.0.0b8   Added support for prepared statements [wb, 2010-03-02]
 * @changes    1.0.0b7   Fixed a bug with decoding MSSQL national column when using an ODBC connection [wb, 2009-09-18]
 * @changes    1.0.0b6   Added the method ::unescape(), changed ::tossIfNoRows() to return the object for chaining [wb, 2009-08-12]
 * @changes    1.0.0b5   Added the method ::asObjects() to allow for returning objects instead of associative arrays [wb, 2009-06-23]
 * @changes    1.0.0b4   Fixed a bug with not properly converting SQL Server text to UTF-8 [wb, 2009-06-18]
 * @changes    1.0.0b3   Added support for Oracle, various bug fixes [wb, 2009-05-04]
 * @changes    1.0.0b2   Updated for new fCore API [wb, 2009-02-16]
 * @changes    1.0.0b    The initial implementation [wb, 2008-05-07]
 */
class fUnbufferedResult implements Iterator
{
    /**
     * The character set to transcode from for MSSQL queries.
     *
     * @var string
     */
    private $character_set;

    /**
     * The current row of the result set.
     *
     * @var array
     */
    private $current_row;

    /**
     * The database object the result was created from.
     *
     * @var fDatabase
     */
    private $database;

    /**
     * If rows should be converted to objects.
     *
     * @var bool
     */
    private $output_objects = false;

    /**
     * The position of the pointer in the result set.
     *
     * @var int
     */
    private $pointer;

    /**
     * The result resource.
     *
     * @var resource
     */
    private $result;

    /**
     * The SQL query.
     *
     * @var string
     */
    private $sql = '';

    /**
     * Holds the data types for each column to allow for on-the-fly unescaping.
     *
     * @var array
     */
    private $unescape_map = [];

    /**
     * The SQL from before translation.
     *
     * @var string
     */
    private $untranslated_sql;

    /**
     * Configures the result set.
     *
     * @internal
     *
     * @param fDatabase $database      The database object this result was created from
     * @param string    $character_set MSSQL only: the character set to transcode from since MSSQL doesn't do UTF-8
     *
     * @return fUnbufferedResult
     */
    public function __construct($database, $character_set = null)
    {
        if (! $database instanceof fDatabase) {
            throw new fProgrammerException(
                'The database object provided does not appear to be a descendant of fDatabase'
            );
        }

        $this->database = $database;
        $this->character_set = $character_set;
    }

    /**
     * Frees up the result object.
     *
     * @internal
     */
    public function __destruct()
    {
        if (! is_resource($this->result) && ! is_object($this->result)) {
            return;
        }

        // stdClass results are holders for prepared statements, so we don't
        // want to free them since it would break fStatement
        if ($this->result instanceof stdClass) {
            if ($this->database->getExtension() == 'msyqli') {
                $this->result->statement->free_result();
            }
            unset($this->result);

            return;
        }

        switch ($this->database->getExtension()) {
            case 'ibm_db2':
                db2_free_result($this->result);

                break;

            case 'mssql':
                mssql_free_result($this->result);

                break;

            case 'mysql':
                mysql_free_result($this->result);

                break;

            case 'mysqli':
                mysqli_free_result($this->result);

                break;

            case 'oci8':
                oci_free_statement($this->result);

                break;

            case 'pgsql':
                pg_free_result($this->result);

                break;

            case 'sqlite':
                unset($this->result);

                break;

            case 'sqlsrv':
                sqlsrv_free_stmt($this->result);

                break;

            case 'pdo':
                $this->result->closeCursor();

                break;
        }

        $this->result = null;
    }

    /**
     * All requests that hit this method should be requests for callbacks.
     *
     * @internal
     *
     * @param string $method The method to create a callback for
     *
     * @return callable The callback for the method requested
     */
    public function __get($method)
    {
        return [$this, $method];
    }

    /**
     * Sets the object to return rows as objects instead of associative arrays (the default).
     *
     * @return fUnbufferedResult The result object, to allow for method chaining
     */
    public function asObjects()
    {
        $this->output_objects = true;

        return $this;
    }

    /**
     * Returns the current row in the result set (required by iterator interface).
     *
     * @throws fNoRowsException      When the query did not return any rows
     * @throws fNoRemainingException When there are no rows left in the result
     *
     * @internal
     *
     * @return array|stdClass The current row
     */
    public function current()
    {
        $this->validateState();

        // Primes the result set
        if ($this->pointer === null) {
            $this->pointer = 0;
            $this->advanceCurrentRow();
        }

        if (! $this->current_row && $this->pointer == 0) {
            throw new fNoRowsException('The query did not return any rows');
        }
        if (! $this->current_row) {
            throw new fNoRemainingException('There are no remaining rows');
        }

        if ($this->output_objects) {
            return (object) $this->current_row;
        }

        return $this->current_row;
    }

    /**
     * Returns the row next row in the result set (where the pointer is currently assigned to).
     *
     * @throws fNoRowsException      When the query did not return any rows
     * @throws fNoRemainingException When there are no rows left in the result
     *
     * @return array|stdClass The next row in the result
     */
    public function fetchRow()
    {
        $this->validateState();

        $row = $this->current();
        $this->next();

        return $row;
    }

    /**
     * Returns the result.
     *
     * @internal
     *
     * @return mixed The result of the query
     */
    public function getResult()
    {
        $this->validateState();

        return $this->result;
    }

    /**
     * Returns the SQL used in the query.
     *
     * @return string The SQL used in the query
     */
    public function getSQL()
    {
        return $this->sql;
    }

    /**
     * Returns the SQL as it was before translation.
     *
     * @return string The SQL from before translation
     */
    public function getUntranslatedSQL()
    {
        return $this->untranslated_sql;
    }

    /**
     * Returns the current row number (required by iterator interface).
     *
     * @throws fNoRowsException      When the query did not return any rows
     * @throws fNoRemainingException When there are no rows left in the result
     *
     * @internal
     *
     * @return int The current row number
     */
    public function key()
    {
        $this->validateState();

        if ($this->pointer === null) {
            $this->current();
        }

        return $this->pointer;
    }

    /**
     * Advances to the next row in the result (required by iterator interface).
     *
     * @throws fNoRowsException      When the query did not return any rows
     * @throws fNoRemainingException When there are no rows left in the result
     *
     * @internal
     */
    public function next()
    {
        $this->validateState();

        if ($this->pointer === null) {
            $this->current();
        }

        $this->advanceCurrentRow();
        $this->pointer++;
    }

    /**
     * Rewinds the query (required by iterator interface).
     *
     * @internal
     */
    public function rewind()
    {
        $this->validateState();

        if (! empty($this->pointer)) {
            throw new fProgrammerException(
                'Unbuffered database results can not be iterated through multiple times'
            );
        }
    }

    /**
     * Sets the result from the query.
     *
     * @internal
     *
     * @param mixed $result The result from the query
     */
    public function setResult($result): void
    {
        $this->result = $result;
    }

    /**
     * Sets the SQL used in the query.
     *
     * @internal
     *
     * @param string $sql The SQL used in the query
     */
    public function setSQL($sql): void
    {
        $this->sql = $sql;
    }

    /**
     * Sets the SQL from before translation.
     *
     * @internal
     *
     * @param string $untranslated_sql The SQL from before translation
     */
    public function setUntranslatedSQL($untranslated_sql): void
    {
        $this->untranslated_sql = $untranslated_sql;
    }

    /**
     * Throws an fNoResultException if the query did not return any rows.
     *
     * @param string $message The message to use for the exception if there are no rows in this result set
     *
     * @throws fNoRowsException When the query did not return any rows
     *
     * @return fUnbufferedResult The result object, to allow for method chaining
     */
    public function tossIfNoRows($message = null)
    {
        try {
            $this->current();
        } catch (fNoRowsException $e) {
            if ($message !== null) {
                $e->setMessage($message);
            }

            throw $e;
        }

        return $this;
    }

    /**
     * Sets the result object to unescape all values as they are retrieved from the object.
     *
     * The data types should be from the list of types supported by
     * fDatabase::unescape().
     *
     * @param array $column_data_type_map An associative array with column names as the keys and the data types as the values
     *
     * @return fUnbufferedResult The result object, to allow for method chaining
     */
    public function unescape($column_data_type_map)
    {
        if (! is_array($column_data_type_map)) {
            throw new fProgrammerException(
                'The column to data type map specified, %s, does not appear to be an array',
                $column_data_type_map
            );
        }

        $this->unescape_map = $column_data_type_map;

        return $this;
    }

    /**
     * Returns if the query has any rows left.
     *
     * @return bool If the iterator is still valid
     */
    public function valid()
    {
        $this->validateState();

        if ($this->pointer === null) {
            $this->advanceCurrentRow();
            $this->pointer = 0;
        }

        return ! empty($this->current_row);
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
    protected static function compose($message)
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
     * Gets the next row from the result and assigns it to the current row.
     */
    private function advanceCurrentRow(): void
    {
        $type = $this->database->getType();
        $extension = $this->database->getExtension();

        switch ($extension) {
            case 'ibm_db2':
                $row = db2_fetch_assoc($this->result);

                break;

            case 'mssql':
                // For some reason the mssql extension will return an empty row even
                // when now rows were returned, so we have to explicitly check for this
                if ($this->pointer == 0 && ! mssql_num_rows($this->result)) {
                    $row = false;
                } else {
                    $row = mssql_fetch_assoc($this->result);
                    if (empty($row)) {
                        mssql_fetch_batch($this->result);
                        $row = mssql_fetch_assoc($this->result);
                    }
                    if (! empty($row)) {
                        $row = $this->fixDblibMSSQLDriver($row);
                    }
                }

                break;

            case 'mysql':
                $row = mysql_fetch_assoc($this->result);

                break;

            case 'mysqli':
                if (! $this->result instanceof stdClass) {
                    $row = mysqli_fetch_assoc($this->result);
                } else {
                    $meta = $this->result->statement->result_metadata();
                    $row_references = [];
                    while ($field = $meta->fetch_field()) {
                        $row_references[] = &$fetched_row[$field->name];
                    }

                    call_user_func_array([$this->result->statement, 'bind_result'], $row_references);
                    $this->result->statement->fetch();

                    $row = [];
                    foreach ($fetched_row as $key => $val) {
                        $row[$key] = $val;
                    }
                    unset($row_references);
                    $meta->free_result();
                }

                break;

            case 'oci8':
                $row = oci_fetch_assoc($this->result);

                break;

            case 'pgsql':
                $row = pg_fetch_assoc($this->result);

                break;

            case 'sqlite':
                $row = sqlite_fetch_array($this->result, SQLITE_ASSOC);

                break;

            case 'sqlsrv':
                $resource = $this->result instanceof stdClass ? $this->result->statement : $this->result;
                $row = sqlsrv_fetch_array($resource, SQLSRV_FETCH_ASSOC);

                break;

            case 'pdo':
                $row = $this->result->fetch(PDO::FETCH_ASSOC);

                break;
        }

        // Fix uppercase column names to lowercase
        if ($row && ($type == 'oracle' || ($type == 'db2' && $extension != 'ibm_db2'))) {
            $new_row = [];
            foreach ($row as $column => $value) {
                $new_row[strtolower($column)] = $value;
            }
            $row = $new_row;
        }

        // This is an unfortunate fix that required for databases that don't support limit
        // clauses with an offset. It prevents unrequested columns from being returned.
        if ($row && in_array($type, ['mssql', 'oracle', 'db2'])) {
            if ($this->untranslated_sql !== null && isset($row['flourish__row__num'])) {
                unset($row['flourish__row__num']);
            }
        }

        // This decodes the data coming out of MSSQL into UTF-8
        if ($row && $type == 'mssql') {
            if ($this->character_set) {
                foreach ($row as $key => $value) {
                    if (! is_string($value) || strpos($key, '__flourish_mssqln_') === 0 || isset($row['fmssqln__'.$key]) || preg_match('#[\x0-\x8\xB\xC\xE-\x1F]#', $value)) {
                        continue;
                    }
                    $row[$key] = iconv($this->character_set, 'UTF-8', $value);
                }
            }
            $row = $this->decodeMSSQLNationalColumns($row);
        }

        if ($this->unescape_map) {
            foreach ($this->unescape_map as $column => $type) {
                if (! isset($row[$column])) {
                    continue;
                }
                $row[$column] = $this->database->unescape($type, $row[$column]);
            }
        }

        $this->current_row = $row;
    }

    /**
     * Decodes national (unicode) character data coming out of MSSQL into UTF-8.
     *
     * @param array $row The row from the database
     *
     * @return array The fixed row
     */
    private function decodeMSSQLNationalColumns($row)
    {
        if (strpos($this->sql, 'fmssqln__') === false) {
            return $row;
        }

        $columns = array_keys($row);

        foreach ($columns as $column) {
            if (substr($column, 0, 9) != 'fmssqln__') {
                continue;
            }

            $real_column = substr($column, 9);

            $row[$real_column] = iconv('ucs-2le', 'utf-8', $this->database->unescape('blob', $row[$column]));
            unset($row[$column]);
        }

        return $row;
    }

    /**
     * Warns the user about bugs in the DBLib driver for MSSQL, fixes some bugs.
     *
     * @param array $row The row from the database
     *
     * @return array The fixed row
     */
    private function fixDblibMSSQLDriver($row)
    {
        static $using_dblib = null;

        if ($using_dblib === null) {
            // If it is not a windows box we are definitely not using dblib
            if (! fCore::checkOS('windows')) {
                $using_dblib = false;

            // Check this windows box for dblib
            } else {
                ob_start();
                phpinfo(INFO_MODULES);
                $module_info = ob_get_contents();
                ob_end_clean();

                $using_dblib = ! preg_match('#FreeTDS#ims', $module_info, $match);
            }
        }

        if (! $using_dblib) {
            return $row;
        }

        foreach ($row as $key => $value) {
            if ($value == ' ') {
                $row[$key] = '';
                trigger_error(
                    self::compose(
                        'A single space was detected coming out of the database and was converted into an empty string - see %s for more information',
                        'http://bugs.php.net/bug.php?id=26315'
                    ),
                    E_USER_NOTICE
                );
            }
            if (strlen($key) == 30) {
                trigger_error(
                    self::compose(
                        'A column name exactly 30 characters in length was detected coming out of the database - this column name may be truncated, see %s for more information.',
                        'http://bugs.php.net/bug.php?id=23990'
                    ),
                    E_USER_NOTICE
                );
            }
            if (strlen($value) == 256) {
                trigger_error(
                    self::compose(
                        'A value exactly 255 characters in length was detected coming out of the database - this value may be truncated, see %s for more information.',
                        'http://bugs.php.net/bug.php?id=37757'
                    ),
                    E_USER_NOTICE
                );
            }
        }

        return $row;
    }

    /**
     * Throws an exception if this object has been deconstructed already.
     */
    private function validateState(): void
    {
        if ($this->result === null) {
            throw new fProgrammerException('This unbuffered result has been fully fetched, or replaced by a newer result');
        }
    }
}

/*
 * Copyright (c) 2007-2010 Will Bond <will@flourishlib.com>
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
