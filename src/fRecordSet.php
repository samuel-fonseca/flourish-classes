<?php

/**
 * A lightweight, iterable set of fActiveRecord-based objects.
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fRecordSet
 *
 * @version    1.0.0b40  Added the ::tally() method [wb, 2010-09-28]
 * @changes    1.0.0b39  Backwards Compatibility Break - removed the methods ::fetchRecord(), ::current(), ::key(), ::next(), ::rewind() and ::valid() and the Iterator interface - and the `$pointer` parameter for callbacks registered via fORM::registerRecordSetMethod() was replaced with the `$method_name` parameter - added the methods ::getIterator(), ::getLimit(), ::getPage(), ::getPages(), ::getRecord(), ::offsetExists(), ::offsetGet(), ::offsetSet() and ::offsetUnset() and the IteratorAggregate and ArrayAccess interfaces [wb, 2010-09-28]
 * @changes    1.0.0b38  Updated code to work with the new fORM API [wb, 2010-08-06]
 * @changes    1.0.0b37  Fixed a typo/bug in ::reduce() [wb, 2010-06-30]
 * @changes    1.0.0b36  Replaced create_function() with a private method call [wb, 2010-06-08]
 * @changes    1.0.0b35  Added the ::chunk() and ::split() methods [wb, 2010-05-20]
 * @changes    1.0.0b34  Added an integer cast to ::count() to fix issues with the dblib MSSQL driver [wb, 2010-04-09]
 * @changes    1.0.0b33  Updated the class to force configure classes before peforming actions with them [wb, 2010-03-30]
 * @changes    1.0.0b32  Fixed a column aliasing issue with SQLite [wb, 2010-01-25]
 * @changes    1.0.0b31  Added the ability to compare columns in ::build() with the `=:`, `!:`, `<:`, `<=:`, `>:` and `>=:` operators [wb, 2009-12-08]
 * @changes    1.0.0b30  Fixed a bug affecting where conditions with columns that are not null but have a default value [wb, 2009-11-03]
 * @changes    1.0.0b29  Updated code for the new fORMDatabase and fORMSchema APIs [wb, 2009-10-28]
 * @changes    1.0.0b28  Fixed ::prebuild() and ::precount() to work across all databases, changed SQL statements to use value placeholders, identifier escaping and schema support [wb, 2009-10-22]
 * @changes    1.0.0b27  Changed fRecordSet::build() to fix bad $page numbers instead of throwing an fProgrammerException [wb, 2009-10-05]
 * @changes    1.0.0b26  Updated the documentation for ::build() and ::filter() to reflect new functionality [wb, 2009-09-21]
 * @changes    1.0.0b25  Fixed ::map() to work with string-style static method callbacks in PHP 5.1 [wb, 2009-09-18]
 * @changes    1.0.0b24  Backwards Compatibility Break - renamed ::buildFromRecords() to ::buildFromArray(). Added ::buildFromCall(), ::buildFromMap() and `::build{RelatedRecords}()` [wb, 2009-09-16]
 * @changes    1.0.0b23  Added an extra parameter to ::diff(), ::filter(), ::intersect(), ::slice() and ::unique() to save the number of records in the current set as the non-limited count for the new set [wb, 2009-09-15]
 * @changes    1.0.0b22  Changed ::__construct() to accept any Iterator instead of just an fResult object [wb, 2009-08-12]
 * @changes    1.0.0b21  Added performance tweaks to ::prebuild() and ::precreate() [wb, 2009-07-31]
 * @changes    1.0.0b20  Changed the class to implement Countable, making the [http://php.net/count `count()`] function work [wb, 2009-07-29]
 * @changes    1.0.0b19  Fixed bugs with ::diff() and ::intersect() and empty record sets [wb, 2009-07-29]
 * @changes    1.0.0b18  Added method chaining support to prebuild, precount and precreate methods [wb, 2009-07-15]
 * @changes    1.0.0b17  Changed ::__call() to pass the parameters to the callback [wb, 2009-07-14]
 * @changes    1.0.0b16  Updated documentation for the intersection operator `><` [wb, 2009-07-13]
 * @changes    1.0.0b15  Added the methods ::diff() and ::intersect() [wb, 2009-07-13]
 * @changes    1.0.0b14  Added the methods ::contains() and ::unique() [wb, 2009-07-09]
 * @changes    1.0.0b13  Added documentation to ::build() about the intersection operator `><` [wb, 2009-07-09]
 * @changes    1.0.0b12  Added documentation to ::build() about the `AND LIKE` operator `&~` [wb, 2009-07-09]
 * @changes    1.0.0b11  Added documentation to ::build() about the `NOT LIKE` operator `!~` [wb, 2009-07-08]
 * @changes    1.0.0b10  Moved the private method ::checkConditions() to fActiveRecord::checkConditions() [wb, 2009-07-08]
 * @changes    1.0.0b9   Changed ::build() to only fall back to ordering by primary keys if one exists [wb, 2009-06-26]
 * @changes    1.0.0b8   Updated ::merge() to accept arrays of fActiveRecords or a single fActiveRecord in addition to an fRecordSet [wb, 2009-06-02]
 * @changes    1.0.0b7   Backwards Compatibility Break - Removed ::flagAssociate() and ::isFlaggedForAssociation(), callbacks registered via fORM::registerRecordSetMethod() no longer receive the `$associate` parameter [wb, 2009-06-02]
 * @changes    1.0.0b6   Changed ::tossIfEmpty() to return the record set to allow for method chaining [wb, 2009-05-18]
 * @changes    1.0.0b5   ::build() now allows NULL for `$where_conditions` and `$order_bys`, added a check to the SQL passed to ::buildFromSQL() [wb, 2009-05-03]
 * @changes    1.0.0b4   ::__call() was changed to prevent exceptions coming from fGrammar when an unknown method is called [wb, 2009-03-27]
 * @changes    1.0.0b3   ::sort() and ::sortByCallback() now return the record set to allow for method chaining [wb, 2009-03-23]
 * @changes    1.0.0b2   Added support for != and <> to ::build() and ::filter() [wb, 2008-12-04]
 * @changes    1.0.0b    The initial implementation [wb, 2007-08-04]
 */
class fRecordSet implements IteratorAggregate, ArrayAccess, Countable
{
    // The following constants allow for nice looking callbacks to static methods
    public const build = 'fRecordSet::build';

    public const buildFromArray = 'fRecordSet::buildFromArray';

    public const buildFromSQL = 'fRecordSet::buildFromSQL';

    /**
     * The class of the contained records.
     *
     * @var array|string
     */
    private $class;

    /**
     * The limit that was used when creating the set.
     *
     * @var int
     */
    private $limit;

    /**
     * The page of results the record set represents.
     *
     * @var int
     */
    private $page = 1;

    /**
     * The number of rows that would have been returned if a `LIMIT` clause had not been used, or the SQL to get that number.
     *
     * @var int|string
     */
    private $non_limited_count;

    /**
     * An array of the records in the set, initially empty.
     *
     * @var array
     */
    private $records = [];

    /**
     * Sets the contents of the set.
     *
     * @param array|string   $class             The type(s) of records the object will contain
     * @param array|Iterator $records           The Iterator object of the records to create or an array of records
     * @param int|string     $non_limited_count An SQL statement to get the total number of records sans a `LIMIT` clause or a integer of the total number of records
     * @param int            $limit             The number of records the set was limited to
     * @param int            $page              The page of records that was built
     *
     * @return fRecordSet
     */
    protected function __construct($class, $records = null, $non_limited_count = null, $limit = null, $page = 1)
    {
        $this->class = (is_array($class) && count($class) == 1) ? current($class) : $class;

        if ($non_limited_count !== null) {
            $this->non_limited_count = $non_limited_count;
        }

        if ($records && is_object($records) && $records instanceof Iterator) {
            while ($records->valid()) {
                $this->records[] = new $class($records);
                $records->next();
            }
        }

        if (is_array($records)) {
            $this->records = $records;
        }

        $this->limit = $limit;
        $this->page = $page;
    }

    /**
     * Allows for preloading various data related to the record set in single database queries, as opposed to one query per record.
     *
     * This method will handle methods in the format `verbRelatedRecords()` for
     * the verbs `build`, `prebuild`, `precount` and `precreate`.
     *
     * `build` calls `create{RelatedClass}()` on each record in the set and
     * returns the result as a new record set. The relationship route can be
     * passed as an optional parameter.
     *
     * `prebuild` builds *-to-many record sets for all records in the record
     * set. `precount` will count records in *-to-many record sets for every
     * record in the record set. `precreate` will create a *-to-one record
     * for every record in the record set.
     *
     * @param string $method_name The name of the method called
     * @param string $parameters  The parameters passed
     */
    public function __call($method_name, $parameters)
    {
        if ($callback = fORM::getRecordSetMethod($method_name)) {
            return call_user_func_array(
                $callback,
                [
                    $this,
                    $this->class,
                    &$this->records,
                    $method_name,
                    $parameters,
                ]
            );
        }

        [$action, $subject] = fORM::parseMethod($method_name);

        $route = ($parameters) ? $parameters[0] : null;

        // This check prevents fGrammar exceptions being thrown when an unknown method is called
        if (in_array($action, ['build', 'prebuild', 'precount', 'precreate'])) {
            $related_class = fGrammar::singularize($subject);
        }

        switch ($action) {
            case 'build':
                if ($route) {
                    $this->precreate($related_class, $route);

                    return $this->buildFromCall('create'.$related_class, $route);
                }
                $this->precreate($related_class);

                return $this->buildFromCall('create'.$related_class);

            case 'prebuild':
                return $this->prebuild($related_class, $route);

            case 'precount':
                return $this->precount($related_class, $route);

            case 'precreate':
                return $this->precreate($related_class, $route);
        }

        throw new fProgrammerException(
            'Unknown method, %s(), called',
            $method_name
        );
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
     * Creates an fRecordSet by specifying the class to create plus the where conditions and order by rules.
     *
     * The where conditions array can contain `key => value` entries in any of
     * the following formats:
     *
     * {{{
     * 'column='                    => VALUE,                       // column = VALUE
     * 'column!'                    => VALUE                        // column <> VALUE
     * 'column!='                   => VALUE                        // column <> VALUE
     * 'column<>'                   => VALUE                        // column <> VALUE
     * 'column~'                    => VALUE                        // column LIKE '%VALUE%'
     * 'column!~'                   => VALUE                        // column NOT LIKE '%VALUE%'
     * 'column<'                    => VALUE                        // column < VALUE
     * 'column<='                   => VALUE                        // column <= VALUE
     * 'column>'                    => VALUE                        // column > VALUE
     * 'column>='                   => VALUE                        // column >= VALUE
     * 'column=:'                   => 'other_column'               // column = other_column
     * 'column!:'                   => 'other_column'               // column <> other_column
     * 'column!=:'                  => 'other_column'               // column <> other_column
     * 'column<>:'                  => 'other_column'               // column <> other_column
     * 'column<:'                   => 'other_column'               // column < other_column
     * 'column<=:'                  => 'other_column'               // column <= other_column
     * 'column>:'                   => 'other_column'               // column > other_column
     * 'column>=:'                  => 'other_column'               // column >= other_column
     * 'column='                    => array(VALUE, VALUE2, ... )   // column IN (VALUE, VALUE2, ... )
     * 'column!'                    => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
     * 'column!='                   => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
     * 'column<>'                   => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
     * 'column~'                    => array(VALUE, VALUE2, ... )   // (column LIKE '%VALUE%' OR column LIKE '%VALUE2%' OR column ... )
     * 'column&~'                   => array(VALUE, VALUE2, ... )   // (column LIKE '%VALUE%' AND column LIKE '%VALUE2%' AND column ... )
     * 'column!~'                   => array(VALUE, VALUE2, ... )   // (column NOT LIKE '%VALUE%' AND column NOT LIKE '%VALUE2%' AND column ... )
     * 'column!|column2<|column3='  => array(VALUE, VALUE2, VALUE3) // (column <> '%VALUE%' OR column2 < '%VALUE2%' OR column3 = '%VALUE3%')
     * 'column|column2><'           => array(VALUE, VALUE2)         // WHEN VALUE === NULL: ((column2 IS NULL AND column = VALUE) OR (column2 IS NOT NULL AND column <= VALUE AND column2 >= VALUE))
     *                                                              // WHEN VALUE !== NULL: ((column <= VALUE AND column2 >= VALUE) OR (column >= VALUE AND column <= VALUE2))
     * 'column|column2|column3~'    => VALUE                        // (column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%')
     * 'column|column2|column3~'    => array(VALUE, VALUE2, ... )   // ((column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%') AND (column LIKE '%VALUE2%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE2%') AND ... )
     * }}}
     *
     * When creating a condition in the form `column|column2|column3~`, if the
     * value for the condition is a single string that contains spaces, the
     * string will be parsed for search terms. The search term parsing will
     * handle quoted phrases and normal words and will strip punctuation and
     * stop words (such as "the" and "a").
     *
     * The order bys array can contain `key => value` entries in any of the
     * following formats:
     *
     * {{{
     * 'column'     => 'asc'      // 'first_name' => 'asc'
     * 'column'     => 'desc'     // 'last_name'  => 'desc'
     * 'expression' => 'asc'      // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'asc'
     * 'expression' => 'desc'     // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'desc'
     * }}}
     *
     * The column in both the where conditions and order bys can be in any of
     * the formats:
     *
     * {{{
     * 'column'                                                         // e.g. 'first_name'
     * 'current_table.column'                                           // e.g. 'users.first_name'
     * 'related_table.column'                                           // e.g. 'user_groups.name'
     * 'related_table{route}.column'                                    // e.g. 'user_groups{user_group_id}.name'
     * 'related_table=>once_removed_related_table.column'               // e.g. 'user_groups=>permissions.level'
     * 'related_table{route}=>once_removed_related_table.column'        // e.g. 'user_groups{user_group_id}=>permissions.level'
     * 'related_table=>once_removed_related_table{route}.column'        // e.g. 'user_groups=>permissions{read}.level'
     * 'related_table{route}=>once_removed_related_table{route}.column' // e.g. 'user_groups{user_group_id}=>permissions{read}.level'
     * 'column||other_column'                                           // e.g. 'first_name||last_name' - this concatenates the column values
     * }}}
     *
     * In addition to using plain column names for where conditions, it is also
     * possible to pass an aggregate function wrapped around a column in place
     * of a column name, but only for certain comparison types. //Note that for
     * column comparisons, the function may be placed on either column or both.//
     *
     * {{{
     * 'function(column)='   => VALUE,                       // function(column) = VALUE
     * 'function(column)!'   => VALUE                        // function(column) <> VALUE
     * 'function(column)!=   => VALUE                        // function(column) <> VALUE
     * 'function(column)<>'  => VALUE                        // function(column) <> VALUE
     * 'function(column)~'   => VALUE                        // function(column) LIKE '%VALUE%'
     * 'function(column)!~'  => VALUE                        // function(column) NOT LIKE '%VALUE%'
     * 'function(column)<'   => VALUE                        // function(column) < VALUE
     * 'function(column)<='  => VALUE                        // function(column) <= VALUE
     * 'function(column)>'   => VALUE                        // function(column) > VALUE
     * 'function(column)>='  => VALUE                        // function(column) >= VALUE
     * 'function(column)=:'  => 'other_column'               // function(column) = other_column
     * 'function(column)!:'  => 'other_column'               // function(column) <> other_column
     * 'function(column)!=:' => 'other_column'               // function(column) <> other_column
     * 'function(column)<>:' => 'other_column'               // function(column) <> other_column
     * 'function(column)<:'  => 'other_column'               // function(column) < other_column
     * 'function(column)<=:' => 'other_column'               // function(column) <= other_column
     * 'function(column)>:'  => 'other_column'               // function(column) > other_column
     * 'function(column)>=:' => 'other_column'               // function(column) >= other_column
     * 'function(column)='   => array(VALUE, VALUE2, ... )   // function(column) IN (VALUE, VALUE2, ... )
     * 'function(column)!'   => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
     * 'function(column)!='  => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
     * 'function(column)<>'  => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
     * }}}
     *
     * The aggregate functions `AVG()`, `COUNT()`, `MAX()`, `MIN()` and
     * `SUM()` are supported across all database types.
     *
     * Below is an example of using where conditions and order bys. Please note
     * that values should **not** be escaped for the database, but should just
     * be normal PHP values.
     *
     * {{{
     * #!php
     * return fRecordSet::build(
     *     'User',
     *     array(
     *         'first_name='      => 'John',
     *         'status!'          => 'Inactive',
     *         'groups.group_id=' => 2
     *     ),
     *     array(
     *         'last_name'   => 'asc',
     *         'date_joined' => 'desc'
     *     )
     * );
     * }}}
     *
     * @param string $class            The class to create the fRecordSet of
     * @param array  $where_conditions The `column => value` comparisons for the `WHERE` clause
     * @param array  $order_bys        The `column => direction` values to use for the `ORDER BY` clause
     * @param int    $limit            The number of records to fetch
     * @param int    $page             The page offset to use when limiting records
     *
     * @return fRecordSet A set of fActiveRecord objects
     */
    public static function build($class, $where_conditions = [], $order_bys = [], $limit = null, $page = null)
    {
        fActiveRecord::validateClass($class);
        fActiveRecord::forceConfigure($class);

        $db = fORMDatabase::retrieve($class, 'read');
        $schema = fORMSchema::retrieve($class);
        $table = fORM::tablize($class);

        $params = [$db->escape('SELECT %r.* FROM :from_clause', $table)];

        if ($where_conditions) {
            $having_conditions = fORMDatabase::splitHavingConditions($where_conditions);
        } else {
            $having_conditions = null;
        }

        if ($where_conditions) {
            $params[0] .= ' WHERE ';
            $params = fORMDatabase::addWhereClause($db, $schema, $params, $table, $where_conditions);
        }

        $params[0] .= ' :group_by_clause ';

        if ($having_conditions) {
            $params[0] .= ' HAVING ';
            $params = fORMDatabase::addHavingClause($db, $schema, $params, $table, $having_conditions);
        }

        // If no ordering is specified, order by the primary key
        if (! $order_bys) {
            $order_bys = [];
            foreach ($schema->getKeys($table, 'primary') as $pk_column) {
                $order_bys[$table.'.'.$pk_column] = 'ASC';
            }
        }

        $params[0] .= ' ORDER BY ';
        $params = fORMDatabase::addOrderByClause($db, $schema, $params, $table, $order_bys);

        $params = fORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $table);

        // Add the limit clause and create a query to get the non-limited total
        $non_limited_count_sql = null;
        if ($limit !== null) {
            $pk_columns = [];
            foreach ($schema->getKeys($table, 'primary') as $pk_column) {
                $pk_columns[] = $table.'.'.$pk_column;
            }

            $non_limited_count_sql = str_replace(
                $db->escape('SELECT %r.*', $table),
                $db->escape('SELECT %r', $pk_columns),
                $params[0]
            );
            $non_limited_count_sql = preg_replace('#\s+ORDER BY.*$#', '', $non_limited_count_sql);
            $non_limited_count_sql = $db->escape('SELECT count(*) FROM ('.$non_limited_count_sql.') subquery', array_slice($params, 1));

            $params[0] .= ' LIMIT '.$limit;

            if ($page !== null) {
                if (! is_numeric($page) || $page < 1) {
                    $page = 1;
                }

                $params[0] .= ' OFFSET '.(($page - 1) * $limit);
            }
        } else {
            $page = 1;
        }

        return new self($class, call_user_func_array($db->translatedQuery, $params), $non_limited_count_sql, $limit, $page);
    }

    /**
     * Creates an fRecordSet from an array of records.
     *
     * @internal
     *
     * @param array|string $class   The class or classes of the records
     * @param array        $records the records to create the set from, the order of the record set will be the same as the order of the array
     *
     * @return fRecordSet A set of fActiveRecord objects
     */
    public static function buildFromArray($class, $records)
    {
        if (is_array($class)) {
            foreach ($class as $_class) {
                fActiveRecord::validateClass($_class);
                fActiveRecord::forceConfigure($_class);
            }
        } else {
            fActiveRecord::validateClass($class);
            fActiveRecord::forceConfigure($class);
        }

        if (! is_array($records)) {
            throw new fProgrammerException('The records specified are not in an array');
        }

        return new self($class, $records);
    }

    /**
     * Creates an fRecordSet from an SQL statement.
     *
     * The SQL statement should select all columns from a single table with a *
     * pattern since that is what an fActiveRecord models. If any columns are
     * left out or added, strange error may happen when loading or saving
     * records.
     *
     * Here is an example of an appropriate SQL statement:
     *
     * {{{
     * #!sql
     * SELECT users.* FROM users INNER JOIN groups ON users.group_id = groups.group_id WHERE groups.name = 'Public'
     * }}}
     *
     * Here is an example of a SQL statement that will cause errors:
     *
     * {{{
     * #!sql
     * SELECT users.*, groups.name FROM users INNER JOIN groups ON users.group_id = groups.group_id WHERE groups.group_id = 2
     * }}}
     *
     * The `$non_limited_count_sql` should only be passed when the `$sql`
     * contains a `LIMIT` clause and should contain a count of the records when
     * a `LIMIT` is not imposed.
     *
     * Here is an example of a `$sql` statement with a `LIMIT` clause and a
     * corresponding `$non_limited_count_sql`:
     *
     * {{{
     * #!php
     * fRecordSet::buildFromSQL('User', 'SELECT * FROM users LIMIT 5', 'SELECT count(*) FROM users');
     * }}}
     *
     * The `$non_limited_count_sql` is used when ::count() is called with `TRUE`
     * passed as the parameter.
     *
     * @param string $class                 The class to create the fRecordSet of
     * @param string $sql                   The SQL to create the set from
     * @param string $non_limited_count_sql An SQL statement to get the total number of rows that would have been returned if a `LIMIT` clause had not been used. Should only be passed if a `LIMIT` clause is used.
     *
     * @return fRecordSet A set of fActiveRecord objects
     */
    public static function buildFromSQL($class, $sql, $non_limited_count_sql = null)
    {
        fActiveRecord::validateClass($class);
        fActiveRecord::forceConfigure($class);

        if (! preg_match('#^\s*SELECT\s*(DISTINCT|ALL)?\s*(("?\w+"?\.)?"?\w+"?\.)?\*\s*FROM#i', $sql)) {
            throw new fProgrammerException(
                'The SQL statement specified, %s, does not appear to be in the form SELECT * FROM table',
                $sql
            );
        }

        $db = fORMDatabase::retrieve($class, 'read');

        return new self(
            $class,
            $db->translatedQuery($sql),
            $non_limited_count_sql
        );
    }

    /**
     * Counts the number of records that match the conditions specified.
     *
     * @param string $class            The class of records to count
     * @param mixed  $where_conditions An array of where clause parameters in the same format as ::build()
     *
     * @return int The number of records
     */
    public static function tally($class, $where_conditions = [])
    {
        fActiveRecord::validateClass($class);
        fActiveRecord::forceConfigure($class);

        $db = fORMDatabase::retrieve($class, 'read');
        $schema = fORMSchema::retrieve($class);
        $table = fORM::tablize($class);

        $pk_columns = [];
        foreach ($schema->getKeys($table, 'primary') as $pk_column) {
            $pk_columns[] = $table.'.'.$pk_column;
        }

        $params = [$db->escape('SELECT COUNT(*) FROM (SELECT %r FROM :from_clause', $pk_columns)];

        if ($where_conditions) {
            $having_conditions = fORMDatabase::splitHavingConditions($where_conditions);
        } else {
            $having_conditions = null;
        }

        if ($where_conditions) {
            $params[0] .= ' WHERE ';
            $params = fORMDatabase::addWhereClause($db, $schema, $params, $table, $where_conditions);
        }

        $params[0] .= ' :group_by_clause ';

        if ($having_conditions) {
            $params[0] .= ' HAVING ';
            $params = fORMDatabase::addHavingClause($db, $schema, $params, $table, $having_conditions);
        }

        $params[0] .= ') subquery';

        $params = fORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $table);

        return call_user_func_array($db->translatedQuery, $params)->fetchScalar();
    }

    /**
     * Calls a specific method on each object, returning an fRecordSet of the results.
     *
     * @param string $method    The method to call
     * @param mixed  $parameter A parameter to pass for each call to the method
     * @param  mixed  ...
     *
     * @return fRecordSet A set of records that resulted from calling the method
     */
    public function buildFromCall($method)
    {
        $parameters = func_get_args();

        $result = call_user_func_array($this->call, $parameters);

        $classes = [];
        foreach ($result as $record) {
            if (! $record instanceof fActiveRecord) {
                throw new fProgrammerException(
                    'The method called, %1$s, returned something other than an fActiveRecord object',
                    $method
                );
            }

            $class = get_class($record);

            if (! isset($classes[$class])) {
                $classes[$class] = true;
            }
        }

        // If no objects were returned we need to fake the class
        if (! $classes) {
            $classes = ['fActiveRecord' => true];
        }

        return new self(array_keys($classes), $result);
    }

    /**
     * Maps each record in the set to a callback function, returning an fRecordSet of the results.
     *
     * @param callable $callback  The callback to pass the values to
     * @param mixed    $parameter The parameter to pass to the callback - see method description for details
     * @param  mixed    ...
     *
     * @return fRecordSet A set of records that resulted from the mapping operation
     */
    public function buildFromMap($callback)
    {
        $parameters = func_get_args();

        $result = call_user_func_array($this->map, $parameters);

        $classes = [];
        foreach ($result as $record) {
            if (! $record instanceof fActiveRecord) {
                throw new fProgrammerException(
                    'The map operation specified, %1$s, returned something other than an fActiveRecord object',
                    $callback
                );
            }

            $class = get_class($record);

            if (! isset($classes[$class])) {
                $classes[$class] = true;
            }
        }

        // If no objects were returned we need to fake the class
        if (! $classes) {
            $classes = ['fActiveRecord' => true];
        }

        return new self(array_keys($classes), $result);
    }

    /**
     * Calls a specific method on each object, returning an array of the results.
     *
     * @param string $method    The method to call
     * @param mixed  $parameter A parameter to pass for each call to the method
     * @param  mixed  ...
     *
     * @return array An array the size of the record set with one result from each record/method
     */
    public function call($method)
    {
        $parameters = array_slice(func_get_args(), 1);

        $output = [];
        foreach ($this->records as $record) {
            $output[] = call_user_func_array(
                $record->{$method},
                $parameters
            );
        }

        return $output;
    }

    /**
     * Chunks the record set into an array of fRecordSet objects.
     *
     * Each fRecordSet would contain `$number` records, except for the last,
     * which will contain between 1 and `$number` records.
     *
     * @param int $number The number of fActiveRecord objects to place in each fRecordSet
     *
     * @return array An array of fRecordSet objects
     */
    public function chunk($number)
    {
        $output = [];
        $number_of_sets = ceil($this->count() / $number);
        for ($i = 0; $i < $number_of_sets; $i++) {
            $output[] = new self($this->class, array_slice($this->records, $i * $number, $number));
        }

        return $output;
    }

    /**
     * Checks if the record set contains the record specified.
     *
     * @param fActiveRecord $record The record to check, must exist in the database
     *
     * @return bool If the record specified is in this record set
     */
    public function contains($record)
    {
        $class = get_class($record);
        if (! in_array($class, (array) $this->class)) {
            return false;
        }

        if (! $record->exists()) {
            throw new fProgrammerException(
                'Only records that exist can be checked for in the record set'
            );
        }

        $hash = fActiveRecord::hash($record);

        foreach ($this->records as $_record) {
            if ($class != get_class($_record)) {
                continue;
            }
            if ($hash == fActiveRecord::hash($_record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the number of records in the set.
     *
     * @param bool $ignore_limit If set to `TRUE`, this method will return the number of records that would be in the set if there was no `LIMIT` clause
     *
     * @return int The number of records in the set
     */
    public function count($ignore_limit = false): int
    {
        if ($ignore_limit !== true || $this->non_limited_count === null) {
            return (int) count($this->records);
        }

        if (! is_numeric($this->non_limited_count)) {
            try {
                $db = fORMDatabase::retrieve($this->class, 'read');
                // The integer cast here is to solve issues with the broken dblib
                // SQL Server driver that is sometimes present on Windows machines
                $this->non_limited_count = (int) $db->translatedQuery($this->non_limited_count)->fetchScalar();
            } catch (fExpectedException $e) {
                $this->non_limited_count = $this->count();
            }
        }

        return (int) $this->non_limited_count;
    }

    /**
     * Removes all passed records from the current record set.
     *
     * @param array|fActiveRecord|fRecordSet $records                 The record set, array of records, or record to remove from the current record set, all instances will be removed
     * @param bool                           $remember_original_count If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
     *
     * @return fRecordSet The records not present in the passed records
     */
    public function diff($records, $remember_original_count = false)
    {
        $remove_records = [];

        if ($records instanceof fActiveRecord) {
            $records = [$records];
        }
        foreach ($records as $record) {
            $class = get_class($record);
            $hash = fActiveRecord::hash($record);
            $remove_records[$class.'::'.$hash] = true;
        }

        $new_records = [];
        $classes = [];

        foreach ($this->records as $record) {
            $class = get_class($record);
            $hash = fActiveRecord::hash($record);
            if (! isset($remove_records[$class.'::'.$hash])) {
                $new_records[] = $record;
                $classes[$class] = true;
            }
        }

        if ($classes) {
            $class = array_keys($classes);
        } else {
            $class = $this->class;
        }

        return new self(
            $class,
            $new_records,
            $remember_original_count ? $this->count() : null
        );
    }

    /**
     * Filters the records in the record set via a callback.
     *
     * The `$callback` parameter can be one of three different forms to filter
     * the records in the set:
     *
     *  - A callback that accepts a single record and returns `FALSE` if it should be removed
     *  - A psuedo-callback in the form `'{record}::methodName'` to filter out any records where the output of `$record->methodName()` is equivalent to `FALSE`
     *  - A conditions array that will remove any records that don't meet all of the conditions
     *
     * The conditions array can use one or more of the following `key => value`
     * syntaxes to perform various comparisons. The array keys are method
     * names followed by a comparison operator.
     *
     * {{{
     * // The following forms work for any $value that is not an array
     * 'methodName='                           => $value  // If the output is equal to $value
     * 'methodName!'                           => $value  // If the output is not equal to $value
     * 'methodName!='                          => $value  // If the output is not equal to $value
     * 'methodName<>'                          => $value  // If the output is not equal to $value
     * 'methodName<'                           => $value  // If the output is less than $value
     * 'methodName<='                          => $value  // If the output is less than or equal to $value
     * 'methodName>'                           => $value  // If the output is greater than $value
     * 'methodName>='                          => $value  // If the output is greater than or equal to $value
     * 'methodName~'                           => $value  // If the output contains the $value (case insensitive)
     * 'methodName!~'                          => $value  // If the output does not contain the $value (case insensitive)
     * 'methodName|methodName2|methodName3~'   => $value  // Parses $value as a search string and make sure each term is present in at least one output (case insensitive)
     *
     * // The following forms work for any $array that is an array
     * 'methodName='                           => $array  // If the output is equal to at least one value in $array
     * 'methodName!'                           => $array  // If the output is not equal to any value in $array
     * 'methodName!='                          => $array  // If the output is not equal to any value in $array
     * 'methodName<>'                          => $array  // If the output is not equal to any value in $array
     * 'methodName~'                           => $array  // If the output contains one of the strings in $array (case insensitive)
     * 'methodName!~'                          => $array  // If the output contains none of the strings in $array (case insensitive)
     * 'methodName&~'                          => $array  // If the output contains all of the strings in $array (case insensitive)
     * 'methodName|methodName2|methodName3~'   => $array  // If each value in the array is present in the output of at least one method (case insensitive)
     *
     * // The following works for an equal number of methods and values in the array
     * 'methodName!|methodName2<|methodName3=' => array($value, $value2, $value3) // An OR statement - one of the method to value comparisons must be TRUE
     *
     * // The following accepts exactly two methods and two values, although the second value may be NULL
     * 'methodName|methodName2><'              => array($value, $value2) // If the range of values from the methods intersects the range of $value and $value2 - should be dates, times, timestamps or numbers
     * }}}
     *
     * @param array|callable|string $procedure               The way in which to filter the records - see method description for possible forms
     * @param bool                  $remember_original_count If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
     *
     * @return fRecordSet A new fRecordSet with the filtered records
     */
    public function filter($procedure, $remember_original_count = false)
    {
        if (! $this->records) {
            return clone $this;
        }

        if (is_array($procedure) && is_string(key($procedure))) {
            $type = 'conditions';
            $conditions = $procedure;
        } elseif (is_string($procedure) && preg_match('#^\{record\}::([a-z0-9_\-]+)$#iD', $procedure, $matches)) {
            $type = 'psuedo-callback';
            $method = $matches[1];
        } else {
            $type = 'callback';
            $callback = $procedure;
            if (is_string($callback) && strpos($callback, '::') !== false) {
                $callback = explode('::', $callback);
            }
        }

        $new_records = [];
        $classes = (! is_array($this->class)) ? [$this->class => true] : [];

        foreach ($this->records as $record) {
            switch ($type) {
                case 'conditions':
                    $value = fActiveRecord::checkConditions($record, $conditions);

                    break;

                case 'psuedo-callback':
                    $value = $record->{$method}();

                    break;

                case 'callback':
                    $value = call_user_func($callback, $record);

                    break;
            }

            if ($value) {
                $classes[get_class($record)] = true;

                $new_records[] = $record;
            }
        }

        return new self(
            array_keys($classes),
            $new_records,
            $remember_original_count ? $this->count() : null
        );
    }

    /**
     * Returns the class name of the record being stored.
     *
     * @return array|string The class name(s) of the records in the set
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Returns an iterator for the record set.
     *
     * This method is required by the IteratorAggregate interface.
     *
     * @internal
     *
     * @return Traversable An iterator for the record set
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->records);
    }

    /**
     * Returns the number of records the set was limited to.
     *
     * @return int|null The number of records the set was limited to
     */
    public function getLimit(): int|null
    {
        return (! $this->limit) ? null : $this->limit;
    }

    /**
     * Returns the page of records this set represents.
     *
     * @return int The page of records this set represents
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Returns the number of pages of records exist for the limit used when creating this set.
     *
     * @return float|int The number of pages of records that exist for the limit specified
     *
     * @psalm-return 1|float
     */
    public function getPages(): int|float
    {
        if (! $this->limit) {
            return 1;
        }

        return ceil($this->count(true) / $this->limit);
    }

    /**
     * Returns the record at the zero-based index specified.
     *
     * @throw  fNoRemainingException  When the index is beyond the end of the set
     *
     * @param int $index The index of the record to return
     *
     * @return fActiveRecord The record requested
     */
    public function getRecord($index)
    {
        return $this->offsetGet($index);
    }

    /**
     * Returns all of the records in the set.
     *
     * @return array The records in the set
     */
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * Returns the primary keys for all of the records in the set.
     *
     * @return array The primary keys of all the records in the set
     */
    public function getPrimaryKeys()
    {
        if (! count($this->records)) {
            return [];
        }

        $this->validateSingleClass('get primary key');

        $table = fORM::tablize($this->class);
        $schema = fORMSchema::retrieve($this->class);
        $pk_columns = $schema->getKeys($table, 'primary');
        $first_pk_column = $pk_columns[0];

        $primary_keys = [];

        foreach ($this->records as $number => $record) {
            $keys = [];

            foreach ($pk_columns as $pk_column) {
                $method = 'get'.fGrammar::camelize($pk_column, true);
                $keys[$pk_column] = $record->{$method}();
            }

            $primary_keys[$number] = (count($pk_columns) == 1) ? $keys[$first_pk_column] : $keys;
        }

        return $primary_keys;
    }

    /**
     * Returns all records in the current record set that are also present in the passed records.
     *
     * @param array|fActiveRecord|fRecordSet $records                 The record set, array of records, or record to create an intersection of with the current record set
     * @param bool                           $remember_original_count If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
     *
     * @return fRecordSet The records present in the current record set that are also present in the passed records
     */
    public function intersect($records, $remember_original_count = false)
    {
        $hashes = [];

        if ($records instanceof fActiveRecord) {
            $records = [$records];
        }
        foreach ($records as $record) {
            $class = get_class($record);
            $hash = fActiveRecord::hash($record);
            $hashes[$class.'::'.$hash] = true;
        }

        $new_records = [];
        $classes = [];

        foreach ($this->records as $record) {
            $class = get_class($record);
            $hash = fActiveRecord::hash($record);
            if (isset($hashes[$class.'::'.$hash])) {
                $new_records[] = $record;
                $classes[$class] = true;
            }
        }

        if ($classes) {
            $class = array_keys($classes);
        } else {
            $class = $this->class;
        }

        return new self(
            $class,
            $new_records,
            $remember_original_count ? $this->count() : null
        );
    }

    /**
     * Performs an [http://php.net/array_map array_map()] on the record in the set.
     *
     * The record will be passed to the callback as the first parameter unless
     * it's position is specified by the placeholder string `'{record}'`.
     *
     * Additional parameters can be passed to the callback in one of two
     * different ways:
     *
     *  - Passing a non-array value will cause it to be passed to the callback
     *  - Passing an array value will cause the array values to be passed to the callback with their corresponding record
     *
     * If an array parameter is too long (more items than records in the set)
     * it will be truncated. If an array parameter is too short (less items
     * than records in the set) it will be padded with `NULL` values.
     *
     * To allow passing the record as a specific parameter to the callback, a
     * placeholder string `'{record}'` will be replaced with a the record. It
     * is also possible to specify `'{record}::methodName'` to cause the output
     * of a method from the record to be passed instead of the whole record.
     *
     * It is also possible to pass the zero-based record index to the callback
     * by passing a parameter that contains `'{index}'`.
     *
     * @param callable $callback  The callback to pass the values to
     * @param mixed    $parameter The parameter to pass to the callback - see method description for details
     * @param  mixed    ...
     *
     * @return array An array of the results from the callback
     */
    public function map($callback)
    {
        $parameters = array_slice(func_get_args(), 1);

        if (! $this->records) {
            return [];
        }

        $parameters_array = [];
        $found_record = false;
        $total_records = count($this->records);

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                if (preg_match('#^\{record\}::([a-z0-9_\-]+)$#iD', $parameter, $matches)) {
                    $parameters_array[] = $this->call($matches[1]);
                    $found_record = true;
                } elseif ($parameter === '{record}') {
                    $parameters_array[] = $this->records;
                    $found_record = true;
                } elseif ($parameter === '{index}') {
                    $parameters_array[] = array_keys($this->records);
                } else {
                    $parameters_array[] = array_pad([], $total_records, $parameter);
                }
            } elseif (count($parameter) > $total_records) {
                $parameters_array[] = array_slice($parameter, 0, $total_records);
            } elseif (count($parameter) < $total_records) {
                $parameters_array[] = array_pad($parameter, $total_records, null);
            } else {
                $parameters_array[] = $parameter;
            }
        }

        if (! $found_record) {
            array_unshift($parameters_array, $this->records);
        }

        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        }

        array_unshift($parameters_array, $callback);

        return call_user_func_array('array_map', $parameters_array);
    }

    /**
     * Merges the record set with more records.
     *
     * @param array|fActiveRecord|fRecordSet $records The record set, array of records, or record to merge with the current record set, duplicates will **not** be removed
     *
     * @return fRecordSet The merged record sets
     */
    public function merge($records)
    {
        $classes = array_flip((array) $this->class);

        if ($records instanceof self) {
            $new_records = $records->records;
            $classes += array_flip((array) $records->class);
        } elseif (is_array($records)) {
            $new_records = [];
            foreach ($records as $record) {
                if (! $record instanceof fActiveRecord) {
                    throw new fProgrammerException(
                        'One of the records specified is not an instance of %s',
                        'fActiveRecord'
                    );
                }
                $new_records[] = $record;
                $classes[get_class($record)] = true;
            }
        } elseif ($records instanceof fActiveRecord) {
            $new_records = [$records];
            $classes[get_class($records)] = true;
        } else {
            throw new fProgrammerException(
                'The records specified, %1$s, are invalid. Must be an %2$s, %3$s or an array of %4$s.',
                $records,
                'fRecordSet',
                'fActiveRecord',
                'fActiveRecords'
            );
        }

        if (! $new_records) {
            return $this;
        }

        return new self(
            array_keys($classes),
            array_merge(
                $this->records,
                $new_records
            )
        );
    }

    /**
     * Checks to see if an offset exists.
     *
     * This method is required by the ArrayAccess interface.
     *
     * @internal
     *
     * @param mixed $offset The offset to check
     *
     * @return bool If the offset exists
     */
    public function offsetExists($offset): bool
    {
        return isset($this->records[$offset]);
    }

    /**
     * Returns a record based on the offset.
     *
     * This method is required by the ArrayAccess interface.
     *
     * @internal
     *
     * @param mixed $offset The offset of the record to get
     *
     * @throws fNoRemainingException When the offset specified is beyond the last record
     *
     * @return fActiveRecord The requested record
     */
    public function offsetGet($offset): mixed
    {
        if ((! is_int($offset) && ! is_numeric($offset)) || $offset < 0) {
            throw new fProgrammerException(
                'The offset specified, %1$s, is invalid. Offsets must be a non-negative integer.',
                $offset
            );
        }
        if ($offset >= count($this->records)) {
            throw new fNoRemainingException(
                'The offset specified, %1$s, is beyond the last record in the set',
                $offset
            );
        }

        return $this->records[$offset];
    }

    /**
     * Prevents setting values to the record set.
     *
     * This method is required by the ArrayAccess interface.
     *
     * @internal
     *
     * @param mixed $offset The offset to set
     * @param mixed $value  The value to set to the offset
     */
    public function offsetSet($offset, $value): void
    {
        throw new fProgrammerException(
            '%1$s does not allow setting records via array syntax',
            'fRecordSet'
        );
    }

    /**
     * Prevents unsetting values from the record set.
     *
     * This method is required by the ArrayAccess interface.
     *
     * @internal
     *
     * @param mixed $offset The offset to unset
     */
    public function offsetUnset($offset): void
    {
        throw new fProgrammerException(
            '%1$s does not allow unsetting records via array syntax',
            'fRecordSet'
        );
    }

    /**
     * Reduces the record set to a single value via a callback.
     *
     * The callback should take two parameters and return a single value:
     *
     *  - The initial value and the first record for the first call
     *  - The result of the last call plus the next record for the second and subsequent calls
     *
     * @param callable $callback      The callback to pass the records to - see method description for details
     * @param mixed    $initial_value The initial value to seed reduce with
     *
     * @return mixed The result of the reduce operation
     */
    public function reduce($callback, $initial_value = null)
    {
        if (! $this->records) {
            return $initial_value;
        }

        $result = $initial_value;

        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        }

        foreach ($this->records as $record) {
            $result = call_user_func($callback, $result, $record);
        }

        return $result;
    }

    /**
     * Slices a section of records from the set and returns a new set containing those.
     *
     * @param int  $offset                  The index to start at, negative indexes will slice that many records from the end
     * @param int  $length                  The number of records to return, negative values will stop that many records before the end, `NULL` will return all records to the end of the set - if there are not enough records, less than `$length` will be returned
     * @param bool $remember_original_count If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
     *
     * @return fRecordSet The new slice of records
     */
    public function slice($offset, $length = null, $remember_original_count = false)
    {
        if ($length === null) {
            if ($offset >= 0) {
                $length = count($this->records) - $offset;
            } else {
                $length = abs($offset);
            }
        }

        return new self(
            $this->class,
            array_slice($this->records, $offset, $length),
            $remember_original_count ? $this->count() : null
        );
    }

    /**
     * Sorts the set by the return value of a method from the class created and rewind the interator.
     *
     * This methods uses fUTF8::inatcmp() to perform comparisons.
     *
     * @param string $method    The method to call on each object to get the value to sort by
     * @param string $direction Either `'asc'` or `'desc'`
     *
     * @return fRecordSet The record set object, to allow for method chaining
     */
    public function sort($method, $direction)
    {
        if (! in_array($direction, ['asc', 'desc'])) {
            throw new fProgrammerException(
                'The sort direction specified, %1$s, is invalid. Must be one of: %2$s or %3$s.',
                $direction,
                'asc',
                'desc'
            );
        }

        $this->sort_method = $method;
        $this->sort_direction = $direction;
        $this->sortByCallback([$this, 'sortCallback']);
        unset($this->sort_method, $this->sort_direction);

        return $this;
    }

    /**
     * Sorts the set by passing the callback to [http://php.net/usort `usort()`] and rewinds the interator.
     *
     * @param mixed $callback The function/method to pass to `usort()`
     *
     * @return fRecordSet The record set object, to allow for method chaining
     */
    public function sortByCallback($callback)
    {
        usort($this->records, $callback);

        return $this;
    }

    /**
     * Splits the record set into an array of fRecordSet objects.
     *
     * Each fRecordSet would contain ceil(number of records/`$number`) records,
     * except for the last, which will contain between 1 and ceil(…) records.
     *
     * @param int $number The number of fRecordSet objects to create
     *
     * @return array An array of fRecordSet objects
     */
    public function split($number)
    {
        $output = [];
        $records_per_set = ceil($this->count() / $number);
        for ($i = 0; $i < $number; $i++) {
            $output[] = new self($this->class, array_slice($this->records, $i * $records_per_set, $records_per_set));
        }

        return $output;
    }

    /**
     * Throws an fEmptySetException if the record set is empty.
     *
     * @param string $message The message to use for the exception if there are no records in this set
     *
     * @throws fEmptySetException When there are no record in the set
     *
     * @return fRecordSet The record set object, to allow for method chaining
     */
    public function tossIfEmpty($message = null)
    {
        if ($this->records) {
            return $this;
        }

        if ($message === null) {
            if (is_array($this->class)) {
                $names = array_map(['fORM', 'getRecordName'], $this->class);
                $names = array_map(['fGrammar', 'pluralize'], $names);
                $name = implode(', ', $names);
            } else {
                $name = fGrammar::pluralize(fORM::getRecordName($this->class));
            }

            $message = self::compose(
                'No %s could be found',
                $name
            );
        }

        throw new fEmptySetException($message);
    }

    /**
     * Returns a new fRecordSet containing only unique records in the record set.
     *
     * @param bool $remember_original_count If the number of records in the current set should be saved as the non-limited count for the new set - the page will be reset to `1` either way
     *
     * @return fRecordSet The new record set with only unique records
     */
    public function unique($remember_original_count = false)
    {
        $records = [];

        foreach ($this->records as $record) {
            $class = get_class($record);
            $hash = fActiveRecord::hash($record);
            if (isset($records[$class.'::'.$hash])) {
                continue;
            }
            $records[$class.'::'.$hash] = $record;
        }

        $set = new self(
            $this->class,
            array_values($records)
        );

        if ($remember_original_count) {
            $set->non_limited_count = $this->count();
        }

        return $set;
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
     * Adds an `ORDER BY` clause to the SQL for the primary keys of this record set.
     *
     * @param fDatabase $db            The database the query will be executed on
     * @param fSchema   $schema        The schema for the database
     * @param array     $params        The parameters for the fDatabase::query() call
     * @param string    $related_class The related class to add the order bys for
     * @param string    $route         The route to this table from another table
     *
     * @return array The params with the `ORDER BY` clause added
     */
    private function addOrderByParams($db, $schema, $params, $related_class, $route = null)
    {
        $table = fORM::tablize($this->class);
        $table_with_route = ($route) ? $table.'{'.$route.'}' : $table;

        $pk_columns = $schema->getKeys($table, 'primary');
        $first_pk_column = $pk_columns[0];

        $escaped_pk_columns = [];
        foreach ($pk_columns as $pk_column) {
            $escaped_pk_columns[$pk_column] = $db->escape('%r', $table_with_route.'.'.$pk_column);
        }

        $column_info = $schema->getColumnInfo($table);

        $sql = '';
        $number = 0;
        foreach ($this->getPrimaryKeys() as $primary_key) {
            $sql .= 'WHEN ';

            if (is_array($primary_key)) {
                $conditions = [];
                foreach ($pk_columns as $pk_column) {
                    $value = $primary_key[$pk_column];

                    // This makes sure the query performs the way an insert will
                    if ($value === null && $column_info[$pk_column]['not_null'] && $column_info[$pk_column]['default'] !== null) {
                        $value = $column_info[$pk_column]['default'];
                    }

                    $conditions[] = str_replace(
                        '%r',
                        $escaped_pk_columns[$pk_column],
                        fORMDatabase::makeCondition($schema, $table, $pk_column, '=', $value)
                    );
                    $params[] = $value;
                }
                $sql .= implode(' AND ', $conditions);
            } else {
                $sql .= str_replace(
                    '%r',
                    $escaped_pk_columns[$first_pk_column],
                    fORMDatabase::makeCondition($schema, $table, $first_pk_column, '=', $primary_key)
                );
                $params[] = $primary_key;
            }

            $sql .= ' THEN '.$number.' ';

            $number++;
        }

        $params[0] .= 'CASE '.$sql.'END ASC';

        if ($related_order_bys = fORMRelated::getOrderBys($this->class, $related_class, $route)) {
            $params[0] .= ', ';
            $params = fORMDatabase::addOrderByClause($db, $schema, $params, fORM::tablize($related_class), $related_order_bys);
        }

        return $params;
    }

    /**
     * Adds `WHERE` params to the SQL for the primary keys of this record set.
     *
     * @param fDatabase $db     The database the query will be executed on
     * @param fSchema   $schema The schema for the database
     * @param array     $params The parameters for the fDatabase::query() call
     * @param string    $route  The route to this table from another table
     *
     * @return array The params with the `WHERE` clause added
     */
    private function addWhereParams($db, $schema, $params, $route = null)
    {
        $table = fORM::tablize($this->class);
        $table_with_route = ($route) ? $table.'{'.$route.'}' : $table;

        $pk_columns = $schema->getKeys($table, 'primary');

        // We have a multi-field primary key, making things kinda ugly
        if (count($pk_columns) > 1) {
            $escape_pk_columns = [];
            foreach ($pk_columns as $pk_column) {
                $escaped_pk_columns[$pk_column] = $db->escape('%r', $table_with_route.'.'.$pk_column);
            }

            $column_info = $schema->getColumnInfo($table);

            $conditions = [];

            foreach ($this->getPrimaryKeys() as $primary_key) {
                $sub_conditions = [];
                foreach ($pk_columns as $pk_column) {
                    $value = $primary_key[$pk_column];

                    // This makes sure the query performs the way an insert will
                    if ($value === null && $column_info[$pk_column]['not_null'] && $column_info[$pk_column]['default'] !== null) {
                        $value = $column_info[$pk_column]['default'];
                    }

                    $sub_conditions[] = str_replace(
                        '%r',
                        $escaped_pk_columns[$pk_column],
                        fORMDatabase::makeCondition($schema, $table, $pk_column, '=', $value)
                    );
                    $params[] = $value;
                }
                $conditions[] = implode(' AND ', $sub_conditions);
            }
            $params[0] .= '('.implode(') OR (', $conditions).')';

            // We have a single primary key field, making things nice and easy
        } else {
            $first_pk_column = $pk_columns[0];
            $params[0] .= $db->escape('%r IN ', $table_with_route.'.'.$first_pk_column);
            $params[0] .= '('.$schema->getColumnInfo($table, $first_pk_column, 'placeholder').')';
            $params[] = $this->getPrimaryKeys();
        }

        return $params;
    }

    /**
     * Builds the related records for all records in this set in one DB query.
     *
     * @param string $related_class This should be the name of a related class
     * @param string $route         This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
     *
     * @return fRecordSet The record set object, to allow for method chaining
     */
    private function prebuild($related_class, $route = null)
    {
        if (! $this->records) {
            return $this;
        }

        $this->validateSingleClass('prebuild');

        // If there are no primary keys we can just exit
        if (! array_merge($this->getPrimaryKeys())) {
            return $this;
        }

        fActiveRecord::validateClass($related_class);
        fActiveRecord::forceConfigure($related_class);

        $db = fORMDatabase::retrieve($this->class, 'read');
        $schema = fORMSchema::retrieve($this->class);

        $related_table = fORM::tablize($related_class);
        $table = fORM::tablize($this->class);

        $route = fORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');
        $relationship = fORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-many');

        $table_with_route = ($route) ? $table.'{'.$route.'}' : $table;

        // Build the query out
        $params = [$db->escape('SELECT %r.*', $related_table)];

        // If we are going through a join table we need the related primary key for matching
        if (isset($relationship['join_table'])) {
            // We explicitly alias the column because of SQLite issues
            $params[0] .= $db->escape(', %r AS %r', $table_with_route.'.'.$relationship['column'], $relationship['column']);
        }

        $params[0] .= ' FROM :from_clause WHERE ';
        $params = $this->addWhereParams($db, $schema, $params, $route);
        $params[0] .= ' :group_by_clause ORDER BY ';
        $params = $this->addOrderByParams($db, $schema, $params, $related_class, $route);

        $params = fORMDatabase::injectFromAndGroupByClauses($db, $schema, $params, $related_table);

        // Add the joining column to the group by
        if (strpos($params[0], 'GROUP BY') !== false) {
            $params[0] = str_replace(
                ' ORDER BY',
                $db->escape(', %r ORDER BY', $table.'.'.$relationship['column']),
                $params[0]
            );
        }

        // Run the query and inject the results into the records
        $result = call_user_func_array($db->translatedQuery, $params);

        $total_records = count($this->records);
        for ($i = 0; $i < $total_records; $i++) {
            // Get the record we are injecting into
            $record = $this->records[$i];
            $keys = [];

            // If we are going through a join table, keep track of the record by the value in the join table
            if (isset($relationship['join_table'])) {
                try {
                    $current_row = $result->current();
                    $keys[$relationship['column']] = $current_row[$relationship['column']];
                } catch (fExpectedException $e) {
                }

            // If it is a straight join, keep track of the value by the related column value
            } else {
                $method = 'get'.fGrammar::camelize($relationship['column'], true);
                $keys[$relationship['related_column']] = $record->{$method}();
            }

            // Loop through and find each row for the current record
            $rows = [];

            try {
                while (! array_diff_assoc($keys, $result->current())) {
                    $row = $result->fetchRow();

                    // If we are going through a join table we need to remove the related primary key that was used for matching
                    if (isset($relationship['join_table'])) {
                        unset($row[$relationship['column']]);
                    }

                    $rows[] = $row;
                }
            } catch (fExpectedException $e) {
            }

            // Set up the result object for the new record set
            $set = new self($related_class, new ArrayIterator($rows));

            // Inject the new record set into the record
            $method = 'inject'.fGrammar::pluralize($related_class);
            $record->{$method}($set, $route);
        }

        return $this;
    }

    /**
     * Counts the related records for all records in this set in one DB query.
     *
     * @param string $related_class This should be the name of a related class
     * @param string $route         This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
     *
     * @return fRecordSet The record set object, to allow for method chaining
     */
    private function precount($related_class, $route = null)
    {
        if (! $this->records) {
            return $this;
        }

        $this->validateSingleClass('precount');

        // If there are no primary keys we can just exit
        if (! array_merge($this->getPrimaryKeys())) {
            return $this;
        }

        fActiveRecord::validateClass($related_class);
        fActiveRecord::forceConfigure($related_class);

        $db = fORMDatabase::retrieve($this->class, 'read');
        $schema = fORMSchema::retrieve($this->class);

        $related_table = fORM::tablize($related_class);
        $table = fORM::tablize($this->class);

        $route = fORMSchema::getRouteName($schema, $table, $related_table, $route, '*-to-many');
        $relationship = fORMSchema::getRoute($schema, $table, $related_table, $route, '*-to-many');

        // Build the query out
        $table_and_column = $table.'.'.$relationship['column'];

        if (isset($relationship['join_table'])) {
            $table_to_join = $relationship['join_table'];
            $column_to_join = $relationship['join_table'].'.'.$relationship['join_column'];
        } else {
            $table_to_join = $related_table;
            $column_to_join = $related_table.'.'.$relationship['related_column'];
        }

        $params = [$db->escape(
            'SELECT count(*) AS flourish__count, %r AS flourish__column FROM %r INNER JOIN %r ON %r = %r WHERE ',
            $table_and_column,
            $table,
            $table_to_join,
            $table_and_column,
            $column_to_join
        )];
        $params = $this->addWhereParams($db, $schema, $params);
        $params[0] .= $db->escape(' GROUP BY %r', $table_and_column);

        // Run the query and inject the results into the records
        $result = call_user_func_array($db->translatedQuery, $params);

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['flourish__column']] = (int) $row['flourish__count'];
        }

        unset($result);

        $total_records = count($this->records);
        $get_method = 'get'.fGrammar::camelize($relationship['column'], true);
        $tally_method = 'tally'.fGrammar::pluralize($related_class);

        for ($i = 0; $i < $total_records; $i++) {
            $record = $this->records[$i];
            $count = (isset($counts[$record->{$get_method}()])) ? $counts[$record->{$get_method}()] : 0;
            $record->{$tally_method}($count, $route);
        }

        return $this;
    }

    /**
     * Creates the objects for related records that are in a one-to-one or many-to-one relationship with the current class in a single DB query.
     *
     * @param string $related_class This should be the name of a related class
     * @param string $route         This should be the column name of the foreign key and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
     *
     * @return fRecordSet The record set object, to allow for method chaining
     */
    private function precreate($related_class, $route = null)
    {
        if (! $this->records) {
            return $this;
        }

        $this->validateSingleClass('precreate');

        // If there are no primary keys we can just exit
        if (! array_merge($this->getPrimaryKeys())) {
            return $this;
        }

        fActiveRecord::validateClass($related_class);
        fActiveRecord::forceConfigure($related_class);

        $relationship = fORMSchema::getRoute(
            fORMSchema::retrieve($this->class),
            fORM::tablize($this->class),
            fORM::tablize($related_class),
            $route,
            '*-to-one'
        );

        $values = $this->call('get'.fGrammar::camelize($relationship['column'], true));
        $values = array_unique($values);

        self::build(
            $related_class,
            [
                $relationship['related_column'].'=' => $values,
            ]
        );

        return $this;
    }

    /**
     * A usort callback to sort by methods on records.
     *
     * @param fActiveRecord $a The first record to compare
     * @param fActiveRecord $b The second record to compare
     *
     * @return int < 0 if `$a` is less than `$b`, 0 if `$a` = `$b`, > 0 if `$a` is greater than `$b`
     */
    private function sortCallback($a, $b)
    {
        if ($this->sort_direction == 'asc') {
            return fUTF8::inatcmp($a->{$this->sort_method}(), $b->{$this->sort_method}());
        }

        return fUTF8::inatcmp($b->{$this->sort_method}(), $a->{$this->sort_method}());
    }

    /**
     * Ensures the record set only contains a single kind of record to prevent issues with certain operations.
     *
     * @param string $operation The operation being performed - used in the exception thrown
     */
    private function validateSingleClass($operation): void
    {
        if (! is_array($this->class) && $this->class != 'fActiveRecord') {
            return;
        }

        throw new fProgrammerException(
            'The %1$s operation can not be performed on a record set with multiple types (%2$s) of records',
            $operation,
            implode(', ', $this->class)
        );
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
