<?php
/**
 * Provides a common API for different databases - will automatically use any installed extension.
 *
 * This class is implemented to use the UTF-8 character encoding. Please see
 * http://flourishlib.com/docs/UTF-8 for more information.
 *
 * The following databases are supported:
 *
 *  - [http://ibm.com/db2 DB2]
 *  - [http://microsoft.com/sql/ MSSQL]
 *  - [http://mysql.com MySQL]
 *  - [http://oracle.com Oracle]
 *  - [http://postgresql.org PostgreSQL]
 *  - [http://sqlite.org SQLite]
 *
 * The class will automatically use the first of the following extensions it finds:
 *
 *  - DB2
 *   - [http://php.net/ibm_db2 ibm_db2]
 *   - [http://php.net/pdo_ibm pdo_ibm]
 *  - MSSQL
 *   - [http://msdn.microsoft.com/en-us/library/cc296221.aspx sqlsrv]
 *   - [http://php.net/pdo_dblib pdo_dblib]
 *   - [http://php.net/mssql mssql] (or [http://php.net/sybase sybase])
 *  - MySQL
 *   - [http://php.net/mysql mysql]
 *   - [http://php.net/mysqli mysqli]
 *   - [http://php.net/pdo_mysql pdo_mysql]
 *  - Oracle
 *   - [http://php.net/oci8 oci8]
 *   - [http://php.net/pdo_oci pdo_oci]
 *  - PostgreSQL
 *   - [http://php.net/pgsql pgsql]
 *   - [http://php.net/pdo_pgsql pdo_pgsql]
 *  - SQLite
 *   - [http://php.net/pdo_sqlite pdo_sqlite] (for v3.x)
 *   - [http://php.net/sqlite sqlite] (for v2.x)
 *
 * The `odbc` and `pdo_odbc` extensions are not supported due to character
 * encoding and stability issues on Windows, and functionality on non-Windows
 * operating systems.
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fDatabase
 *
 * @version    1.0.0b30
 * @changes    1.0.0b30  Fixed the pgsql, mssql and mysql extensions to force a new connection instead of reusing an existing one [wb, 2010-08-17]
 * @changes    1.0.0b29  Backwards Compatibility Break - removed ::enableSlowQueryWarnings(), added ability to replicate via ::registerHookCallback() [wb, 2010-08-10]
 * @changes    1.0.0b28  Backwards Compatibility Break - removed ODBC support. Added support for the `pdo_ibm` extension. [wb, 2010-07-31]
 * @changes    1.0.0b27  Fixed a bug with running multiple copies of a SQL statement with string values through a single ::translatedQuery() call [wb, 2010-07-14]
 * @changes    1.0.0b26  Updated the class to use new fCore functionality [wb, 2010-07-05]
 * @changes    1.0.0b25  Added IBM DB2 support [wb, 2010-04-13]
 * @changes    1.0.0b24  Fixed an auto-incrementing transaction bug with Oracle and debugging issues with all databases [wb, 2010-03-17]
 * @changes    1.0.0b23  Resolved another bug with capturing auto-incrementing values for PostgreSQL and Oracle [wb, 2010-03-15]
 * @changes    1.0.0b22  Changed ::clearCache() to also clear the cache on the fSQLTranslation [wb, 2010-03-09]
 * @changes    1.0.0b21  Added ::execute() for result-less SQL queries, ::prepare() and ::translatedPrepare() to create fStatement objects for prepared statements, support for prepared statements in ::query() and ::unbufferedQuery(), fixed default caching key for ::enableCaching() [wb, 2010-03-02]
 * @changes    1.0.0b20  Added a parameter to ::enableCaching() to provide a key token that will allow cached values to be shared between multiple databases with the same schema [wb, 2009-10-28]
 * @changes    1.0.0b19  Added support for escaping identifiers (column and table names) to ::escape(), added support for database schemas, rewrote internal SQL string spliting [wb, 2009-10-22]
 * @changes    1.0.0b18  Updated the class for the new fResult and fUnbufferedResult APIs, fixed ::unescape() to not touch NULLs [wb, 2009-08-12]
 * @changes    1.0.0b17  Added the ability to pass an array of all values as a single parameter to ::escape() instead of one value per parameter [wb, 2009-08-11]
 * @changes    1.0.0b16  Fixed PostgreSQL and Oracle from trying to get auto-incrementing values on inserts when explicit values were given [wb, 2009-08-06]
 * @changes    1.0.0b15  Fixed a bug where auto-incremented values would not be detected when table names were quoted [wb, 2009-07-15]
 * @changes    1.0.0b14  Changed ::determineExtension() and ::determineCharacterSet() to be protected instead of private [wb, 2009-07-08]
 * @changes    1.0.0b13  Updated ::escape() to accept arrays of values for insertion into full SQL strings [wb, 2009-07-06]
 * @changes    1.0.0b12  Updates to ::unescape() to improve performance [wb, 2009-06-15]
 * @changes    1.0.0b11  Changed replacement values in preg_replace() calls to be properly escaped [wb, 2009-06-11]
 * @changes    1.0.0b10  Changed date/time/timestamp escaping from `strtotime()` to fDate/fTime/fTimestamp for better localization support [wb, 2009-06-01]
 * @changes    1.0.0b9   Fixed a bug with ::escape() where floats that start with a . were encoded as `NULL` [wb, 2009-05-09]
 * @changes    1.0.0b8   Added Oracle support, change PostgreSQL code to no longer cause lastval() warnings, added support for arrays of values to ::escape() [wb, 2009-05-03]
 * @changes    1.0.0b7   Updated for new fCore API [wb, 2009-02-16]
 * @changes    1.0.0b6   Fixed a bug with executing transaction queries when using the mysqli extension [wb, 2009-02-12]
 * @changes    1.0.0b5   Changed @ error suppression operator to `error_reporting()` calls [wb, 2009-01-26]
 * @changes    1.0.0b4   Added a few error suppression operators back in so that developers don't get errors and exceptions [wb, 2009-01-14]
 * @changes    1.0.0b3   Removed some unnecessary error suppresion operators [wb, 2008-12-11]
 * @changes    1.0.0b2   Fixed a bug with PostgreSQL when using the PDO extension and executing an INSERT statement [wb, 2008-12-11]
 * @changes    1.0.0b    The initial implementation [wb, 2007-09-25]
 */
class fDatabase
{
    /**
     * The extension to use for the database specified.
     *
     * Options include:
     *
     *  - `'ibm_db2'`
     *  - `'mssql'`
     *  - `'mysql'`
     *  - `'mysqli'`
     *  - `'oci8'`
     *  - `'pgsql'`
     *  - `'sqlite'`
     *  - `'sqlsrv'`
     *  - `'pdo'`
     *
     * @var string
     */
    protected $extension;

    /**
     * A cache of database-specific code.
     *
     * @var array
     */
    protected $schema_info;

    /**
     * An fCache object to cache the schema info to.
     *
     * @var fCache
     */
    private $cache;

    /**
     * The cache prefix to use for cache entries.
     *
     * @var string
     */
    private $cache_prefix;

    /**
     * Database connection resource or PDO object.
     *
     * @var mixed
     */
    private $connection;

    /**
     * The database name.
     *
     * @var string
     */
    private $database;

    /**
     * If debugging is enabled.
     *
     * @var bool
     */
    private $debug;

    /**
     * A temporary error holder for the mssql extension.
     *
     * @var string
     */
    private $error;

    /**
     * Hooks callbacks to be used for accessing and modifying queries.
     *
     * This array will have the structure:
     *
     * {{{
     * array(
     *     'unmodified' => array({callbacks}),
     *     'extracted'  => array({callbacks}),
     *     'run'        => array({callbacks})
     * )
     * }}}
     *
     * @var array
     */
    private $hook_callbacks;

    /**
     * The host the database server is located on.
     *
     * @var string
     */
    private $host;

    /**
     * If a transaction is in progress.
     *
     * @var bool
     */
    private $inside_transaction;

    /**
     * The password for the user specified.
     *
     * @var string
     */
    private $password;

    /**
     * The port number for the host.
     *
     * @var string
     */
    private $port;

    /**
     * The total number of seconds spent executing queries.
     *
     * @var float
     */
    private $query_time;

    /**
     * The last executed fStatement object.
     *
     * @var fStatement
     */
    private $statement;

    /**
     * The fSQLTranslation object for this database.
     *
     * @var object
     */
    private $translation;

    /**
     * The database type: `'db2'`, `'mssql'`, `'mysql'`, `'oracle'`, `'postgresql'`, or `'sqlite'`.
     *
     * @var string
     */
    private $type;

    /**
     * The unbuffered query instance.
     *
     * @var fUnbufferedResult
     */
    private $unbuffered_result;

    /**
     * The user to connect to the database as.
     *
     * @var string
     */
    private $username;

    /**
     * Configures the connection to a database - connection is not made until the first query is executed.
     *
     * @param string $type     The type of the database: `'db2'`, `'mssql'`, `'mysql'`, `'oracle'`, `'postgresql'`, `'sqlite'`
     * @param string $database Name of the database. If SQLite the path to the database file.
     * @param string $username Database username - not used for SQLite
     * @param string $password The password for the username specified - not used for SQLite
     * @param string $host     Database server host or IP, defaults to localhost - not used for SQLite. MySQL socket connection can be made by entering `'sock:'` followed by the socket path. PostgreSQL socket connection can be made by passing just `'sock:'`.
     * @param int    $port     The port to connect to, defaults to the standard port for the database type specified - not used for SQLite
     *
     * @return fDatabase
     */
    public function __construct($type, $database, $username = null, $password = null, $host = null, $port = null)
    {
        $valid_types = ['db2', 'mssql', 'mysql', 'oracle', 'postgresql', 'sqlite'];
        if (! in_array($type, $valid_types)) {
            throw new fProgrammerException(
                'The database type specified, %1$s, is invalid. Must be one of: %2$s.',
                $type,
                implode(', ', $valid_types)
            );
        }

        if (empty($database)) {
            throw new fProgrammerException('No database was specified');
        }

        if ($host === null) {
            $host = 'localhost';
        }

        $this->type = $type;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;

        $this->hook_callbacks = [
            'unmodified' => [],
            'extracted' => [],
            'run' => [],
        ];

        $this->schema_info = [];

        $this->determineExtension();
    }

    /**
     * Closes the open database connection.
     *
     * @internal
     */
    public function __destruct()
    {
        if (! $this->connection) {
            return;
        }

        fCore::debug('Total query time: '.$this->query_time.' seconds', $this->debug);
        if ($this->extension == 'ibm_db2') {
            db2_close($this->connection);
        } elseif ($this->extension == 'mssql') {
            mssql_close($this->connection);
        } elseif ($this->extension == 'mysql') {
            mysql_close($this->connection);
        } elseif ($this->extension == 'mysqli') {
            mysqli_close($this->connection);
        } elseif ($this->extension == 'oci8') {
            oci_close($this->connection);
        } elseif ($this->extension == 'pgsql') {
            pg_close($this->connection);
        } elseif ($this->extension == 'sqlite') {
            sqlite_close($this->connection);
        } elseif ($this->extension == 'sqlsrv') {
            sqlsrv_close($this->connection);
        } elseif ($this->extension == 'pdo') {
            // PDO objects close their own connections when destroyed
        }
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
     * Clears all of the schema info out of the object and, if set, the fCache object.
     */
    public function clearCache(): void
    {
        $this->schema_info = [];
        if ($this->cache) {
            $this->cache->delete($this->makeCachePrefix().'schema_info');
        }
        if ($this->type == 'mssql') {
            $this->determineCharacterSet();
        }
        if ($this->translation) {
            $this->translation->clearCache();
        }
    }

    /**
     * Sets the schema info to be cached to the fCache object specified.
     *
     * @param fCache $cache     The cache to cache to
     * @param string $key_token Internal use only! (this will be used in the cache key to uniquely identify the cache for this fDatabase object)
     */
    public function enableCaching($cache, $key_token = null): void
    {
        $this->cache = $cache;

        if ($key_token !== null) {
            $this->cache_prefix = 'fDatabase::'.$this->type.'::'.$key_token.'::';
        }

        $this->schema_info = $this->cache->get($this->makeCachePrefix().'schema_info', []);
    }

    /**
     * Sets if debug messages should be shown.
     *
     * @param bool $flag If debugging messages should be shown
     */
    public function enableDebugging($flag): void
    {
        $this->debug = (bool) $flag;
    }

    /**
     * Escapes a value for insertion into SQL.
     *
     * The valid data types are:
     *
     *  - `'blob'`
     *  - `'boolean'`
     *  - `'date'`
     *  - `'float'`
     *  - `'identifier'`
     *  - `'integer'`
     *  - `'string'` (also varchar, char or text)
     *  - `'varchar'`
     *  - `'char'`
     *  - `'text'`
     *  - `'time'`
     *  - `'timestamp'`
     *
     * In addition to being able to specify the data type, you can also pass
     * in an SQL statement with data type placeholders in the following form:
     *
     *  - `%l` for a blob
     *  - `%b` for a boolean
     *  - `%d` for a date
     *  - `%f` for a float
     *  - `%r` for an indentifier (table or column name)
     *  - `%i` for an integer
     *  - `%s` for a string
     *  - `%t` for a time
     *  - `%p` for a timestamp
     *
     * Depending on what `$sql_or_type` and `$value` are, the output will be
     * slightly different. If `$sql_or_type` is a data type or a single
     * placeholder and `$value` is:
     *
     *  - a scalar value - an escaped SQL string is returned
     *  - an array - an array of escaped SQL strings is returned
     *
     * If `$sql_or_type` is a SQL string and `$value` is:
     *
     *  - a scalar value - the escaped value is inserted into the SQL string
     *  - an array - the escaped values are inserted into the SQL string separated by commas
     *
     * If `$sql_or_type` is a SQL string, it is also possible to pass an array
     * of all values as a single parameter instead of one value per parameter.
     * An example would look like the following:
     *
     * {{{
     * #!php
     * $db->escape(
     *     "SELECT * FROM users WHERE status = %s AND authorization_level = %s",
     *     array('Active', 'Admin')
     * );
     * }}}
     *
     * @param string $sql_or_type This can either be the data type to escape or an SQL string with a data type placeholder - see method description
     * @param mixed  $value       The value to escape - both single values and arrays of values are supported, see method description for details
     * @param  mixed  ...
     *
     * @return mixed The escaped value/SQL or an array of the escaped values
     */
    public function escape($sql_or_type, $value)
    {
        $values = array_slice(func_get_args(), 1);

        if (count($values) < 1) {
            throw new fProgrammerException(
                'No value was specified to escape'
            );
        }

        // Convert all objects into strings
        $values = $this->scalarize($values);

        $value = array_shift($values);

        // Handle single value escaping
        $callback = null;

        switch ($sql_or_type) {
            case 'blob':
            case '%l':
                $callback = $this->escapeBlob;

                break;

            case 'boolean':
            case '%b':
                $callback = $this->escapeBoolean;

                break;

            case 'date':
            case '%d':
                $callback = $this->escapeDate;

                break;

            case 'float':
            case '%f':
                $callback = $this->escapeFloat;

                break;

            case 'identifier':
            case '%r':
                $callback = $this->escapeIdentifier;

                break;

            case 'integer':
            case '%i':
                $callback = $this->escapeInteger;

                break;

            case 'string':
            case 'varchar':
            case 'char':
            case 'text':
            case '%s':
                $callback = $this->escapeString;

                break;

            case 'time':
            case '%t':
                $callback = $this->escapeTime;

                break;

            case 'timestamp':
            case '%p':
                $callback = $this->escapeTimestamp;

                break;

            case 'jsonb':
            case 'json':
            case '%j':
                $callback = $this->escapeJson;

                break;
        }

        if ($callback) {
            if (is_array($value)) {
                // If the values were passed as a single array, this handles that
                if (count($value) == 1 && is_array(current($value))) {
                    $value = current($value);
                }

                return array_map($callback, $value);
            }

            return call_user_func($callback, $value);
        }

        // Fix \' in MySQL and PostgreSQL
        if (($this->type == 'mysql' || $this->type == 'postgresql') && strpos($sql_or_type, '\\') !== false) {
            $sql_or_type = preg_replace("#(?<!\\\\)((\\\\{2})*)\\\\'#", "\\1''", $sql_or_type);
        }

        // Separate the SQL from quoted values
        $parts = $this->splitSQL($sql_or_type);

        $temp_sql = '';
        $strings = [];

        // Replace strings with a placeholder so they don't mess up the regex parsing
        foreach ($parts as $part) {
            if ($part[0] == "'") {
                $strings[] = $part;
                $part = ':string_'.(count($strings) - 1);
            }
            $temp_sql .= $part;
        }

        // If the values were passed as a single array, this handles that
        $placeholders = preg_match_all('#%[lbdfristp]\b#', $temp_sql, $trash);
        if (count($values) == 0 && is_array($value) && count($value) == $placeholders) {
            $values = $value;
            $value = array_shift($values);
        }

        array_unshift($values, $value);

        $sql = $this->escapeSQL($temp_sql, $values);

        $string_number = 0;
        foreach ($strings as $string) {
            $string = strtr($string, ['\\' => '\\\\', '$' => '\\$']);
            $sql = preg_replace('#:string_'.$string_number++.'\b#', $string, $sql);
        }

        return $sql;
    }

    /**
     * Executes one or more SQL queries without returning any results.
     *
     * @param fStatement|string $statement One or more SQL statements in a string or an fStatement prepared statement
     * @param mixed             $value     The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
     * @param mixed             ...
     *
     * @return fResult|fUnbufferedResult|null
     */
    public function execute($statement)
    {
        $args = func_get_args();
        $params = array_slice($args, 1);

        if (is_object($statement)) {
            return $this->run($statement, null, $params);
        }

        $queries = $this->prepareSQL($statement, $params, false);

        $output = [];
        foreach ($queries as $query) {
            $this->run($query);
        }
    }

    /**
     * Returns the database connection resource or object.
     *
     * @return mixed The database connection
     */
    public function getConnection()
    {
        $this->connectToDatabase();

        return $this->connection;
    }

    /**
     * Gets the name of the database currently connected to.
     *
     * @return string The name of the database currently connected to
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Gets the php extension being used.
     *
     * @internal
     *
     * @return string The php extension used for database interaction
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * Gets the host for this database.
     *
     * @return string The host
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Gets the port for this database.
     *
     * @return string The port
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Gets the fSQLTranslation object used for translated queries.
     *
     * @return fSQLTranslation The SQL translation object
     */
    public function getSQLTranslation()
    {
        if (! $this->translation) {
            new fSQLTranslation($this);
        }

        return $this->translation;
    }

    /**
     * Gets the database type.
     *
     * @return string The database type: `'mssql'`, `'mysql'`, `'postgresql'` or `'sqlite'`
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Gets the username for this database.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Injects an fSQLTranslation object to handle translation.
     *
     * @internal
     *
     * @param fSQLTranslation $sql_translation The SQL translation object
     */
    public function inject($sql_translation): void
    {
        $this->translation = $sql_translation;
    }

    /**
     * Will indicate if a transaction is currently in progress.
     *
     * @return bool If a transaction has been started and not yet rolled back or committed
     */
    public function isInsideTransaction()
    {
        return $this->inside_transaction;
    }

    /**
     * Prepares a single fStatement object to execute prepared statements.
     *
     * Identifier placeholders (%r) are not supported with prepared statements.
     * In addition, multiple values can not be escaped by a placeholder - only
     * a single value can be provided.
     *
     * @param string $sql The SQL to prepare
     *
     * @return fStatement A prepared statement object that can be passed to ::query(), ::unbufferedQuery() or ::execute()
     */
    public function prepare($sql)
    {
        return $this->prepareStatement($sql);
    }

    /**
     * Executes one or more SQL queries and returns the result(s).
     *
     * @param fStatement|string $statement One or more SQL statements in a string or a single fStatement prepared statement
     * @param mixed             $value     The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
     * @param mixed             ...
     *
     * @return (fResult|fUnbufferedResult)[]|fResult|fUnbufferedResult The fResult object(s) for the query
     *
     * @psalm-return fResult|fUnbufferedResult|list<fResult|fUnbufferedResult>
     */
    public function query($statement)
    {
        $args = func_get_args();
        $params = array_slice($args, 1);

        if (is_object($statement)) {
            return $this->run($statement, 'fResult', $params);
        }

        $queries = $this->prepareSQL($statement, $params, false);

        $output = [];
        foreach ($queries as $query) {
            $output[] = $this->run($query, 'fResult');
        }

        return count($output) == 1 ? $output[0] : $output;
    }

    /**
     * Registers a callback for one of the various query hooks - multiple callbacks can be registered for each hook.
     *
     * The following hooks are available:
     *  - `'unmodified'`: The original SQL passed to fDatabase, for prepared statements this is called just once before the fStatement object is created
     *  - `'extracted'`: The SQL after all non-empty strings have been extracted and replaced with `:string_{number}` placeholders
     *  - `'run'`: After the SQL has been run
     *
     * Methods for the `'unmodified'` hook should have the following signature:
     *
     *  - **`$database`**:  The fDatabase instance
     *  - **`&$sql`**:      The original, unedited SQL
     *  - **`&$values`**:   The values to be escaped into the placeholders in the SQL - this will be empty for prepared statements
     *
     * Methods for the `'extracted'` hook should have the following signature:
     *
     *  - **`$database`**:  The fDatabase instance
     *  - **`&$sql`**:      The original, unedited SQL
     *  - **`&$values`**:   The values to be escaped into the placeholders in the SQL - this will be empty for prepared statements
     *  - **`&$strings`**   The literal strings that were extracted from the SQL
     *
     * The `extracted` hook is the best place to modify the SQL since there is
     * no risk of breaking string literals. Please note that there may be empty
     * strings (`''`) present in the SQL since some database treat those as
     * `NULL`.
     *
     * Methods for the `'run'` hook should have the following signature:
     *
     *  - **`$database`**:    The fDatabase instance
     *  - **`$query`**:       The (string) SQL or `array(0 => {fStatement object}, 1 => {values array})`
     *  - **`$query_time`**:  The (float) number of seconds the query took
     *  - **`$result`**       The fResult or fUnbufferedResult object, or `FALSE` if no result
     *
     * @param string   $hook     The hook to register for
     * @param callable $callback The callback to register - see the method description for details about the method signature
     */
    public function registerHookCallback($hook, $callback): void
    {
        $valid_hooks = [
            'unmodified',
            'extracted',
            'run',
        ];

        if (! in_array($hook, $valid_hooks)) {
            throw new fProgrammerException(
                'The hook specified, %1$s, should be one of: %2$s.',
                $hook,
                implode(', ', $valid_hooks)
            );
        }

        $this->hook_callbacks[$hook][] = $callback;
    }

    /**
     * Translates one or more SQL statements using fSQLTranslation and executes them without returning any results.
     *
     * @param string $sql   One or more SQL statements
     * @param mixed  $value The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
     * @param mixed  ...
     */
    public function translatedExecute($sql): void
    {
        $args = func_get_args();
        $queries = $this->prepareSQL(
            $sql,
            array_slice($args, 1),
            true
        );

        $output = [];
        foreach ($queries as $query) {
            $this->run($query);
        }
    }

    /**
     * Translates a SQL statement and creates an fStatement object from it.
     *
     * Identifier placeholders (%r) are not supported with prepared statements.
     * In addition, multiple values can not be escaped by a placeholder - only
     * a single value can be provided.
     *
     * @param string $sql The SQL to prepare
     *
     * @return fStatement A prepared statement object that can be passed to ::query(), ::unbufferedQuery() or ::execute()
     */
    public function translatedPrepare($sql)
    {
        return $this->prepareStatement($sql, true);
    }

    /**
     * Translates one or more SQL statements using fSQLTranslation and executes them.
     *
     * @param string $sql   One or more SQL statements
     * @param mixed  $value The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
     * @param mixed  ...
     *
     * @return (fResult|fUnbufferedResult)[]|fResult|fUnbufferedResult The fResult object(s) for the query
     *
     * @psalm-return fResult|fUnbufferedResult|list<fResult|fUnbufferedResult>
     */
    public function translatedQuery($sql)
    {
        $args = func_get_args();
        $queries = $this->prepareSQL(
            $sql,
            array_slice($args, 1),
            true
        );

        $output = [];
        foreach ($queries as $key => $query) {
            $result = $this->run($query, 'fResult');
            if (! is_numeric($key)) {
                [$number, $original_query] = explode(':', $key, 2);
                $result->setUntranslatedSQL($original_query);
            }
            $output[] = $result;
        }

        return count($output) == 1 ? $output[0] : $output;
    }

    /**
     * Executes a single SQL statement in unbuffered mode. This is optimal for
     * large results sets since it does not load the whole result set into
     * memory first. The gotcha is that only one unbuffered result can exist at
     * one time. If another unbuffered query is executed, the old result will
     * be deleted.
     *
     * @param fStatement|string $statement A single SQL statement
     * @param mixed             $value     The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
     * @param mixed             ...
     *
     * @return fResult|fUnbufferedResult The result object for the unbuffered query
     */
    public function unbufferedQuery($statement)
    {
        $args = func_get_args();
        $params = array_slice($args, 1);

        if (is_object($statement)) {
            $result = $this->run($statement, 'fUnbufferedResult', $params);
        } else {
            $queries = $this->prepareSQL($statement, $params, false);

            if (count($queries) > 1) {
                throw new fProgrammerException(
                    'Only a single unbuffered query can be run at a time, however %d were passed',
                    count($queries)
                );
            }

            $result = $this->run($queries[0], 'fUnbufferedResult');
        }

        $this->unbuffered_result = $result;

        return $result;
    }

    /**
     * Translates the SQL statement using fSQLTranslation and then executes it
     * in unbuffered mode. This is optimal for large results sets since it does
     * not load the whole result set into memory first. The gotcha is that only
     * one unbuffered result can exist at one time. If another unbuffered query
     * is executed, the old result will be deleted.
     *
     * @param string $sql   A single SQL statement
     * @param mixed  $value The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
     * @param mixed  ...
     *
     * @return fResult|fUnbufferedResult The result object for the unbuffered query
     */
    public function unbufferedTranslatedQuery($sql)
    {
        $args = func_get_args();
        $queries = $this->prepareSQL(
            $sql,
            array_slice($args, 1),
            true
        );

        if (count($queries) > 1) {
            throw new fProgrammerException(
                'Only a single unbuffered query can be run at a time, however %d were passed',
                count($queries)
            );
        }

        $query_keys = array_keys($queries);
        $key = $query_keys[0];
        [$number, $original_query] = explode(':', $key, 2);

        $result = $this->run($queries[$key], 'fUnbufferedResult');
        $result->setUntranslatedSQL($original_query);

        $this->unbuffered_result = $result;

        return $result;
    }

    /**
     * Unescapes a value coming out of a database based on its data type.
     *
     * The valid data types are:
     *
     *  - `'blob'` (or `'%l'`)
     *  - `'boolean'` (or `'%b'`)
     *  - `'date'` (or `'%d'`)
     *  - `'float'` (or `'%f'`)
     *  - `'integer'` (or `'%i'`)
     *  - `'string'` (also `'%s'`, `'varchar'`, `'char'` or `'text'`)
     *  - `'time'` (or `'%t'`)
     *  - `'timestamp'` (or `'%p'`)
     *
     * @param string $data_type The data type being unescaped - see method description for valid values
     * @param mixed  $value     The value or array of values to unescape
     *
     * @return mixed The unescaped value
     */
    public function unescape($data_type, $value)
    {
        if ($value === null) {
            return $value;
        }

        $callback = null;

        switch ($data_type) {
            // Testing showed that strings tend to be most common,
            // and moving this to the top of the switch statement
            // improved performance on read-heavy pages
            case 'string':
            case 'varchar':
            case 'char':
            case 'text':
            case '%s':
                return $value;

            case 'boolean':
            case '%b':
                $callback = $this->unescapeBoolean;

                break;

            case 'date':
            case '%d':
                $callback = $this->unescapeDate;

                break;

            case 'float':
            case '%f':
                return $value;

            case 'integer':
            case '%i':
                return $value;

            case 'time':
            case '%t':
                $callback = $this->unescapeTime;

                break;

            case 'timestamp':
            case '%p':
                $callback = $this->unescapeTimestamp;

                break;

            case 'blob':
            case '%l':
                $callback = $this->unescapeBlob;

                break;

            case 'jsonb':
            case 'json':
            case '%j':
                $callback = $this->unescapeJson;

                break;
        }

        if ($callback) {
            if (is_array($value)) {
                return array_map($callback, $value);
            }

            return call_user_func($callback, $value);
        }

        throw new fProgrammerException(
            'Unknown data type, %1$s, specified. Must be one of: %2$s.',
            $data_type,
            'blob, %l, boolean, %b, date, %d, float, %f, integer, %i, string, %s, time, %t, timestamp, %p'
        );
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
     * Determines the character set of a SQL Server database.
     */
    protected function determineCharacterSet(): void
    {
        $this->schema_info['character_set'] = 'WINDOWS-1252';
        $this->schema_info['character_set'] = $this->query("SELECT 'WINDOWS-' + CONVERT(VARCHAR, COLLATIONPROPERTY(CONVERT(NVARCHAR, DATABASEPROPERTYEX(DB_NAME(), 'Collation')), 'CodePage')) AS charset")->fetchScalar();
        if ($this->cache) {
            $this->cache->set($this->makeCachePrefix().'schema_info', $this->schema_info);
        }
    }

    /**
     * Figures out which extension to use for the database type selected.
     */
    protected function determineExtension(): void
    {
        switch ($this->type) {
            case 'db2':
                if (extension_loaded('ibm_db2')) {
                    $this->extension = 'ibm_db2';
                } elseif (class_exists('PDO', false) && in_array('ibm', PDO::getAvailableDrivers())) {
                    $this->extension = 'pdo';
                } else {
                    $type = 'DB2';
                    $exts = 'ibm_db2, pdo_ibm';
                }

                break;

            case 'mssql':
                if (extension_loaded('sqlsrv')) {
                    $this->extension = 'sqlsrv';
                } elseif (extension_loaded('mssql')) {
                    $this->extension = 'mssql';
                } elseif (class_exists('PDO', false) && (in_array('dblib', PDO::getAvailableDrivers()) || in_array('mssql', PDO::getAvailableDrivers()))) {
                    $this->extension = 'pdo';
                } else {
                    $type = 'MSSQL';
                    $exts = 'mssql, sqlsrv, pdo_dblib (linux), pdo_mssql (windows)';
                }

                break;

            case 'mysql':
                if (extension_loaded('mysqli')) {
                    $this->extension = 'mysqli';
                } elseif (class_exists('PDO', false) && in_array('mysql', PDO::getAvailableDrivers())) {
                    $this->extension = 'pdo';
                } elseif (extension_loaded('mysql')) {
                    $this->extension = 'mysql';
                } else {
                    $type = 'MySQL';
                    $exts = 'mysql, pdo_mysql, mysqli';
                }

                break;

            case 'oracle':
                if (extension_loaded('oci8')) {
                    $this->extension = 'oci8';
                } elseif (class_exists('PDO', false) && in_array('oci', PDO::getAvailableDrivers())) {
                    $this->extension = 'pdo';
                } else {
                    $type = 'Oracle';
                    $exts = 'oci8, pdo_oci';
                }

                break;

            case 'postgresql':
                if (extension_loaded('pgsql')) {
                    $this->extension = 'pgsql';
                } elseif (class_exists('PDO', false) && in_array('pgsql', PDO::getAvailableDrivers())) {
                    $this->extension = 'pdo';
                } else {
                    $type = 'PostgreSQL';
                    $exts = 'pgsql, pdo_pgsql';
                }

                break;

            case 'sqlite':
                $sqlite_version = 0;

                if (file_exists($this->database)) {
                    $database_handle = fopen($this->database, 'r');
                    $database_version = fread($database_handle, 64);
                    fclose($database_handle);

                    if (strpos($database_version, 'SQLite format 3') !== false) {
                        $sqlite_version = 3;
                    } elseif (strpos($database_version, '** This file contains an SQLite 2.1 database **') !== false) {
                        $sqlite_version = 2;
                    } else {
                        throw new fConnectivityException(
                            'The database specified does not appear to be a valid %1$s or %2$s database',
                            'SQLite v2.1',
                            'v3'
                        );
                    }
                }

                if ((! $sqlite_version || $sqlite_version == 3) && class_exists('PDO', false) && in_array('sqlite', PDO::getAvailableDrivers())) {
                    $this->extension = 'pdo';
                } elseif ($sqlite_version == 3 && (! class_exists('PDO', false) || ! in_array('sqlite', PDO::getAvailableDrivers()))) {
                    throw new fEnvironmentException(
                        'The database specified is an %1$s database and the %2$s extension is not installed',
                        'SQLite v3',
                        'pdo_sqlite'
                    );
                } elseif ((! $sqlite_version || $sqlite_version == 2) && extension_loaded('sqlite')) {
                    $this->extension = 'sqlite';
                } elseif ($sqlite_version == 2 && ! extension_loaded('sqlite')) {
                    throw new fEnvironmentException(
                        'The database specified is an %1$s database and the %2$s extension is not installed',
                        'SQLite v2.1',
                        'sqlite'
                    );
                } else {
                    $type = 'SQLite';
                    $exts = 'pdo_sqlite, sqlite';
                }

                break;
        }

        if (! $this->extension) {
            throw new fEnvironmentException(
                'The server does not have any of the following extensions for %2$s support: %2$s',
                $type,
                $exts
            );
        }
    }

    /**
     * Checks to see if an SQL error occured.
     *
     * @param bool|fResult|fUnbufferedResult $result     The result object for the query
     * @param mixed                          $extra_info The sqlite extension will pass a string error message, the oci8 extension will pass the statement resource
     * @param string                         $sql        The SQL that was executed
     */
    private function checkForError($result, $extra_info = null, $sql = null): void
    {
        if ($result === false || $result->getResult() === false) {
            if ($this->extension == 'ibm_db2') {
                if (is_resource($extra_info)) {
                    $message = db2_stmt_errormsg($extra_info);
                } else {
                    $message = db2_stmt_errormsg();
                }
            } elseif ($this->extension == 'mssql') {
                $message = $this->error;
                unset($this->error);
            } elseif ($this->extension == 'mysql') {
                $message = mysql_error($this->connection);
            } elseif ($this->extension == 'mysqli') {
                if (is_object($extra_info)) {
                    $message = $extra_info->error;
                } else {
                    $message = mysqli_error($this->connection);
                }
            } elseif ($this->extension == 'oci8') {
                $error_info = oci_error($extra_info);
                $message = $error_info['message'];
            } elseif ($this->extension == 'pgsql') {
                $message = pg_last_error($this->connection);
            } elseif ($this->extension == 'sqlite') {
                $message = $extra_info;
            } elseif ($this->extension == 'sqlsrv') {
                $error_info = sqlsrv_errors(SQLSRV_ERR_ALL);
                $message = $error_info[0]['message'];
            } elseif ($this->extension == 'pdo') {
                if ($extra_info instanceof PDOStatement) {
                    $error_info = $extra_info->errorInfo();
                } else {
                    $error_info = $this->connection->errorInfo();
                }
                if (empty($error_info[2])) {
                    $error_info[2] = 'Unknown error - this usually indicates a bug in the PDO driver';
                }
                $message = $error_info[2];
            }

            $db_type_map = [
                'db2' => 'DB2',
                'mssql' => 'MSSQL',
                'mysql' => 'MySQL',
                'oracle' => 'Oracle',
                'postgresql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
            ];

            throw new fSQLException(
                '%1$s error (%2$s) in %3$s',
                $db_type_map[$this->type],
                $message,
                is_object($result) ? $result->getSQL() : $sql
            );
        }
    }

    /**
     * Connects to the database specified if no connection exists.
     *
     * @return void
     */
    private function connectToDatabase()
    {
        // Don't try to reconnect if we are already connected
        if ($this->connection) {
            return;
        }

        // Establish a connection to the database
        if ($this->extension == 'pdo') {
            $username = $this->username;
            $password = $this->password;

            if ($this->type == 'db2') {
                if ($this->host === null && $this->port === null) {
                    $dsn = 'ibm:DSN:'.$this->database;
                } else {
                    $dsn = 'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE='.$this->database.';HOSTNAME='.$this->host.';';
                    $dsn .= 'PORT='.($this->port ? $this->port : 60000).';';
                    $dsn .= 'PROTOCOL=TCPIP;UID='.$username.';PWD='.$password.';';
                    $username = null;
                    $password = null;
                }
            } elseif ($this->type == 'mssql') {
                $separator = (fCore::checkOS('windows')) ? ',' : ':';
                $port = ($this->port) ? $separator.$this->port : '';
                $driver = (fCore::checkOs('windows')) ? 'mssql' : 'dblib';
                $dsn = $driver.':host='.$this->host.$port.';dbname='.$this->database;
            } elseif ($this->type == 'mysql') {
                if (substr($this->host, 0, 5) == 'sock:') {
                    $dsn = 'mysql:unix_socket='.substr($this->host, 5).';dbname='.$this->database;
                } else {
                    $port = ($this->port) ? ';port='.$this->port : '';
                    $dsn = 'mysql:host='.$this->host.';dbname='.$this->database.$port;
                }
            } elseif ($this->type == 'oracle') {
                $port = ($this->port) ? ':'.$this->port : '';
                $dsn = 'oci:dbname='.$this->host.$port.'/'.$this->database.';charset=AL32UTF8';
            } elseif ($this->type == 'postgresql') {
                $dsn = 'pgsql:dbname='.$this->database;
                if ($this->host && $this->host != 'sock:') {
                    $dsn .= ' host='.$this->host;
                }
                if ($this->port) {
                    $dsn .= ' port='.$this->port;
                }
            } elseif ($this->type == 'sqlite') {
                $dsn = 'sqlite:'.$this->database;
            }

            try {
                $this->connection = new PDO($dsn, $username, $password);
                if ($this->type == 'mysql') {
                    $this->connection->setAttribute(PDO::MYSQL_ATTR_DIRECT_QUERY, 1);
                }
            } catch (PDOException $e) {
                $this->connection = false;
            }
        }

        if ($this->extension == 'sqlite') {
            $this->connection = sqlite_open($this->database);
        }

        if ($this->extension == 'ibm_db2') {
            $username = $this->username;
            $password = $this->password;
            if ($this->host === null && $this->port === null) {
                $connection_string = $this->database;
            } else {
                $connection_string = 'DATABASE='.$this->database.';HOSTNAME='.$this->host.';';
                $connection_string .= 'PORT='.($this->port ? $this->port : 60000).';';
                $connection_string .= 'PROTOCOL=TCPIP;UID='.$this->username.';PWD='.$this->password.';';
                $username = null;
                $password = null;
            }
            $options = [
                'autocommit' => DB2_AUTOCOMMIT_ON,
                'DB2_ATTR_CASE' => DB2_CASE_LOWER,
            ];
            $this->connection = db2_connect($connection_string, $username, $password, $options);
        }

        if ($this->extension == 'mssql') {
            $separator = (fCore::checkOS('windows')) ? ',' : ':';
            $this->connection = mssql_connect(($this->port) ? $this->host.$separator.$this->port : $this->host, $this->username, $this->password, true);
            if ($this->connection !== false && mssql_select_db($this->database, $this->connection) === false) {
                $this->connection = false;
            }
        }

        if ($this->extension == 'mysql') {
            if (substr($this->host, 0, 5) == 'sock:') {
                $host = substr($this->host, 4);
            } elseif ($this->port) {
                $host = $this->host.':'.$this->port;
            } else {
                $host = $this->host;
            }
            $this->connection = mysql_connect($host, $this->username, $this->password, true);
            if ($this->connection !== false && mysql_select_db($this->database, $this->connection) === false) {
                $this->connection = false;
            }
        }

        if ($this->extension == 'mysqli') {
            if (substr($this->host, 0, 5) == 'sock:') {
                $this->connection = mysqli_connect('localhost', $this->username, $this->password, $this->database, $this->port, substr($this->host, 5));
            } elseif ($this->port) {
                $this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->database, $this->port);
            } else {
                $this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->database);
            }
        }

        if ($this->extension == 'oci8') {
            $this->connection = oci_connect($this->username, $this->password, $this->host.($this->port ? ':'.$this->port : '').'/'.$this->database, 'AL32UTF8');
        }

        if ($this->extension == 'pgsql') {
            $connection_string = "dbname='".addslashes($this->database)."'";
            if ($this->host && $this->host != 'sock:') {
                $connection_string .= " host='".addslashes($this->host)."'";
            }
            if ($this->username) {
                $connection_string .= " user='".addslashes($this->username)."'";
            }
            if ($this->password) {
                $connection_string .= " password='".addslashes($this->password)."'";
            }
            if ($this->port) {
                $connection_string .= " port='".$this->port."'";
            }
            $this->connection = pg_connect($connection_string, PGSQL_CONNECT_FORCE_NEW);
        }

        if ($this->extension == 'sqlsrv') {
            $options = [
                'Database' => $this->database,
                'UID' => $this->username,
                'PWD' => $this->password,
            ];
            $this->connection = sqlsrv_connect($this->host.','.$this->port, $options);
        }

        // Ensure the connection was established
        if ($this->connection === false) {
            throw new fConnectivityException(
                'Unable to connect to database'
            );
        }

        // Make MySQL act more strict and use UTF-8
        if ($this->type == 'mysql') {
            $this->execute("SET SQL_MODE = 'REAL_AS_FLOAT,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE'");
            $this->execute("SET NAMES 'utf8'");
            $this->execute('SET CHARACTER SET utf8');
        }

        // Make SQLite behave like other DBs for assoc arrays
        if ($this->type == 'sqlite') {
            $this->execute('PRAGMA short_column_names = 1');
        }

        // Fix some issues with mssql
        if ($this->type == 'mssql') {
            if (! isset($this->schema_info['character_set'])) {
                $this->determineCharacterSet();
            }
            $this->execute('SET TEXTSIZE 65536');
            $this->execute('SET QUOTED_IDENTIFIER ON');
        }

        // Make PostgreSQL use UTF-8
        if ($this->type == 'postgresql') {
            $this->execute("SET NAMES 'UTF8'");
        }

        // Oracle has different date and timestamp defaults
        if ($this->type == 'oracle') {
            $this->execute("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD'");
            $this->execute("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
            $this->execute("ALTER SESSION SET NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS TZR'");
            $this->execute("ALTER SESSION SET NLS_TIME_FORMAT = 'HH24:MI:SS'");
            $this->execute("ALTER SESSION SET NLS_TIME_TZ_FORMAT = 'HH24:MI:SS TZR'");
        }
    }

    /**
     * Escapes a blob for use in SQL, includes surround quotes when appropriate.
     *
     * A `NULL` value will be returned as `'NULL'`
     *
     * @param string $value The blob to escape
     *
     * @return null|string The escaped blob
     */
    private function escapeBlob($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        $this->connectToDatabase();

        if ($this->type == 'db2') {
            return "BLOB(X'".bin2hex($value)."')";
        }
        if ($this->type == 'mysql') {
            return "x'".bin2hex($value)."'";
        }
        if ($this->type == 'postgresql') {
            $output = '';
            for ($i = 0; $i < strlen($value); $i++) {
                $output .= '\\\\'.str_pad(decoct(ord($value[$i])), 3, '0', STR_PAD_LEFT);
            }

            return "E'".$output."'";
        }
        if ($this->extension == 'sqlite') {
            return "'".bin2hex($value)."'";
        }
        if ($this->type == 'sqlite') {
            return "X'".bin2hex($value)."'";
        }
        if ($this->type == 'mssql') {
            return '0x'.bin2hex($value);
        }
        if ($this->type == 'oracle') {
            return "'".bin2hex($value)."'";
        }
    }

    /**
     * Escapes a boolean for use in SQL, includes surround quotes when appropriate.
     *
     * A `NULL` value will be returned as `'NULL'`
     *
     * @param bool $value The boolean to escape
     *
     * @return null|string The database equivalent of the boolean passed
     */
    private function escapeBoolean($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        if (in_array($this->type, ['postgresql', 'mysql'])) {
            return ($value) ? 'TRUE' : 'FALSE';
        }
        if (in_array($this->type, ['mssql', 'sqlite', 'db2'])) {
            return ($value) ? "'1'" : "'0'";
        }
        if ($this->type == 'oracle') {
            return ($value) ? '1' : '0';
        }
    }

    /**
     * Escapes a date for use in SQL, includes surrounding quotes.
     *
     * A `NULL` or invalid value will be returned as `'NULL'`
     *
     * @param string $value The date to escape
     *
     * @return string The escaped date
     */
    private function escapeDate($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        try {
            $value = new fDate($value);

            return "'".$value->format('Y-m-d')."'";
        } catch (fValidationException $e) {
            return 'NULL';
        }
    }

    /**
     * Escapes a float for use in SQL.
     *
     * A `NULL` value will be returned as `'NULL'`
     *
     * @param float $value The float to escape
     *
     * @return string The escaped float
     */
    private function escapeFloat($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (! strlen($value)) {
            return 'NULL';
        }
        if (! preg_match('#^[+\-]?([0-9]+(\.[0-9]+)?|(\.[0-9]+))$#D', $value)) {
            return 'NULL';
        }

        return (string) $value;
    }

    /**
     * Escapes an identifier for use in SQL, necessary for reserved words.
     *
     * @param string $value The identifier to escape
     *
     * @return string The escaped identifier
     */
    private function escapeIdentifier($value)
    {
        $value = '"'.str_replace(
            ['"', '.'],
            ['',  '"."'],
            $value
        ).'"';
        if (in_array($this->type, ['oracle', 'db2'])) {
            $value = strtoupper($value);
        }

        return $value;
    }

    /**
     * Escapes an integer for use in SQL.
     *
     * A `NULL` or invalid value will be returned as `'NULL'`
     *
     * @param int $value The integer to escape
     *
     * @return string The escaped integer
     */
    private function escapeInteger($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (! strlen($value)) {
            return 'NULL';
        }
        if (! preg_match('#^([+\-]?[0-9]+)(\.[0-9]*)?$#D', $value, $matches)) {
            return 'NULL';
        }

        return str_replace('+', '', $matches[1]);
    }

    /**
     * Escapes a string for use in SQL, includes surrounding quotes.
     *
     * A `NULL` value will be returned as `'NULL'`.
     *
     * @param string $value The string to escape
     *
     * @return string The escaped string
     */
    private function escapeString($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        $this->connectToDatabase();

        if ($this->type == 'db2') {
            return "'".str_replace("'", "''", $value)."'";
        }
        if ($this->extension == 'mysql') {
            return "'".mysql_real_escape_string($value, $this->connection)."'";
        }
        if ($this->extension == 'mysqli') {
            return "'".mysqli_real_escape_string($this->connection, $value)."'";
        }
        if ($this->extension == 'pgsql') {
            return "'".pg_escape_string($this->connection, $value)."'";
        }
        if ($this->extension == 'sqlite') {
            return "'".sqlite_escape_string($value)."'";
        }
        if ($this->type == 'oracle') {
            return "'".str_replace("'", "''", $value)."'";
        }
        if ($this->type == 'mssql') {
            // If there are any non-ASCII characters, we need to escape
            if (preg_match('#[^\x00-\x7F]#', $value)) {
                preg_match_all('#.|^\z#us', $value, $characters);
                $output = '';
                $last_type = null;
                foreach ($characters[0] as $character) {
                    if (strlen($character) > 1) {
                        $b = array_map('ord', str_split($character));

                        switch (strlen($character)) {
                            case 2:
                                $bin = substr(decbin($b[0]), 3).
                                           substr(decbin($b[1]), 2);

                                break;

                            case 3:
                                $bin = substr(decbin($b[0]), 4).
                                           substr(decbin($b[1]), 2).
                                           substr(decbin($b[2]), 2);

                                break;
                                // If it is a 4-byte character, MSSQL can't store it
                                // so instead store a ?
                            default:
                                $output .= '?';

                                continue 2;
                        }
                        if ($last_type == 'nchar') {
                            $output .= '+';
                        } elseif ($last_type == 'char') {
                            $output .= "'+";
                        }
                        $output .= 'NCHAR('.bindec($bin).')';
                        $last_type = 'nchar';
                    } else {
                        if (! $last_type) {
                            $output .= "'";
                        } elseif ($last_type == 'nchar') {
                            $output .= "+'";
                        }
                        $output .= $character;
                        // Escape single quotes
                        if ($character == "'") {
                            $output .= "'";
                        }
                        $last_type = 'char';
                    }
                }
                if ($last_type == 'char') {
                    $output .= "'";
                } elseif (! $last_type) {
                    $output .= "''";
                }

                // ASCII text is normal
            } else {
                $output = "'".str_replace("'", "''", $value)."'";
            }

            // a \ before a \r\n has to be escaped with another \
            return preg_replace('#(?<!\\\\)\\\\(?=\r\n)#', '\\\\\\\\', $output);
        }
        if ($this->extension == 'pdo') {
            return $this->connection->quote($value);
        }
    }

    private function escapeJson($values): string
    {
        $json = json_decode($values, true);

        if (! $json) {
            $json = [];
        }

        return "'".json_encode(
            $json,
            JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK
        )."'".'::jsonb';
    }

    private function unescapeJson($values)
    {
        return json_decode($values, true);
    }

    /**
     * Takes a SQL string and an array of values and replaces the placeholders with the value.
     *
     * @param string $sql    The SQL string containing placeholders
     * @param array  $values An array of values to escape into the SQL
     *
     * @return string The SQL with the values escaped into it
     */
    private function escapeSQL($sql, $values)
    {
        $original_sql = $sql;
        $pieces = preg_split('#(%[lbdfristpj])\b#', $sql, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $sql = '';
        $value = array_shift($values);

        $missing_values = -1;

        foreach ($pieces as $piece) {
            switch ($piece) {
                case '%l':
                    $callback = $this->escapeBlob;

                    break;

                case '%b':
                    $callback = $this->escapeBoolean;

                    break;

                case '%d':
                    $callback = $this->escapeDate;

                    break;

                case '%f':
                    $callback = $this->escapeFloat;

                    break;

                case '%r':
                    $callback = $this->escapeIdentifier;

                    break;

                case '%i':
                    $callback = $this->escapeInteger;

                    break;

                case '%s':
                    $callback = $this->escapeString;

                    break;

                case '%t':
                    $callback = $this->escapeTime;

                    break;

                case '%p':
                    $callback = $this->escapeTimestamp;

                    break;

                case '%j':
                    $callback = $this->escapeJson;

                    break;

                default:
                    $sql .= $piece;

                    continue 2;
            }

            if (is_array($value)) {
                $sql .= implode(', ', array_map($callback, $value));
            } else {
                $sql .= call_user_func($callback, $value);
            }

            if (count($values)) {
                $value = array_shift($values);
            } else {
                $value = null;
                $missing_values++;
            }
        }

        if ($missing_values > 0) {
            throw new fProgrammerException(
                '%1$s value(s) are missing for the placeholders in: %2$s',
                $missing_values,
                $original_sql
            );
        }

        if (count($values)) {
            throw new fProgrammerException(
                '%1$s extra value(s) were passed for the placeholders in: %2$s',
                count($values),
                $original_sql
            );
        }

        return $sql;
    }

    /**
     * Escapes a time for use in SQL, includes surrounding quotes.
     *
     * A `NULL` or invalid value will be returned as `'NULL'`
     *
     * @param string $value The time to escape
     *
     * @return string The escaped time
     */
    private function escapeTime($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        try {
            $value = new fTime($value);

            if ($this->type == 'mssql' || $this->type == 'oracle') {
                return "'".$value->format('1970-01-01 H:i:s')."'";
            }

            return "'".$value->format('H:i:s')."'";
        } catch (fValidationException $e) {
            return 'NULL';
        }
    }

    /**
     * Escapes a timestamp for use in SQL, includes surrounding quotes.
     *
     * A `NULL` or invalid value will be returned as `'NULL'`
     *
     * @param string $value The timestamp to escape
     *
     * @return string The escaped timestamp
     */
    private function escapeTimestamp($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        try {
            $value = new fTimestamp($value);

            return "'".$value->format('Y-m-d H:i:s')."'";
        } catch (fValidationException $e) {
            return 'NULL';
        }
    }

    /**
     * Takes in a string of SQL that contains multiple queries and returns any array of them.
     *
     * @param string $sql The string of SQL to parse for queries
     *
     * @return array The individual SQL queries
     */
    private function explodeQueries($sql)
    {
        $sql_queries = [];

        // Separate the SQL from quoted values
        preg_match_all("#(?:'([^']*(?:'')*)*?')|(?:[^']+)#", $sql, $matches);

        $cur_sql = '';
        foreach ($matches[0] as $match) {
            // This is a quoted string value, don't do anything to it
            if ($match[0] == "'") {
                $cur_sql .= $match;

                // Handle the SQL, exploding on any ; that isn't escaped with a \
            } else {
                $sql_strings = preg_split('#(?<!\\\\);#', $match);
                $cur_sql .= $sql_strings[0];
                for ($i = 1; $i < count($sql_strings); $i++) {
                    $cur_sql = trim($cur_sql);
                    if ($cur_sql) {
                        $sql_queries[] = $cur_sql;
                    }
                    $cur_sql = $sql_strings[$i];
                }
            }
        }
        if (trim($cur_sql)) {
            $sql_queries[] = $cur_sql;
        }

        return $sql_queries;
    }

    /**
     * Will grab the auto incremented value from the last query (if one exists).
     *
     * @param fResult $result   The result object for the query
     * @param mixed   $resource Only applicable for `pdo`, `oci8` and `sqlsrv` extentions or `mysqli` prepared statements - this is either the `PDOStatement` object, `mysqli_stmt` object or the `oci8` or `sqlsrv` resource
     *
     * @return void
     */
    private function handleAutoIncrementedValue($result, $resource = null)
    {
        if (! preg_match('#^\s*INSERT\s+INTO\s+(?:`|"|\[)?(["\w.]+)(?:`|"|\])?#i', $result->getSQL(), $table_match)) {
            $result->setAutoIncrementedValue(null);

            return;
        }
        $quoted_table = $table_match[1];
        $table = str_replace('"', '', strtolower($table_match[1]));

        $insert_id = null;

        if ($this->type == 'oracle') {
            if (! isset($this->schema_info['sequences'])) {
                $sql = "SELECT
                                LOWER(OWNER) AS \"SCHEMA\",
                                LOWER(TABLE_NAME) AS \"TABLE\",
                                TRIGGER_BODY
                            FROM
                                ALL_TRIGGERS
                            WHERE
                                TRIGGERING_EVENT = 'INSERT' AND
                                STATUS = 'ENABLED' AND
                                TRIGGER_NAME NOT LIKE 'BIN\$%' AND
                                OWNER NOT IN (
                                    'SYS',
                                    'SYSTEM',
                                    'OUTLN',
                                    'ANONYMOUS',
                                    'AURORA\$ORB\$UNAUTHENTICATED',
                                    'AWR_STAGE',
                                    'CSMIG',
                                    'CTXSYS',
                                    'DBSNMP',
                                    'DIP',
                                    'DMSYS',
                                    'DSSYS',
                                    'EXFSYS',
                                    'FLOWS_020100',
                                    'FLOWS_FILES',
                                    'LBACSYS',
                                    'MDSYS',
                                    'ORACLE_OCM',
                                    'ORDPLUGINS',
                                    'ORDSYS',
                                    'PERFSTAT',
                                    'TRACESVR',
                                    'TSMSYS',
                                    'XDB'
                                )";

                $this->schema_info['sequences'] = [];

                foreach ($this->query($sql) as $row) {
                    if (preg_match('#SELECT\s+(["\w.]+).nextval\s+INTO\s+:new\.(\w+)\s+FROM\s+dual#i', $row['trigger_body'], $matches)) {
                        $table_name = $row['table'];
                        if ($row['schema'] != strtolower($this->username)) {
                            $table_name = $row['schema'].'.'.$table_name;
                        }
                        $this->schema_info['sequences'][$table_name] = ['sequence' => $matches[1], 'column' => str_replace('"', '', $matches[2])];
                    }
                }

                if ($this->cache) {
                    $this->cache->set($this->makeCachePrefix().'schema_info', $this->schema_info);
                }
            }

            if (! isset($this->schema_info['sequences'][$table]) || preg_match('#INSERT\s+INTO\s+"?'.preg_quote($quoted_table, '#').'"?\s+\([^\)]*?(\b|")'.preg_quote($this->schema_info['sequences'][$table]['column'], '#').'(\b|")#i', $result->getSQL())) {
                return;
            }

            $insert_id_sql = 'SELECT '.$this->schema_info['sequences'][$table]['sequence'].'.currval AS INSERT_ID FROM dual';
        }

        if ($this->type == 'postgresql') {
            if (! isset($this->schema_info['sequences'])) {
                $sql = "SELECT
                                pg_namespace.nspname AS \"schema\",
                                pg_class.relname AS \"table\",
                                pg_attribute.attname AS column
                            FROM
                                pg_attribute INNER JOIN
                                pg_class ON pg_attribute.attrelid = pg_class.oid INNER JOIN
                                pg_namespace ON pg_class.relnamespace = pg_namespace.oid INNER JOIN
                                pg_attrdef ON pg_class.oid = pg_attrdef.adrelid AND pg_attribute.attnum = pg_attrdef.adnum
                            WHERE
                                NOT pg_attribute.attisdropped AND
                                pg_get_expr(pg_attrdef.adbin, pg_attrdef.adrelid) LIKE 'nextval(%'";

                $this->schema_info['sequences'] = [];

                foreach ($this->query($sql) as $row) {
                    $table_name = strtolower($row['table']);
                    if ($row['schema'] != 'public') {
                        $table_name = $row['schema'].'.'.$table_name;
                    }
                    $this->schema_info['sequences'][$table_name] = $row['column'];
                }

                if ($this->cache) {
                    $this->cache->set($this->makeCachePrefix().'schema_info', $this->schema_info);
                }
            }

            if (! isset($this->schema_info['sequences'][$table]) || preg_match('#INSERT\s+INTO\s+"?'.preg_quote($quoted_table, '#').'"?\s+\([^\)]*?(\b|")'.preg_quote($this->schema_info['sequences'][$table], '#').'(\b|")#i', $result->getSQL())) {
                return;
            }
        }

        if ($this->extension == 'ibm_db2') {
            $insert_id_res = db2_exec($this->connection, 'SELECT IDENTITY_VAL_LOCAL() FROM SYSIBM.SYSDUMMY1');
            $insert_id_row = db2_fetch_assoc($insert_id_res);
            $insert_id = current($insert_id_row);
            db2_free_result($insert_id_res);
        } elseif ($this->extension == 'mssql') {
            $insert_id_res = mssql_query('SELECT @@IDENTITY AS insert_id', $this->connection);
            $insert_id = mssql_result($insert_id_res, 0, 'insert_id');
            mssql_free_result($insert_id_res);
        } elseif ($this->extension == 'mysql') {
            $insert_id = mysql_insert_id($this->connection);
        } elseif ($this->extension == 'mysqli') {
            if (is_object($resource)) {
                $insert_id = mysqli_stmt_insert_id($resource);
            } else {
                $insert_id = mysqli_insert_id($this->connection);
            }
        } elseif ($this->extension == 'oci8') {
            $oci_statement = oci_parse($this->connection, $insert_id_sql);
            oci_execute($oci_statement, $this->inside_transaction ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS);
            $insert_id_row = oci_fetch_array($oci_statement, OCI_ASSOC);
            $insert_id = $insert_id_row['INSERT_ID'];
            oci_free_statement($oci_statement);
        } elseif ($this->extension == 'pgsql') {
            $insert_id_res = pg_query($this->connection, 'SELECT lastval()');
            $insert_id_row = pg_fetch_assoc($insert_id_res);
            $insert_id = array_shift($insert_id_row);
            pg_free_result($insert_id_res);
        } elseif ($this->extension == 'sqlite') {
            $insert_id = sqlite_last_insert_rowid($this->connection);
        } elseif ($this->extension == 'sqlsrv') {
            $insert_id_res = sqlsrv_query($this->connection, 'SELECT @@IDENTITY AS insert_id');
            $insert_id_row = sqlsrv_fetch_array($insert_id_res, SQLSRV_FETCH_ASSOC);
            $insert_id = $insert_id_row['insert_id'];
            sqlsrv_free_stmt($insert_id_res);
        } elseif ($this->extension == 'pdo') {
            switch ($this->type) {
                case 'db2':
                    $insert_id_statement = $this->connection->query('SELECT IDENTITY_VAL_LOCAL() FROM SYSIBM.SYSDUMMY1');
                    $insert_id_row = $insert_id_statement->fetch(PDO::FETCH_ASSOC);
                    $insert_id = array_shift($insert_id_row);
                    $insert_id_statement->closeCursor();
                    unset($insert_id_statement);

                    break;

                case 'mssql':
                    try {
                        $insert_id_statement = $this->connection->query('SELECT @@IDENTITY AS insert_id');
                        if (! $insert_id_statement) {
                            throw new Exception();
                        }

                        $insert_id_row = $insert_id_statement->fetch(PDO::FETCH_ASSOC);
                        $insert_id = array_shift($insert_id_row);
                    } catch (Exception $e) {
                        // If there was an error we don't have an insert id
                    }

                    break;

                case 'oracle':
                    try {
                        $insert_id_statement = $this->connection->query($insert_id_sql);
                        if (! $insert_id_statement) {
                            throw new Exception();
                        }

                        $insert_id_row = $insert_id_statement->fetch(PDO::FETCH_ASSOC);
                        $insert_id = array_shift($insert_id_row);
                    } catch (Exception $e) {
                        // If there was an error we don't have an insert id
                    }

                    break;

                case 'postgresql':
                    $insert_id_statement = $this->connection->query('SELECT lastval()');
                    $insert_id_row = $insert_id_statement->fetch(PDO::FETCH_ASSOC);
                    $insert_id = array_shift($insert_id_row);
                    $insert_id_statement->closeCursor();
                    unset($insert_id_statement);

                    break;

                case 'mysql':
                    $insert_id = $this->connection->lastInsertId();

                    break;

                case 'sqlite':
                    $insert_id = $this->connection->lastInsertId();

                    break;
            }
        }

        $result->setAutoIncrementedValue($insert_id);
    }

    /**
     * Handles a PHP error to extract error information for the mssql extension.
     *
     * @param array $errors An array of error information from fCore::stopErrorCapture()
     *
     * @return void
     */
    private function handleErrors($errors)
    {
        if ($this->extension != 'mssql') {
            return;
        }

        foreach ($errors as $error) {
            if (substr($error['string'], 0, 14) == 'mssql_query():') {
                if ($this->error) {
                    $this->error .= ' ';
                }
                $this->error .= preg_replace('#^mssql_query\(\): ([^:]+: )?#', '', $error['string']);
            }
        }
    }

    /**
     * Makes sure each database and extension handles BEGIN, COMMIT and ROLLBACK.
     *
     * @param string &$sql         The SQL to check for a transaction query
     * @param string $result_class The type of result object to create
     *
     * @return mixed `FALSE` if normal processing should continue, otherwise an object of the type $result_class
     */
    private function handleTransactionQueries(&$sql, $result_class)
    {
        // SQL Server supports transactions, but starts then with BEGIN TRANSACTION
        if ($this->type == 'mssql' && preg_match('#^\s*(begin|start(\s+transaction)?)\s*#i', $sql)) {
            $sql = 'BEGIN TRANSACTION';
        }

        $begin = false;
        $commit = false;
        $rollback = false;

        // Track transactions since most databases don't support nesting
        if (preg_match('#^\s*(begin|start)(\s+(transaction|work))?\s*$#iD', $sql)) {
            if ($this->inside_transaction) {
                throw new fProgrammerException('A transaction is already in progress');
            }
            $this->inside_transaction = true;
            $begin = true;
        } elseif (preg_match('#^\s*(commit)(\s+(transaction|work))?\s*$#iD', $sql)) {
            if (! $this->inside_transaction) {
                throw new fProgrammerException('There is no transaction in progress');
            }
            $this->inside_transaction = false;
            $commit = true;
        } elseif (preg_match('#^\s*(rollback)(\s+(transaction|work))?\s*$#iD', $sql)) {
            if (! $this->inside_transaction) {
                throw new fProgrammerException('There is no transaction in progress');
            }
            $this->inside_transaction = false;
            $rollback = true;
        }

        if (! $begin && ! $commit && ! $rollback) {
            return false;
        }

        // The PDO, OCI8 and SQLSRV extensions require special handling through methods and functions
        $is_pdo = $this->extension == 'pdo';
        $is_oci = $this->extension == 'oci8';
        $is_sqlsrv = $this->extension == 'sqlsrv';
        $is_ibm_db2 = $this->extension == 'ibm_db2';

        if (! $is_pdo && ! $is_oci && ! $is_sqlsrv && ! $is_ibm_db2) {
            return false;
        }

        $this->statement = $sql;

        // PDO seems to act weird if you try to start transactions through a normal query call
        if ($is_pdo) {
            try {
                $is_mssql = $this->type == 'mssql' && substr($this->database, 0, 4) != 'dsn:';
                $is_oracle = $this->type == 'oracle' && substr($this->database, 0, 4) != 'dsn:';
                if ($begin) {
                    // The SQL Server PDO object hasn't implemented transactions
                    if ($is_mssql) {
                        $this->connection->exec('BEGIN TRANSACTION');
                    } elseif ($is_oracle) {
                        $this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
                    } else {
                        $this->connection->beginTransaction();
                    }
                } elseif ($commit) {
                    if ($is_mssql) {
                        $this->connection->exec('COMMIT');
                    } elseif ($is_oracle) {
                        $this->connection->exec('COMMIT');
                        $this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
                    } else {
                        $this->connection->commit();
                    }
                } elseif ($rollback) {
                    if ($is_mssql) {
                        $this->connection->exec('ROLLBACK');
                    } elseif ($is_oracle) {
                        $this->connection->exec('ROLLBACK');
                        $this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
                    } else {
                        $this->connection->rollBack();
                    }
                }
            } catch (Exception $e) {
                $db_type_map = [
                    'db2' => 'DB2',
                    'mssql' => 'MSSQL',
                    'mysql' => 'MySQL',
                    'oracle' => 'Oracle',
                    'postgresql' => 'PostgreSQL',
                    'sqlite' => 'SQLite',
                ];

                throw new fSQLException(
                    '%1$s error (%2$s) in %3$s',
                    $db_type_map[$this->type],
                    $e->getMessage(),
                    $sql
                );
            }
        } elseif ($is_oci) {
            if ($commit) {
                oci_commit($this->connection);
            } elseif ($rollback) {
                oci_rollback($this->connection);
            }
        } elseif ($is_sqlsrv) {
            if ($begin) {
                sqlsrv_begin_transaction($this->connection);
            } elseif ($commit) {
                sqlsrv_commit($this->connection);
            } elseif ($rollback) {
                sqlsrv_rollback($this->connection);
            }
        } elseif ($is_ibm_db2) {
            if ($begin) {
                db2_autocommit($this->connection, false);
            } elseif ($commit) {
                db2_commit($this->connection);
                db2_autocommit($this->connection, true);
            } elseif ($rollback) {
                db2_rollback($this->connection);
                db2_autocommit($this->connection, true);
            }
        }

        if ($result_class) {
            $result = new $result_class($this);
            $result->setSQL($sql);
            $result->setResult(true);

            return $result;
        }

        return true;
    }

    /**
     * Creates a unique cache prefix to help prevent cache conflicts.
     *
     * @return string The cache prefix to use
     */
    private function makeCachePrefix()
    {
        if (! $this->cache_prefix) {
            $prefix = 'fDatabase::'.$this->type.'::';
            if ($this->host) {
                $prefix .= $this->host.'::';
            }
            if ($this->port) {
                $prefix .= $this->port.'::';
            }
            $prefix .= $this->database.'::';
            if ($this->username) {
                $prefix .= $this->username.'::';
            }
            $this->cache_prefix = $prefix;
        }

        return $this->cache_prefix;
    }

    /**
     * Executes a SQL statement.
     *
     * @param fStatement|string $statement The statement to perform
     * @param array             $params    The parameters for prepared statements
     */
    private function perform($statement, $params): void
    {
        fCore::startErrorCapture();

        $extra = null;
        if (is_object($statement)) {
            $result = $statement->execute($params, $extra, $statement != $this->statement);
        } elseif ($this->extension == 'ibm_db2') {
            $result = db2_exec($this->connection, $statement, ['cursor' => DB2_FORWARD_ONLY]);
        } elseif ($this->extension == 'mssql') {
            $result = mssql_query($statement, $this->connection);
        } elseif ($this->extension == 'mysql') {
            $result = mysql_unbuffered_query($statement, $this->connection);
        } elseif ($this->extension == 'mysqli') {
            $result = mysqli_query($this->connection, $statement, MYSQLI_USE_RESULT);
        } elseif ($this->extension == 'oci8') {
            $extra = oci_parse($this->connection, $statement);
            $result = oci_execute($extra, $this->inside_transaction ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS);
        } elseif ($this->extension == 'pgsql') {
            $result = pg_query($this->connection, $statement);
        } elseif ($this->extension == 'sqlite') {
            $result = sqlite_exec($this->connection, $statement, $extra);
        } elseif ($this->extension == 'sqlsrv') {
            $result = sqlsrv_query($this->connection, $statement);
        } elseif ($this->extension == 'pdo') {
            if ($this->type == 'mssql' && ! fCore::checkOS('windows')) {
                $result = $this->connection->query($statement);
                if ($result instanceof PDOStatement) {
                    $result->closeCursor();
                }
            } else {
                $result = $this->connection->exec($statement);
            }
        }
        $this->statement = $statement;

        $this->handleErrors(fCore::stopErrorCapture());

        if ($result === false) {
            $this->checkForError($result, $extra, is_object($statement) ? $statement->getSQL() : $statement);
        } elseif (! is_bool($result) && $result !== null) {
            if ($this->extension == 'ibm_db2') {
                db2_free_result($result);
            } elseif ($this->extension == 'mssql') {
                mssql_free_result($result);
            } elseif ($this->extension == 'mysql') {
                mysql_free_result($result);
            } elseif ($this->extension == 'mysqli') {
                mysqli_free_result($result);
            } elseif ($this->extension == 'oci8') {
                oci_free_statement($oci_statement);
            } elseif ($this->extension == 'pgsql') {
                pg_free_result($result);
            } elseif ($this->extension == 'sqlsrv') {
                sqlsrv_free_stmt($result);
            }
        }
    }

    /**
     * Executes an SQL query.
     *
     * @param fStatement|string $statement The statement to perform
     * @param fResult           $result    The result object for the query
     * @param array             $params    The parameters for prepared statements
     */
    private function performQuery($statement, $result, $params): void
    {
        fCore::startErrorCapture();

        $extra = null;
        if (is_object($statement)) {
            $statement->executeQuery($result, $params, $extra, $statement != $this->statement);
        } elseif ($this->extension == 'ibm_db2') {
            $extra = db2_exec($this->connection, $statement, ['cursor' => DB2_FORWARD_ONLY]);
            if (is_resource($extra)) {
                $rows = [];
                while ($row = db2_fetch_assoc($extra)) {
                    $rows[] = $row;
                }
                $result->setResult($rows);
                unset($rows);
            } else {
                $result->setResult($extra);
            }
        } elseif ($this->extension == 'mssql') {
            $result->setResult(mssql_query($result->getSQL(), $this->connection));
        } elseif ($this->extension == 'mysql') {
            $result->setResult(mysql_query($result->getSQL(), $this->connection));
        } elseif ($this->extension == 'mysqli') {
            $result->setResult(mysqli_query($this->connection, $result->getSQL()));
        } elseif ($this->extension == 'oci8') {
            $extra = oci_parse($this->connection, $result->getSQL());
            if (oci_execute($extra, $this->inside_transaction ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS)) {
                oci_fetch_all($extra, $rows, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC);
                $result->setResult($rows);
                unset($rows);
            } else {
                $result->setResult(false);
            }
        } elseif ($this->extension == 'pgsql') {
            $result->setResult(pg_query($this->connection, $result->getSQL()));
        } elseif ($this->extension == 'sqlite') {
            $result->setResult(sqlite_query($this->connection, $result->getSQL(), SQLITE_ASSOC, $extra));
        } elseif ($this->extension == 'sqlsrv') {
            $extra = sqlsrv_query($this->connection, $result->getSQL());
            if (is_resource($extra)) {
                $rows = [];
                while ($row = sqlsrv_fetch_array($extra, SQLSRV_FETCH_ASSOC)) {
                    $rows[] = $row;
                }
                $result->setResult($rows);
                unset($rows);
            } else {
                $result->setResult($extra);
            }
        } elseif ($this->extension == 'pdo') {
            if (preg_match('#^\s*CREATE(\s+OR\s+REPLACE)?\s+TRIGGER#i', $result->getSQL())) {
                $this->connection->exec($result->getSQL());
                $extra = false;
                $returned_rows = [];
            } else {
                $extra = $this->connection->query($result->getSQL());
                if (is_object($extra)) {
                    // This fixes a segfault issue with blobs and fetchAll() for pdo_ibm
                    if ($this->type == 'db2') {
                        $returned_rows = [];
                        $scanned_for_blobs = false;
                        $blob_columns = [];
                        while (($row = $extra->fetch(PDO::FETCH_ASSOC)) !== false) {
                            if (! $scanned_for_blobs) {
                                foreach ($row as $key => $value) {
                                    if (is_resource($value)) {
                                        $blob_columns[] = $key;
                                    }
                                }
                            }
                            foreach ($blob_columns as $blob_column) {
                                $row[$blob_column] = stream_get_contents($row[$blob_column]);
                            }
                            $returned_rows[] = $row;
                        }
                    } else {
                        $returned_rows = $extra->fetchAll(PDO::FETCH_ASSOC);
                    }
                } else {
                    $returned_rows = $extra;
                }

                // The pdo_pgsql driver likes to return empty rows equal to the number of affected rows for insert and deletes
                if ($this->type == 'postgresql' && $returned_rows && $returned_rows[0] == []) {
                    $returned_rows = [];
                }
            }

            $result->setResult($returned_rows);
        }
        $this->statement = $statement;

        $this->handleErrors(fCore::stopErrorCapture());

        $this->checkForError($result, $extra);

        if ($this->extension == 'ibm_db2') {
            $this->setAffectedRows($result, $extra);
            if ($extra && ! is_object($statement)) {
                db2_free_result($extra);
            }
        } elseif ($this->extension == 'pdo') {
            $this->setAffectedRows($result, $extra);
            if ($extra && ! is_object($statement)) {
                $extra->closeCursor();
            }
        } elseif ($this->extension == 'oci8') {
            $this->setAffectedRows($result, $extra);
            if ($extra && ! is_object($statement)) {
                oci_free_statement($extra);
            }
        } elseif ($this->extension == 'sqlsrv') {
            $this->setAffectedRows($result, $extra);
            if ($extra && ! is_object($statement)) {
                sqlsrv_free_stmt($extra);
            }
        } else {
            $this->setAffectedRows($result, $extra);
        }

        $this->setReturnedRows($result);

        $this->handleAutoIncrementedValue($result, $extra);
    }

    /**
     * Executes an unbuffered SQL query.
     *
     * @param fStatement|string $statement The statement to perform
     * @param fUnbufferedResult $result    The result object for the query
     * @param array             $params    The parameters for prepared statements
     */
    private function performUnbufferedQuery($statement, $result, $params): void
    {
        fCore::startErrorCapture();

        $extra = null;
        if (is_object($statement)) {
            $statement->executeUnbufferedQuery($result, $params, $extra, $statement != $this->statement);
        } elseif ($this->extension == 'ibm_db2') {
            $result->setResult(db2_exec($this->connection, $statement, ['cursor' => DB2_FORWARD_ONLY]));
        } elseif ($this->extension == 'mssql') {
            $result->setResult(mssql_query($result->getSQL(), $this->connection, 20));
        } elseif ($this->extension == 'mysql') {
            $result->setResult(mysql_unbuffered_query($result->getSQL(), $this->connection));
        } elseif ($this->extension == 'mysqli') {
            $result->setResult(mysqli_query($this->connection, $result->getSQL(), MYSQLI_USE_RESULT));
        } elseif ($this->extension == 'oci8') {
            $extra = oci_parse($this->connection, $result->getSQL());
            if (oci_execute($extra, $this->inside_transaction ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS)) {
                $result->setResult($extra);
            } else {
                $result->setResult(false);
            }
        } elseif ($this->extension == 'pgsql') {
            $result->setResult(pg_query($this->connection, $result->getSQL()));
        } elseif ($this->extension == 'sqlite') {
            $result->setResult(sqlite_unbuffered_query($this->connection, $result->getSQL(), SQLITE_ASSOC, $extra));
        } elseif ($this->extension == 'sqlsrv') {
            $result->setResult(sqlsrv_query($this->connection, $result->getSQL()));
        } elseif ($this->extension == 'pdo') {
            $result->setResult($this->connection->query($result->getSQL()));
        }
        $this->statement = $statement;

        $this->handleErrors(fCore::stopErrorCapture());

        $this->checkForError($result, $extra);
    }

    /**
     * Prepares a single fStatement object to execute prepared statements.
     *
     * Identifier placeholders (%r) are not supported with prepared statements.
     * In addition, multiple values can not be escaped by a placeholder - only
     * a single value can be provided.
     *
     * @param string $sql       The SQL to prepare
     * @param bool   $translate If the SQL should be translated using fSQLTranslation
     *
     * @return fStatement A prepare statement object that can be passed to ::query(), ::unbufferedQuery() or ::execute()
     */
    private function prepareStatement($sql, $translate = false)
    {
        // Ensure an SQL statement was passed
        if (empty($sql)) {
            throw new fProgrammerException('No SQL statement passed');
        }

        // This is just to keep the callback method signature consistent
        $values = [];

        if ($this->hook_callbacks['unmodified']) {
            foreach ($this->hook_callbacks['unmodified'] as $callback) {
                $params = [
                    $this,
                    &$sql,
                    &$values,
                ];
                call_user_func_array($callback, $params);
            }
        }

        // Fix \' in MySQL and PostgreSQL
        if (($this->type == 'mysql' || $this->type == 'postgresql') && strpos($sql, '\\') !== false) {
            $sql = preg_replace("#(?<!\\\\)((\\\\{2})*)\\\\'#", "\\1''", $sql);
        }

        // Separate the SQL from quoted values
        $parts = $this->splitSQL($sql);

        $query = '';
        $strings = [];

        foreach ($parts as $part) {
            // We split out all strings except for empty ones because Oracle
            // has to translate empty strings to NULL
            if ($part[0] == "'" && $part != "''") {
                $query .= ':string_'.count($strings);
                $strings[] = $part;
            } else {
                $query .= $part;
            }
        }

        if ($this->hook_callbacks['extracted']) {
            foreach ($this->hook_callbacks['extracted'] as $callback) {
                $params = [
                    $this,
                    &$query,
                    &$values,
                    &$strings,
                ];
                call_user_func_array($callback, $params);
            }
        }

        $pieces = preg_split('#(%[lbdfistp])\b#', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $placeholders = [];

        $new_query = '';
        foreach ($pieces as $piece) {
            if (strlen($piece) == 2 && $piece[0] == '%') {
                $placeholders[] = $piece;
                $new_query .= '%s';
            } else {
                $new_query .= $piece;
            }
        }
        $query = $new_query;

        $untranslated_sql = null;
        if ($translate) {
            [$query] = $t->gethisSQLTranslation()->translate([$query]);
            $untranslated_sql = $sql;
        }

        // Unescape literal semicolons in the queries
        $query = preg_replace('#(?<!\\\\)\\\\;#', ';', $query);

        // Put the strings back into the SQL
        foreach ($strings as $index => $string) {
            $string = strtr($string, ['\\' => '\\\\', '$' => '\\$']);
            $query = preg_replace('#:string_'.$index.'\b#', $string, $query, 1);
        }

        return new fStatement($this, $query, $placeholders, $untranslated_sql);
    }

    /**
     * Prepares the SQL by escaping values, spliting queries, cleaning escaped semicolons, fixing backslashed single quotes and translating.
     *
     * @param string $sql       The SQL to prepare
     * @param array  $values    Literal values to escape into the SQL
     * @param bool   $translate If the SQL should be translated
     *
     * @return array The split out SQL queries, queries that have been translated will have a string key of a number, `:` and the original SQL, non-translated SQL will have a numeric key
     */
    private function prepareSQL($sql, $values, $translate)
    {
        $this->connectToDatabase();

        // Ensure an SQL statement was passed
        if (empty($sql)) {
            throw new fProgrammerException('No SQL statement passed');
        }

        if ($this->hook_callbacks['unmodified']) {
            foreach ($this->hook_callbacks['unmodified'] as $callback) {
                $params = [
                    $this,
                    &$sql,
                    &$values,
                ];
                call_user_func_array($callback, $params);
            }
        }

        // Fix \' in MySQL and PostgreSQL
        if (($this->type == 'mysql' || $this->type == 'postgresql') && strpos($sql, '\\') !== false) {
            $sql = preg_replace("#(?<!\\\\)((\\\\{2})*)\\\\'#", "\\1''", $sql);
        }

        $strings = [[]];
        $queries = [''];
        $number = 0;

        // Separate the SQL from quoted values
        $parts = $this->splitSQL($sql);

        foreach ($parts as $part) {
            // We split out all strings except for empty ones because Oracle
            // has to translate empty strings to NULL
            if ($part[0] == "'" && $part != "''") {
                $queries[$number] .= ':string_'.count($strings[$number]);
                $strings[$number][] = $part;
            } else {
                $split_queries = preg_split('#(?<!\\\\);#', $part);

                $queries[$number] .= $split_queries[0];

                for ($i = 1; $i < count($split_queries); $i++) {
                    $queries[$number] = trim($queries[$number]);
                    $number++;
                    $strings[$number] = [];
                    $queries[$number] = $split_queries[$i];
                }
            }
        }
        if (! trim($queries[$number])) {
            unset($queries[$number], $strings[$number]);
        } else {
            $queries[$number] = trim($queries[$number]);
        }

        // If the values were passed as a single array, this handles that
        $placeholders = preg_match_all('#%[lbdfristp]\b#', implode(';', $queries), $trash);
        if (count($values) == 1 && is_array($values[0]) && count($values[0]) == $placeholders) {
            $values = array_shift($values);
        }

        // Loop through the queries, chunk the values and add blank strings back in
        $chunked_values = [];
        $value_number = 0;
        foreach (array_keys($queries) as $number) {
            $pieces = preg_split('#(%[lbdfristpj])\b#', $queries[$number], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $placeholders = 0;

            $new_sql = '';
            $chunked_values[$number] = [];

            foreach ($pieces as $piece) {
                // A placeholder
                if (strlen($piece) == 2 && $piece[0] == '%') {
                    $value = $values[$value_number];

                    // Here we put blank strings back into the SQL so they can be translated for Oracle
                    if ($piece == '%s' && $value !== null && ! is_array($value) && ((string) $value) == '') {
                        $new_sql .= "''";
                        $value_number++;
                    } elseif ($piece == '%r') {
                        if (is_array($value)) {
                            $new_sql .= implode(', ', array_map($this->escapeIdentifier, $value));
                        } else {
                            $new_sql .= $this->escapeIdentifier($value);
                        }
                        $value_number++;

                        // Other placeholder/value combos just get added
                    } else {
                        $placeholders++;
                        $value_number++;
                        $new_sql .= $piece;
                        $chunked_values[$number][] = $value;
                    }

                    // A piece of SQL
                } else {
                    $new_sql .= $piece;
                }
            }

            $queries[$number] = $new_sql;
        }

        if ($this->hook_callbacks['extracted']) {
            foreach (array_keys($queries) as $number) {
                foreach ($this->hook_callbacks['extracted'] as $callback) {
                    if (! isset($chunked_values[$number])) {
                        $chunked_values[$number] = [];
                    }
                    $params = [
                        $this,
                        &$queries[$number],
                        &$chunked_values[$number],
                        &$strings[$number],
                    ];
                    call_user_func_array($callback, $params);
                }
            }
        }

        // Translate the SQL queries
        if ($translate) {
            $queries = $this->getSQLTranslation()->translate($queries);
        }

        $output = [];
        foreach (array_keys($queries) as $key) {
            $query = $queries[$key];
            $parts = explode(':', $key, 2);
            $number = $parts[0];

            // Escape the values into the SQL
            if (! empty($chunked_values[$number])) {
                $query = $this->escapeSQL($query, $chunked_values[$number]);
            }

            // Unescape literal semicolons in the queries
            $query = preg_replace('#(?<!\\\\)\\\\;#', ';', $query);

            // Put the strings back into the SQL
            if (isset($strings[$number])) {
                foreach ($strings[$number] as $index => $string) {
                    $string = strtr($string, ['\\' => '\\\\', '$' => '\\$']);
                    $query = preg_replace('#:string_'.$index.'\b#', $string, $query, 1);
                }
            }

            $output[$key] = $query;
        }

        return $output;
    }

    /**
     * Runs a single statement and times it, removes any old unbuffered queries before starting.
     *
     * @param fStatement|string $statement   The SQL statement or prepared statement to execute
     * @param string            $result_type The type of result object to return, fResult or fUnbufferedResult
     * @param mixed             $params
     *
     * @return fResult|fUnbufferedResult The result for the query
     */
    private function run($statement, $result_type = null, $params = [])
    {
        if ($this->unbuffered_result) {
            $this->unbuffered_result->__destruct();
            $this->unbuffered_result = null;
        }

        $start_time = microtime(true);

        if (is_object($statement)) {
            $sql = $statement->getSQL();
        } else {
            $sql = $statement;
        }

        if (! $result = $this->handleTransactionQueries($sql, $result_type)) {
            if ($result_type) {
                $result = new $result_type($this, $this->type == 'mssql' ? $this->schema_info['character_set'] : null);
                $result->setSQL($sql);

                if ($result_type == 'fResult') {
                    $this->performQuery($statement, $result, $params);
                } else {
                    $this->performUnbufferedQuery($statement, $result, $params);
                }
            } else {
                $this->perform($statement, $params);
            }
        }

        // Write some debugging info
        $query_time = microtime(true) - $start_time;
        $this->query_time += $query_time;
        if (fCore::getDebug($this->debug)) {
            fCore::debug(
                self::compose(
                    'Query time was %1$s seconds for:%2$s',
                    $query_time,
                    "\n".$sql
                ),
                $this->debug
            );
        }

        if ($this->hook_callbacks['run']) {
            foreach ($this->hook_callbacks['run'] as $callback) {
                $callback_params = [
                    $this,
                    is_object($statement) ? [$statement, $params] : $sql,
                    $query_time,
                    $result,
                ];
                call_user_func_array($callback, $callback_params);
            }
        }

        if ($result_type) {
            return $result;
        }
    }

    /**
     * Turns an array possibly containing objects into an array of all strings.
     *
     * @param array $values The array of values to scalarize
     *
     * @return array The scalarized values
     */
    private function scalarize($values)
    {
        $new_values = [];
        foreach ($values as $value) {
            if (is_object($value) && is_callable([$value, '__toString'])) {
                $value = $value->__toString();
            } elseif (is_object($value)) {
                $value = (string) $value;
            } elseif (is_array($value)) {
                $value = $this->scalarize($value);
            }
            $new_values[] = $value;
        }

        return $new_values;
    }

    /**
     * Sets the number of rows affected by the query.
     *
     * @param fResult $result   The result object for the query
     * @param mixed   $resource Only applicable for `ibm_db2`, `pdo`, `oci8` and `sqlsrv` extentions or `mysqli` prepared statements - this is either the `PDOStatement` object, `mysqli_stmt` object or the `oci8` or `sqlsrv` resource
     */
    private function setAffectedRows($result, $resource = null): void
    {
        if ($this->extension == 'ibm_db2') {
            $insert_update_delete = preg_match('#^\s*(INSERT|UPDATE|DELETE)\b#i', $result->getSQL());
            $result->setAffectedRows(! $insert_update_delete ? 0 : db2_num_rows($resource));
        } elseif ($this->extension == 'mssql') {
            $affected_rows_result = mssql_query('SELECT @@ROWCOUNT AS rows', $this->connection);
            $result->setAffectedRows((int) mssql_result($affected_rows_result, 0, 'rows'));
        } elseif ($this->extension == 'mysql') {
            $result->setAffectedRows(mysql_affected_rows($this->connection));
        } elseif ($this->extension == 'mysqli') {
            if (is_object($resource)) {
                $result->setAffectedRows($resource->affected_rows);
            } else {
                $result->setAffectedRows(mysqli_affected_rows($this->connection));
            }
        } elseif ($this->extension == 'oci8') {
            $result->setAffectedRows(oci_num_rows($resource));
        } elseif ($this->extension == 'pgsql') {
            $result->setAffectedRows(pg_affected_rows($result->getResult()));
        } elseif ($this->extension == 'sqlite') {
            $result->setAffectedRows(sqlite_changes($this->connection));
        } elseif ($this->extension == 'sqlsrv') {
            $result->setAffectedRows(sqlsrv_rows_affected($resource));
        } elseif ($this->extension == 'pdo') {
            // This fixes the fact that rowCount is not reset for non INSERT/UPDATE/DELETE statements
            try {
                if (! $resource || ! $resource->fetch()) {
                    throw new PDOException();
                }
                $result->setAffectedRows(0);
            } catch (PDOException $e) {
                // The SQLite PDO driver seems to return 1 when no rows are returned from a SELECT statement
                if ($this->type == 'sqlite' && $this->extension == 'pdo' && preg_match('#^\s*SELECT#i', $result->getSQL())) {
                    $result->setAffectedRows(0);
                } elseif (! $resource) {
                    $result->setAffectedRows(0);
                } else {
                    $result->setAffectedRows($resource->rowCount());
                }
            }
        }
    }

    /**
     * Sets the number of rows returned by the query.
     *
     * @param fResult $result The result object for the query
     */
    private function setReturnedRows($result): void
    {
        if (is_resource($result->getResult()) || is_object($result->getResult())) {
            if ($this->extension == 'mssql') {
                $result->setReturnedRows(mssql_num_rows($result->getResult()));
            } elseif ($this->extension == 'mysql') {
                $result->setReturnedRows(mysql_num_rows($result->getResult()));
            } elseif ($this->extension == 'mysqli') {
                $result->setReturnedRows(mysqli_num_rows($result->getResult()));
            } elseif ($this->extension == 'pgsql') {
                $result->setReturnedRows(pg_num_rows($result->getResult()));
            } elseif ($this->extension == 'sqlite') {
                $result->setReturnedRows(sqlite_num_rows($result->getResult()));
            }
        } elseif (is_array($result->getResult())) {
            $result->setReturnedRows(count($result->getResult()));
        }
    }

    /**
     * Splits SQL into pieces of SQL and quoted strings.
     *
     * @param string $sql The SQL to split
     *
     * @return array The pieces
     */
    private function splitSQL($sql)
    {
        $parts = [];
        $temp_sql = $sql;
        $start_pos = 0;
        $inside_string = false;
        do {
            $pos = strpos($temp_sql, "'", $start_pos);
            if ($pos !== false) {
                if (! $inside_string) {
                    $parts[] = substr($temp_sql, 0, $pos);
                    $temp_sql = substr($temp_sql, $pos);
                    $start_pos = 1;
                    $inside_string = true;
                } elseif ($pos == strlen($temp_sql)) {
                    $parts[] = $temp_sql;
                    $temp_sql = '';
                    $pos = false;
                } elseif (strlen($temp_sql) > $pos + 1 && $temp_sql[$pos + 1] == "'") {
                    $start_pos = $pos + 2;
                } else {
                    $parts[] = substr($temp_sql, 0, $pos + 1);
                    $temp_sql = substr($temp_sql, $pos + 1);
                    $start_pos = 0;
                    $inside_string = false;
                }
            }
        } while ($pos !== false);
        if ($temp_sql) {
            $parts[] = $temp_sql;
        }

        return $parts;
    }

    /**
     * Unescapes a blob coming out of the database.
     *
     * @param string $value The value to unescape
     *
     * @return false|string The binary data
     */
    private function unescapeBlob($value)
    {
        $this->connectToDatabase();

        if ($this->extension == 'pgsql') {
            return pg_unescape_bytea($value);
        }
        if ($this->extension == 'pdo' && is_resource($value)) {
            return stream_get_contents($value);
        }
        if ($this->extension == 'sqlite') {
            return pack('H*', $value);
        }

        return $value;
    }

    /**
     * Unescapes a boolean coming out of the database.
     *
     * @param string $value The value to unescape
     *
     * @return bool The boolean
     */
    private function unescapeBoolean($value)
    {
        return ($value === 'f' || ! $value) ? false : true;
    }

    /**
     * Unescapes a date coming out of the database.
     *
     * @param string $value The value to unescape
     *
     * @return string The date in YYYY-MM-DD format
     */
    private function unescapeDate($value)
    {
        if ($this->extension == 'sqlsrv' && $value instanceof DateTime) {
            return $value->format('Y-m-d');
        }
        if ($this->type == 'mssql') {
            $value = preg_replace('#:\d{3}#', '', $value);
        }

        return date('Y-m-d', strtotime($value));
    }

    /**
     * Unescapes a time coming out of the database.
     *
     * @param string $value The value to unescape
     *
     * @return string The time in `HH:MM:SS` format
     */
    private function unescapeTime($value)
    {
        if ($this->extension == 'sqlsrv' && $value instanceof DateTime) {
            return $value->format('H:i:s');
        }
        if ($this->type == 'mssql') {
            $value = preg_replace('#:\d{3}#', '', $value);
        }

        return date('H:i:s', strtotime($value));
    }

    /**
     * Unescapes a timestamp coming out of the database.
     *
     * @param string $value The value to unescape
     *
     * @return string The timestamp in `YYYY-MM-DD HH:MM:SS` format
     */
    private function unescapeTimestamp($value)
    {
        if ($this->extension == 'sqlsrv' && $value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($this->type == 'mssql') {
            $value = preg_replace('#:\d{3}#', '', $value);
        }

        return date('Y-m-d H:i:s', strtotime($value));
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
