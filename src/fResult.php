<?php
/**
 * Representation of a result from a query against the fDatabase class.
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fResult
 *
 * @version    1.0.0b11
 * @changes    1.0.0b11  Backwards Compatibility Break - removed ODBC support [wb, 2010-07-31]
 * @changes    1.0.0b10  Added IBM DB2 support [wb, 2010-04-13]
 * @changes    1.0.0b9   Added support for prepared statements [wb, 2010-03-02]
 * @changes    1.0.0b8   Fixed a bug with decoding MSSQL national column when using an ODBC connection [wb, 2009-09-18]
 * @changes    1.0.0b7   Added the method ::unescape(), changed ::tossIfNoRows() to return the object for chaining [wb, 2009-08-12]
 * @changes    1.0.0b6   Fixed a bug where ::fetchAllRows() would throw a fNoRowsException [wb, 2009-06-30]
 * @changes    1.0.0b5   Added the method ::asObjects() to allow for returning objects instead of associative arrays [wb, 2009-06-23]
 * @changes    1.0.0b4   Fixed a bug with not properly converting SQL Server text to UTF-8 [wb, 2009-06-18]
 * @changes    1.0.0b3   Added support for Oracle, various bug fixes [wb, 2009-05-04]
 * @changes    1.0.0b2   Updated for new fCore API [wb, 2009-02-16]
 * @changes    1.0.0b    The initial implementation [wb, 2007-09-25]
 */
class fResult implements Iterator
{
    /**
     * The number of rows affected by an `INSERT`, `UPDATE`, `DELETE`, etc.
     *
     * @var int
     */
    private $affected_rows = 0;

    /**
     * The auto incremented value from the query.
     *
     * @var int
     */
    private $auto_incremented_value;

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
     * The database object this result was created from.
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
     * The result resource or array.
     *
     * @var mixed
     */
    private $result;

    /**
     * The number of rows returned by a select.
     *
     * @var int
     */
    private $returned_rows = 0;

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
     * The SQL from before translation - only applicable to translated queries.
     *
     * @var string
     */
    private $untranslated_sql;

    /**
     * Configures the result set.
     *
     * @internal
     *
     * @param fDatabase $database      The database object this result set was created from
     * @param string    $character_set MSSQL only: the character set to transcode from since MSSQL doesn't do UTF-8
     *
     * @return fResult
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
     * Frees up the result object to save memory.
     *
     * @internal
     */
    public function __destruct()
    {
        if (! is_resource($this->result) && ! is_object($this->result)) {
            return;
        }

        switch ($this->database->getExtension()) {
            case 'mssql':
                mssql_free_result($this->result);

                break;

            case 'mysql':
                mysql_free_result($this->result);

                break;

            case 'mysqli':
                if (is_resource($this->result)) {
                    mysqli_free_result($this->result);
                }

                break;

            case 'pgsql':
                pg_free_result($this->result);

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
     * @return fResult The result object, to allow for method chaining
     */
    public function asObjects()
    {
        $this->output_objects = true;

        return $this;
    }

    /**
     * Returns the number of rows affected by the query.
     *
     * @return int The number of rows affected by the query
     */
    public function countAffectedRows()
    {
        return $this->affected_rows;
    }

    /**
     * Returns the number of rows returned by the query.
     *
     * @return int The number of rows returned by the query
     */
    public function countReturnedRows()
    {
        return $this->returned_rows;
    }

    /**
     * Returns the current row in the result set (required by iterator interface).
     *
     * @throws fNoRowsException      When the query did not return any rows
     * @throws fNoRemainingException When there are no remaining rows in the result
     *
     * @internal
     *
     * @return array|stdClass The current row
     */
    public function current(): mixed
    {
        if (! $this->returned_rows) {
            throw new fNoRowsException('The query did not return any rows');
        }

        if (! $this->valid()) {
            throw new fNoRemainingException('There are no remaining rows');
        }

        // Primes the result set
        if ($this->pointer === null) {
            $this->pointer = 0;
            $this->advanceCurrentRow();
        }

        if ($this->output_objects) {
            return (object) $this->current_row;
        }

        return $this->current_row;
    }

    /**
     * Returns all of the rows from the result set.
     *
     * @return array The array of rows
     */
    public function fetchAllRows()
    {
        $all_rows = [];
        foreach ($this as $row) {
            $all_rows[] = $row;
        }

        return $all_rows;
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
        $row = $this->current();
        $this->next();

        return $row;
    }

    /**
     * Wraps around ::fetchRow() and returns the first field from the row instead of the whole row.
     *
     * @throws fNoRowsException      When the query did not return any rows
     * @throws fNoRemainingException When there are no rows left in the result
     *
     * @return bool|number|string The first scalar value from ::fetchRow()
     */
    public function fetchScalar()
    {
        $row = $this->fetchRow();

        return array_shift($row);
    }

    /**
     * Returns the last auto incremented value for this database connection. This may or may not be from the current query.
     *
     * @return int The auto incremented value
     */
    public function getAutoIncrementedValue()
    {
        return $this->auto_incremented_value;
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
     * @throws fNoRemainingException When there are no remaining rows in the result
     *
     * @internal
     *
     * @return int The current row number
     */
    public function key(): mixed
    {
        if ($this->pointer === null) {
            $this->current();
        }

        return $this->pointer;
    }

    /**
     * Advances to the next row in the result (required by iterator interface).
     *
     * @throws fNoRowsException      When the query did not return any rows
     * @throws fNoRemainingException When there are no remaining rows in the result
     *
     * @internal
     */
    public function next(): void
    {
        if ($this->pointer === null) {
            $this->current();
        }

        $this->pointer++;

        if ($this->valid()) {
            $this->advanceCurrentRow();
        } else {
            $this->current_row = null;
        }
    }

    /**
     * Rewinds the query (required by iterator interface).
     *
     * @internal
     */
    public function rewind(): void
    {
        try {
            $this->seek(0);
        } catch (Exception $e) {
        }
    }

    /**
     * Seeks to the specified zero-based row for the specified SQL query.
     *
     * @param int $row The row number to seek to (zero-based)
     *
     * @throws fNoRowsException When the query did not return any rows
     */
    public function seek($row): void
    {
        if (! $this->returned_rows) {
            throw new fNoRowsException('The query did not return any rows');
        }

        if ($row >= $this->returned_rows || $row < 0) {
            throw new fProgrammerException('The row requested does not exist');
        }

        $this->pointer = $row;

        switch ($this->database->getExtension()) {
            case 'mssql':
                $success = mssql_data_seek($this->result, $row);

                break;

            case 'mysql':
                $success = mysql_data_seek($this->result, $row);

                break;

            case 'mysqli':
                if (is_object($this->result)) {
                    $success = mysqli_data_seek($this->result, $row);
                } else {
                    $success = true;
                }

                break;

            case 'pgsql':
                $success = pg_result_seek($this->result, $row);

                break;

            case 'sqlite':
                $success = sqlite_seek($this->result, $row);

                break;

            case 'ibm_db2':
            case 'oci8':
            case 'pdo':
            case 'sqlsrv':
                // Do nothing since we already changed the pointer
                $success = true;

                break;
        }

        if (! $success) {
            throw new fSQLException(
                'There was an error seeking to row %s',
                $row
            );
        }

        $this->advanceCurrentRow();
    }

    /**
     * Sets the number of affected rows.
     *
     * @internal
     *
     * @param int $affected_rows The number of affected rows
     */
    public function setAffectedRows($affected_rows): void
    {
        if ($affected_rows === -1) {
            $affected_rows = 0;
        }
        $this->affected_rows = (int) $affected_rows;
    }

    /**
     * Sets the auto incremented value.
     *
     * @internal
     *
     * @param int $auto_incremented_value The auto incremented value
     */
    public function setAutoIncrementedValue($auto_incremented_value): void
    {
        $this->auto_incremented_value = ($auto_incremented_value == 0) ? null : $auto_incremented_value;
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
     * Sets the number of rows returned.
     *
     * @internal
     *
     * @param int $returned_rows The number of rows returned
     */
    public function setReturnedRows($returned_rows): void
    {
        $this->returned_rows = (int) $returned_rows;
        if ($this->returned_rows) {
            $this->affected_rows = 0;
        }
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
     * @return fResult The result object, to allow for method chaining
     */
    public function tossIfNoRows($message = null)
    {
        if (! $this->returned_rows && ! $this->affected_rows) {
            if ($message === null) {
                $message = 'No rows were returned or affected by the query';
            }

            throw new fNoRowsException($message);
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
     * @return fResult The result object, to allow for method chaining
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
    public function valid(): bool
    {
        if (! $this->returned_rows) {
            return false;
        }

        if ($this->pointer === null) {
            return true;
        }

        return $this->pointer < $this->returned_rows;
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
            case 'mssql':
                $row = mssql_fetch_assoc($this->result);
                if (! empty($row)) {
                    $row = $this->fixDblibMSSQLDriver($row);
                }

                break;

            case 'mysql':
                $row = mysql_fetch_assoc($this->result);

                break;

            case 'mysqli':
                if (is_object($this->result)) {
                    $row = mysqli_fetch_assoc($this->result);
                } else {
                    $row = $this->result[$this->pointer];
                }

                break;

            case 'pgsql':
                $row = pg_fetch_assoc($this->result);

                break;

            case 'sqlite':
                $row = sqlite_fetch_array($this->result, SQLITE_ASSOC);

                break;

            case 'ibm_db2':
            case 'oci8':
            case 'pdo':
            case 'sqlsrv':
                $row = $this->result[$this->pointer];

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
                    if (! is_string($value) || strpos($key, 'fmssqln__') === 0 || isset($row['fmssqln__'.$key]) || preg_match('#[\x0-\x8\xB\xC\xE-\x1F]#', $value)) {
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
            if ($value === ' ') {
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
