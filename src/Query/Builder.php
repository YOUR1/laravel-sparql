<?php

namespace LinkedData\SPARQL\Query;

use Closure;
use Illuminate\Database\Concerns\BuildsQueries;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use LinkedData\SPARQL\Eloquent\Builder as EloquentBuilder;
use LinkedData\SPARQL\Query\Concerns\BuildsAggregates;
use LinkedData\SPARQL\Query\Concerns\BuildsDataTypes;
use LinkedData\SPARQL\Query\Concerns\BuildsDateWhereClauses;
use LinkedData\SPARQL\Query\Concerns\BuildsHavingClauses;
use LinkedData\SPARQL\Query\Concerns\BuildsJoins;
use LinkedData\SPARQL\Query\Concerns\BuildsOrderingAndLimits;
use LinkedData\SPARQL\Query\Concerns\BuildsQueryResults;
use LinkedData\SPARQL\Query\Concerns\BuildsSparqlFunctions;
use LinkedData\SPARQL\Query\Concerns\BuildsSparqlQueries;
use LinkedData\SPARQL\Query\Concerns\BuildsSparqlUpdates;
use LinkedData\SPARQL\Query\Concerns\BuildsWhereClauses;
use RuntimeException;

class Builder
{
    use BuildsAggregates;
    use BuildsDataTypes;
    use BuildsDateWhereClauses;
    use BuildsHavingClauses;
    use BuildsJoins;
    use BuildsOrderingAndLimits;
    use BuildsQueries, ForwardsCalls, Macroable {
        __call as macroCall;
    }
    use BuildsQueryResults;
    use BuildsSparqlFunctions;
    use BuildsSparqlQueries;
    use BuildsSparqlUpdates;
    use BuildsWhereClauses;

    /**
     * The database connection instance.
     *
     * @var \Illuminate\Database\ConnectionInterface
     */
    public $connection;

    /**
     * The database query grammar instance.
     *
     * @var \LinkedData\SPARQL\Query\Grammar
     */
    public $grammar;

    /**
     * The database query post processor instance.
     *
     * @var \LinkedData\SPARQL\Query\Processor
     */
    public $processor;

    public $unique_subject;

    public $graph = null;

    /**
     * The Blazegraph namespace for this query.
     *
     * @var string|null
     */
    protected $namespace = null;

    /**
     * The type of query (select, construct, ask, describe).
     *
     * @var string
     */
    public $queryType = 'select';

    /**
     * The CONSTRUCT template for CONSTRUCT queries.
     *
     * @var array|string|null
     */
    public $constructTemplate = null;

    /**
     * The resources for DESCRIBE queries.
     *
     * @var array|null
     */
    public $describeResources = null;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    public $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'having' => [],
        'order' => [],
        'union' => [],
    ];

    /**
     * An aggregate function and column to be run.
     *
     * @var array
     */
    public $aggregate;

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns;

    /**
     * Custom SELECT expressions (e.g., aggregates, computed values).
     *
     * @var array
     */
    public $selectExpressions = [];

    /**
     * Indicates if the query returns distinct results.
     *
     * @var bool
     */
    public $distinct = false;

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    public $from;

    /**
     * The table joins for the query.
     *
     * @var array
     */
    public $joins;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * BIND expressions for the query.
     *
     * @var array
     */
    public $binds = [];

    /**
     * The groupings for the query.
     *
     * @var array
     */
    public $groups;

    /**
     * The having constraints for the query.
     *
     * @var array
     */
    public $havings;

    /**
     * The orderings for the query.
     *
     * @var array
     */
    public $orders;

    /**
     * The maximum number of records to return.
     *
     * @var int
     */
    public $limit;

    /**
     * The number of records to skip.
     *
     * @var int
     */
    public $offset;

    /**
     * The query union statements.
     *
     * @var array
     */
    public $unions;

    /**
     * The maximum number of union records to return.
     *
     * @var int
     */
    public $unionLimit;

    /**
     * The number of union records to skip.
     *
     * @var int
     */
    public $unionOffset;

    /**
     * The orderings for the union query.
     *
     * @var array
     */
    public $unionOrders;

    /**
     * Indicates whether row locking is being used.
     *
     * @var string|bool
     */
    public $lock;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '!=',
        'regex',
    ];

    /**
     * Whether use write pdo for select.
     *
     * @var bool
     */
    public $useWritePdo = false;

    /**
     * The number of seconds to cache the query results.
     *
     * @var int|null
     */
    public $cacheSeconds;

    /**
     * The cache key to use for the query cache.
     *
     * @var string|null
     */
    public $cacheKey;

    /**
     * The named parameter bindings for the query.
     *
     * @var array
     */
    public $namedBindings = [];

    /**
     * Create a new query builder instance.
     *
     * @return void
     */
    public function __construct(
        ConnectionInterface $connection,
        ?Grammar $grammar = null,
        ?Processor $processor = null
    ) {
        $this->connection = $connection;
        $this->grammar = $grammar ?: $connection->getQueryGrammar();
        $this->processor = $processor ?: $connection->getPostProcessor();
        $this->unique_subject = '?__uri_' . Str::random(5);
    }

    private function defaultColumns()
    {
        return [$this->unique_subject];
    }

    public function pushAttribute($attribute, $actual_where = true)
    {
        /*
            If an attribute has already been pushed into the query, here we
            retrieve the existing mapping to the assigned property name.
        */
        foreach ($this->wheres as $where) {
            if (isset($where['column']) && $where['column'] == $attribute && isset($where['value']) && Expression::is($where['value'], 'param')) {
                return $where['value']->getValue();
            }
        }

        $prop = '?' . Str::random(10);
        $prop = Expression::par($prop);

        if ($actual_where) {
            $this->where($attribute, $prop);
        }

        return $prop;
    }

    /**
     * Set the Blazegraph namespace for this query.
     *
     * @param  string  $namespace  The Blazegraph namespace
     * @return $this
     */
    public function namespace(string $namespace): static
    {
        $this->namespace = $namespace;

        // Also set on connection for this query
        $this->connection->namespace($namespace);

        return $this;
    }

    /**
     * Get the Blazegraph namespace for this query.
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function select($columns = false)
    {
        if ($columns === false) {
            $this->columns = $this->defaultColumns();
        } elseif ($columns == '*') {
            $this->whereRaw($this->unique_subject . ' ?prop ?value');
            $this->columns = [$this->unique_subject, '?prop', '?value'];
        } else {
            $this->columns = [];
            $this->columns[] = $this->unique_subject;

            $columns = is_array($columns) ? $columns : func_get_args();
            foreach ($columns as $c) {
                $this->columns[] = $this->pushAttribute($c);
            }
        }

        return $this;
    }

    /**
     * Add a subselect expression to the query.
     *
     * @param  \Closure|\LinkedData\SPARQL\Query\Builder|string  $query
     * @param  string  $as
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function selectSub($query, $as)
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->selectRaw(
            '(' . $query . ') as ' . $this->grammar->wrap($as),
            $bindings
        );
    }

    /**
     * Add a new "raw" select expression to the query.
     *
     * @param  string  $expression
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function selectRaw($expression, array $bindings = [])
    {
        $this->addSelect(new Expression($expression));

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    /**
     * Makes "from" fetch from a subquery.
     *
     * @param  \Closure|\LinkedData\SPARQL\Query\Builder|string  $query
     * @param  string  $as
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function fromSub($query, $as)
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->fromRaw('(' . $query . ') as ' . $this->grammar->wrapUri($as), $bindings);
    }

    /**
     * Add a raw from clause to the query.
     *
     * @param  string  $expression
     * @param  mixed  $bindings
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function fromRaw($expression, $bindings = [])
    {
        $this->from = new Expression($expression);

        $this->addBinding($bindings, 'from');

        return $this;
    }

    /**
     * Creates a subquery and parse it.
     *
     * @param  \Closure|\LinkedData\SPARQL\Query\Builder|string  $query
     * @return array
     */
    protected function createSub($query)
    {
        // If the given query is a Closure, we will execute it while passing in a new
        // query instance to the Closure. This will give the developer a chance to
        // format and work with the query before we cast it to a raw SQL string.
        if ($query instanceof Closure) {
            $callback = $query;

            $callback($query = $this->forSubQuery());
        }

        return $this->parseSub($query);
    }

    /**
     * Parse the subquery into SQL and bindings.
     *
     * @param  mixed  $query
     * @return array
     */
    protected function parseSub($query)
    {
        if ($query instanceof self || $query instanceof EloquentBuilder) {
            return [$query->toSql(), $query->getBindings()];
        } elseif (is_string($query)) {
            return [$query, []];
        } else {
            throw new InvalidArgumentException;
        }
    }

    /**
     * Add a new select column to the query.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function addSelect($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if (empty($this->columns)) {
            $this->columns = [$this->unique_subject];
        }

        foreach ($columns as $c) {
            if (Expression::is($c, 'param')) {
                $this->columns[] = $c;
            } else {
                $this->columns[] = $this->pushAttribute($c);
            }
        }

        return $this;
    }

    /**
     * Add a custom SELECT expression (e.g., aggregate, computed value).
     * This is separate from triple patterns and will be used in SELECT clause.
     *
     * @param  string|Expression|\Illuminate\Database\Query\Expression  $expression
     * @return $this
     */
    public function selectExpression($expression)
    {
        // Handle Laravel DB::raw() expressions
        if ($expression instanceof \Illuminate\Database\Query\Expression) {
            // Store the raw Laravel expression directly, not wrapped
            $this->selectExpressions[] = $expression;
        } elseif (! $expression instanceof Expression) {
            $expression = new Expression($expression);
            $this->selectExpressions[] = $expression;
        } else {
            $this->selectExpressions[] = $expression;
        }

        return $this;
    }

    /**
     * Add an explicit triple pattern to the WHERE clause.
     *
     * @param  string|Expression  $subject
     * @param  string|Expression  $predicate
     * @param  string|Expression  $object
     * @return $this
     */
    public function whereTriple($subject, $predicate, $object)
    {
        if (! $subject instanceof Expression) {
            $subject = Expression::par($subject);
        }

        // For predicates: keep as-is if it's a string (prefixes will be expanded by Grammar)
        // or use the Expression if provided
        if (! $predicate instanceof Expression) {
            $predicate = new Expression($predicate);
        }

        if (! $object instanceof Expression) {
            $object = Expression::par($object);
        }

        $this->wheres[] = [
            'type' => 'Triple',
            'subject' => $subject,
            'predicate' => $predicate,
            'object' => $object,
            'boolean' => 'and',
        ];

        return $this;
    }

    /**
     * Add a BIND expression to the query.
     *
     * @param  string|Expression  $expression  The SPARQL expression to bind
     * @param  string  $variable  Variable name (with or without ?)
     * @return $this
     */
    public function bind($expression, $variable)
    {
        // Don't wrap expression in Expression if it's already a string or Expression
        // This allows raw SPARQL expressions to pass through
        if (! $expression instanceof Expression && ! is_string($expression)) {
            $expression = new Expression($expression, 'raw');
        } elseif (is_string($expression)) {
            // Keep string expressions as-is (raw SPARQL)
            $expression = new Expression($expression, 'raw');
        }

        // Ensure variable starts with ?
        if (! str_starts_with($variable, '?')) {
            $variable = '?' . $variable;
        }

        $this->binds[] = [
            'expression' => $expression,
            'variable' => $variable,
        ];

        return $this;
    }

    /**
     * Wrap a URI/IRI for use in queries.
     *
     * @param  string  $uri
     * @return string
     */
    protected function wrapUri($uri)
    {
        return $this->grammar->wrapUri($uri);
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct()
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $table
     * @return $this
     */
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  array  ...$groups
     * @return $this
     */
    public function groupBy(...$groups)
    {
        $groups = Arr::wrap($groups);
        foreach ($groups as $group) {
            // If it's already a variable (starts with ?), use it as-is
            if (is_string($group) && str_starts_with($group, '?')) {
                $this->groups[] = Expression::par($group);
            } elseif ($group instanceof Expression) {
                $this->groups[] = $group;
            } else {
                $this->groups[] = $this->pushAttribute($group);
            }
        }

        return $this;
    }

    /**
     * Add a union statement to the query.
     *
     * @param  \LinkedData\SPARQL\Query\Builder|\Closure  $query
     * @param  bool  $all
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function union($query, $all = false)
    {
        if ($query instanceof Closure) {
            call_user_func($query, $query = $this->newQuery());
        }

        $this->unions[] = compact('query', 'all');

        $this->addBinding($query->getBindings(), 'union');

        return $this;
    }

    /**
     * Add a union all statement to the query.
     *
     * @param  \LinkedData\SPARQL\Query\Builder|\Closure  $query
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function unionAll($query)
    {
        return $this->union($query, true);
    }

    /**
     * Lock the selected rows in the table.
     *
     * @param  string|bool  $value
     * @return $this
     */
    public function lock($value = true)
    {
        $this->lock = $value;

        if (! is_null($this->lock)) {
            $this->useWritePdo();
        }

        return $this;
    }

    /**
     * Lock the selected rows in the table for updating.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function lockForUpdate()
    {
        return $this->lock(true);
    }

    /**
     * Share lock the selected rows in the table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function sharedLock()
    {
        return $this->lock(false);
    }

    /**
     * Get the SPARQL representation of the query.
     *
     * @return string
     */
    public function toSparql()
    {
        switch ($this->queryType) {
            case 'construct':
                return $this->grammar->compileConstruct($this);
            case 'ask':
                return $this->grammar->compileAsk($this);
            case 'describe':
                return $this->grammar->compileDescribe($this);
            case 'select':
            default:
                return $this->grammar->compileSelect($this);
        }
    }

    /**
     * Convenient alias for toSparql().
     *
     * @return string
     */
    public function toSql()
    {
        return $this->toSparql();
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  int|string  $id
     * @param  array  $columns
     * @return mixed|static
     */
    public function find($id, $columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        return $this->where($this->unique_subject, '=', $id)->first($columns);
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function value($column)
    {
        $result = (array) $this->first([$column]);

        return count($result) > 0 ? reset($result) : null;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        } else {
            $wrapper_columns = [];
            $columns = Arr::wrap($columns);
            foreach ($columns as $c) {
                if (! $c instanceof Expression) {
                    $wrapper_columns[] = $this->pushAttribute($c);
                }
            }

            $columns = $wrapper_columns;
        }

        return collect($this->onceWithColumns(Arr::wrap($columns), function () {
            return $this->processor->processSelect($this, $this->runSelect());
        }));
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        return $this->connection->select(
            $this->toSql(),
            $this->getBindings(),
            ! $this->useWritePdo
        );
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = 15, $columns = false, $pageName = 'page', $page = null)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $total = $this->getCountForPagination();

        $results = $total ? $this->forPage($page, $perPage)->get($columns) : collect();

        return $this->paginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * This is more efficient on larger data-sets, etc.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginate($perPage = 15, $columns = false, $pageName = 'page', $page = null)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $this->skip(($page - 1) * $perPage)->take($perPage + 1);

        return $this->simplePaginator($this->get($columns), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param  array  $columns
     * @return int
     */
    public function getCountForPagination($columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        $results = $this->runPaginationCountQuery($columns);

        // Once we have run the pagination count query, we will get the resulting count and
        // take into account what type of query it was. When there is a group by we will
        // just return the count of the entire results set since that will be correct.
        if (isset($this->groups)) {
            return count($results);
        } elseif (! isset($results[0])) {
            return 0;
        } elseif (is_object($results[0])) {
            return (int) $results[0]->aggregate;
        }

        return (int) array_change_key_case((array) $results[0])['aggregate'];
    }

    /**
     * Run a pagination count query.
     *
     * @param  array  $columns
     * @return array
     */
    protected function runPaginationCountQuery($columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        $without = $this->unions ? ['orders', 'limit', 'offset'] : ['columns', 'orders', 'limit', 'offset'];

        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get()->all();
    }

    /**
     * Remove the column aliases since they will break count queries.
     *
     * @return array
     */
    protected function withoutSelectAliases(array $columns)
    {
        return array_map(function ($column) {
            return is_string($column) && ($aliasPosition = stripos($column, ' as ')) !== false
                    ? substr($column, 0, $aliasPosition) : $column;
        }, $columns);
    }

    /**
     * Get a generator for the given query.
     *
     * @return \Generator
     */
    public function cursor()
    {
        if (is_null($this->columns)) {
            $this->columns = $this->defaultColumns();
        }

        return $this->connection->cursor(
            $this->toSql(),
            $this->getBindings(),
            ! $this->useWritePdo
        );
    }

    /**
     * Chunk the results of a query by comparing numeric IDs.
     *
     * @param  int  $count
     * @param  string  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function chunkById($count, callable $callback, $column = 'id', $alias = null)
    {
        $alias = $alias ?: $column;

        $lastId = null;

        do {
            $clone = clone $this;

            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $clone->forPageAfterId($count, $lastId, $column)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results) === false) {
                return false;
            }

            $lastId = $results->last()->{$alias};

            unset($results);
        } while ($countResults == $count);

        return true;
    }

    /**
     * Throw an exception if the query doesn't have an orderBy clause.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function enforceOrderBy()
    {
        if (empty($this->orders) && empty($this->unionOrders)) {
            throw new RuntimeException('You must specify an orderBy clause when using this function.');
        }
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection
     */
    public function pluck($column, $key = null)
    {
        // First, we will need to select the results of the query accounting for the
        // given columns / key. Once we have the results, we will be able to take
        // the results and get the exact data that was requested for the query.
        $queryResult = $this->onceWithColumns(
            is_null($key) ? [$column] : [$column, $key],
            function () {
                return $this->processor->processSelect(
                    $this,
                    $this->runSelect()
                );
            }
        );

        if (empty($queryResult)) {
            return collect();
        }

        // If the columns are qualified with a table or have an alias, we cannot use
        // those directly in the "pluck" operations since the results from the DB
        // are only keyed by the column itself. We'll strip the table out here.
        $column = $this->stripTableForPluck($column);

        $key = $this->stripTableForPluck($key);

        return is_array($queryResult[0])
                    ? $this->pluckFromArrayColumn($queryResult, $column, $key)
                    : $this->pluckFromObjectColumn($queryResult, $column, $key);
    }

    /**
     * Strip off the table name or alias from a column identifier.
     *
     * @param  string  $column
     * @return string|null
     */
    protected function stripTableForPluck($column)
    {
        return is_null($column) ? $column : Arr::last(preg_split('~\.| ~', $column));
    }

    /**
     * Retrieve column values from rows represented as objects.
     *
     * @param  array  $queryResult
     * @param  string  $column
     * @param  string  $key
     * @return \Illuminate\Support\Collection
     */
    protected function pluckFromObjectColumn($queryResult, $column, $key)
    {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row->$column;
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row->$key] = $row->$column;
            }
        }

        return collect($results);
    }

    /**
     * Retrieve column values from rows represented as arrays.
     *
     * @param  array  $queryResult
     * @param  string  $column
     * @param  string  $key
     * @return \Illuminate\Support\Collection
     */
    protected function pluckFromArrayColumn($queryResult, $column, $key)
    {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row[$column];
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row[$key]] = $row[$column];
            }
        }

        return collect($results);
    }

    public function first($columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        return $this->take(1)->get($columns)->first();
    }

    /**
     * Concatenate values of a given column as a string.
     *
     * @param  string  $column
     * @param  string  $glue
     * @return string
     */
    public function implode($column, $glue = '')
    {
        return $this->pluck($column)->implode($glue);
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        $results = $this->connection->select(
            $this->grammar->compileExists($this),
            $this->getBindings(),
            ! $this->useWritePdo
        );

        // If the results has rows, we will get the row and see if the exists column is a
        // boolean true. If there is no results for this query we will return false as
        // there are no rows for this query at all and we can return that info here.
        if (isset($results[0])) {
            $results = (array) $results[0];

            return (bool) $results['exists'];
        }

        return false;
    }

    /**
     * Determine if no rows exist for the current query.
     *
     * @return bool
     */
    public function doesntExist()
    {
        return ! $this->exists();
    }

    /**
     * Insert a new record into the database.
     *
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings(Arr::flatten($values, 1))
        );
    }

    /**
     * Insert a new record into the database while ignoring errors.
     *
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        if (empty($values)) {
            return 0;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertOrIgnore($this, $values),
            $this->cleanBindings(Arr::flatten($values, 1))
        );
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  string|null  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }

    /**
     * Insert new records into the table using a subquery.
     *
     * @param  \Closure|\LinkedData\SPARQL\Query\Builder|string  $query
     * @return bool
     */
    public function insertUsing(array $columns, $query)
    {
        [$sql, $bindings] = $this->createSub($query);

        return $this->connection->insert(
            $this->grammar->compileInsertUsing($this, $columns, $sql),
            $this->cleanBindings($bindings)
        );
    }

    /**
     * Update a record in the database.
     *
     * @return int
     */
    public function update(array $values)
    {
        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($sql, $this->cleanBindings(
            $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
        ));
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     *
     * @return bool
     */
    public function updateOrInsert(array $attributes, array $values = [])
    {
        if (! $this->where($attributes)->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (bool) $this->take(1)->update($values);
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped + $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped - $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (! is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }

        return $this->connection->delete(
            $this->grammar->compileDelete($this),
            $this->cleanBindings(
                $this->grammar->prepareBindingsForDelete($this->bindings)
            )
        );
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate()
    {
        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return \LinkedData\SPARQL\Query\Builder
     */
    public function newQuery()
    {
        return new static($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Create a new query instance for a sub-query.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function forSubQuery()
    {
        return $this->newQuery();
    }

    /**
     * Create a raw database expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Contracts\Database\Query\Expression
     */
    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings()
    {
        return Arr::flatten($this->bindings);
    }

    /**
     * Get the raw array of bindings.
     *
     * @return array
     */
    public function getRawBindings()
    {
        return $this->bindings;
    }

    /**
     * Set the bindings on the query builder.
     *
     * @param  string  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setBindings(array $bindings, $type = 'where')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Add a binding to the query.
     *
     * @param  mixed  $value
     * @param  string  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function addBinding($value, $type = 'where')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $value = Arr::wrap($value);
        array_map(function ($a) {
            if (is_a($a, Expression::class) == false) {
                return new Expression($a);
            } else {
                return $a;
            }
        }, $value);

        $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));

        return $this;
    }

    /**
     * Merge an array of bindings into our bindings.
     *
     * @return $this
     */
    public function mergeBindings(self $query)
    {
        $this->bindings = array_merge_recursive($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * Remove all of the expressions from a list of bindings.
     *
     * @return array
     */
    protected function cleanBindings(array $bindings)
    {
        return array_values(array_filter($bindings, function ($binding) {
            return ! $binding instanceof Expression;
        }));
    }

    /**
     * Add a named binding to the query.
     *
     * Named bindings allow using associative arrays for parameter binding,
     * making queries more readable and maintainable.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @param  string  $type
     * @return $this
     */
    public function addNamedBinding($name, $value, $type = 'where')
    {
        if (! isset($this->namedBindings)) {
            $this->namedBindings = [];
        }

        $this->namedBindings[$name] = $value;

        // Also add to regular bindings for compatibility
        return $this->addBinding($value, $type);
    }

    /**
     * Set multiple named bindings at once.
     *
     * @param  string  $type
     * @return $this
     */
    public function setNamedBindings(array $bindings, $type = 'where')
    {
        foreach ($bindings as $name => $value) {
            $this->addNamedBinding($name, $value, $type);
        }

        return $this;
    }

    /**
     * Get a named binding value.
     *
     * @param  string  $name
     * @param  mixed  $default
     * @return mixed
     */
    public function getNamedBinding($name, $default = null)
    {
        return $this->namedBindings[$name] ?? $default;
    }

    /**
     * Get all named bindings.
     *
     * @return array
     */
    public function getNamedBindings()
    {
        return $this->namedBindings;
    }

    /**
     * Execute a raw query with named parameter bindings.
     *
     * This allows using :name syntax in queries for better readability.
     *
     * @param  string  $query
     * @return mixed
     */
    public function selectWithNamedBindings($query, array $bindings = [])
    {
        // Convert named bindings (:name) to positional bindings (?)
        $positionalBindings = [];
        $convertedQuery = preg_replace_callback(
            '/:(\w+)/',
            function ($matches) use ($bindings, &$positionalBindings) {
                $name = $matches[1];
                if (array_key_exists($name, $bindings)) {
                    $positionalBindings[] = $bindings[$name];

                    return '?';
                }

                return $matches[0]; // Keep original if no binding found
            },
            $query
        );

        return $this->connection->select($convertedQuery, $positionalBindings, ! $this->useWritePdo);
    }

    /**
     * Add a raw where clause with named bindings.
     *
     * @param  string  $sql
     * @param  string  $boolean
     * @return $this
     */
    public function whereRawNamed($sql, array $bindings = [], $boolean = 'and')
    {
        // Store the named bindings
        foreach ($bindings as $name => $value) {
            $this->addNamedBinding($name, $value);
        }

        // Convert named parameters to positional
        $positionalBindings = [];
        $convertedSql = preg_replace_callback(
            '/:(\w+)/',
            function ($matches) use ($bindings, &$positionalBindings) {
                $name = $matches[1];
                if (array_key_exists($name, $bindings)) {
                    $positionalBindings[] = $bindings[$name];

                    return '?';
                }

                return $matches[0];
            },
            $sql
        );

        return $this->whereRaw($convertedSql, $positionalBindings, $boolean);
    }

    /**
     * Get the database connection instance.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the database query processor instance.
     *
     * @return \LinkedData\SPARQL\Query\Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Get the query grammar instance.
     *
     * @return \LinkedData\SPARQL\Query\Grammar
     */
    public function getGrammar()
    {
        return $this->grammar;
    }

    /**
     * Use the write pdo for query.
     *
     * @return $this
     */
    public function useWritePdo()
    {
        $this->useWritePdo = true;

        return $this;
    }

    /**
     * Clone the query without the given properties.
     *
     * @return static
     */
    public function cloneWithout(array $properties)
    {
        return tap(clone $this, function ($clone) use ($properties) {
            foreach ($properties as $property) {
                $clone->{$property} = null;
            }
        });
    }

    /**
     * Clone the query without the given bindings.
     *
     * @return static
     */
    public function cloneWithoutBindings(array $except)
    {
        return tap(clone $this, function ($clone) use ($except) {
            foreach ($except as $type) {
                $clone->bindings[$type] = [];
            }
        });
    }

    /**
     * Dump the current SQL and bindings.
     *
     * @return $this
     */
    public function dump()
    {
        dump($this->toSql(), $this->getBindings());

        return $this;
    }

    /**
     * Die and dump the current SQL and bindings.
     *
     * @return void
     */
    public function dd()
    {
        dd($this->toSql(), $this->getBindings());
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (Str::startsWith($method, 'where')) {
            return $this->dynamicWhere($method, $parameters);
        }

        static::throwBadMethodCallException($method);
    }
}
