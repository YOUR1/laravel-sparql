<?php

namespace LinkedData\SPARQL\Query;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LinkedData\SPARQL\Grammar as BaseGrammar;
use RuntimeException;

class Grammar extends BaseGrammar
{
    /**
     * The grammar specific operators.
     *
     * @var array
     */
    protected $operators = [];

    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    /**
     * Compile a select query into SQL.
     *
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if ($query->unions && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = [$query->unique_subject];
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $sql = trim(
            $this->concatenate(
                $this->compileComponents($query)
            )
        );

        $query->columns = $original;

        return $sql;
    }

    /**
     * Compile a CONSTRUCT query into SPARQL.
     *
     * @return string
     */
    public function compileConstruct(Builder $query)
    {
        // Build the CONSTRUCT template
        $template = $this->compileConstructTemplate($query);

        // Build the WHERE clause using existing components
        $original = $query->columns;
        if (is_null($query->columns)) {
            $query->columns = [$query->unique_subject];
        }

        $where = $this->compileWhereClauseForConstruct($query);

        $query->columns = $original;

        // Add graph, limit, offset if needed
        $graph = $query->graph ? $this->compileGraph($query) : '';
        $limit = isset($query->limit) ? $this->compileLimit($query, $query->limit) : '';
        $offset = isset($query->offset) ? $this->compileOffset($query, $query->offset) : '';

        return trim("construct {$template} {$graph} {$where} {$limit} {$offset}");
    }

    /**
     * Compile an ASK query into SPARQL.
     *
     * @return string
     */
    public function compileAsk(Builder $query)
    {
        // Build the WHERE clause using existing components
        $where = '';
        if (! empty($query->wheres)) {
            $where = $this->compileWheres($query);
        }

        $graph = $query->graph ? $this->compileGraph($query) : '';

        return trim("ask {$graph} {$where}");
    }

    /**
     * Compile a DESCRIBE query into SPARQL.
     *
     * @return string
     */
    public function compileDescribe(Builder $query)
    {
        // Build the resource list
        $resources = '';
        if (is_null($query->describeResources)) {
            // If no resources specified, describe the subject
            $resources = $query->unique_subject;
        } else {
            // Otherwise, describe the specified resources
            $resources = implode(' ', array_map(function ($resource) {
                if (Expression::is($resource, 'iri')) {
                    return $resource;
                } elseif (Expression::is($resource, 'param')) {
                    return $resource;
                } else {
                    return $this->wrapUri($resource);
                }
            }, $query->describeResources));
        }

        // Build optional WHERE clause
        $where = '';
        if (! empty($query->wheres)) {
            $where = $this->compileWheres($query);
        }

        $graph = $query->graph ? $this->compileGraph($query) : '';

        return trim("describe {$resources} {$graph} {$where}");
    }

    /**
     * Compile the CONSTRUCT template.
     *
     * @return string
     */
    protected function compileConstructTemplate(Builder $query)
    {
        if (is_string($query->constructTemplate)) {
            // Raw SPARQL template
            return "{ {$query->constructTemplate} }";
        }

        // Build template from array of triples
        $triples = [];
        foreach ($query->constructTemplate as $triple) {
            if (is_string($triple)) {
                $triples[] = $triple;
            } elseif (is_array($triple) && count($triple) === 3) {
                // Format: [subject, predicate, object]
                $triples[] = sprintf('%s %s %s', $triple[0], $triple[1], $triple[2]);
            }
        }

        return '{ ' . implode(' . ', $triples) . ' }';
    }

    /**
     * Compile WHERE clause for CONSTRUCT queries.
     *
     * @return string
     */
    protected function compileWhereClauseForConstruct(Builder $query)
    {
        $components = $this->compileComponents($query);

        // Remove the construct template from components
        unset($components['columns']);

        return trim($this->concatenate($components));
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            // To compile the query, we'll spin through each component of the query and
            // see if that component exists. If it does we'll just call the compiler
            // function for the component which is responsible for making the SQL.
            if (isset($query->$component) && ! is_null($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        // Handle GROUP_CONCAT with separator
        $function = $aggregate['function'];
        if (str_starts_with($function, 'group_concat_separator_')) {
            $separator = substr($function, strlen('group_concat_separator_'));

            return 'select (group_concat(' . $column . '; separator="' . $separator . '") as ?aggregate) ' . $this->compileGraph($query);
        }

        return 'select (' . $aggregate['function'] . '(' . $column . ') as ?aggregate) ' . $this->compileGraph($query);
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (! is_null($query->aggregate)) {
            return null;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select . $this->columnize($columns) . $this->compileGraph($query);
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  string  $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        if (! empty($table)) {
            $query->where('rdf:type', Expression::iri($table));
        }

        return '';
    }

    /**
     * Compile the "join" portions of the query.
     *
     * @param  array  $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
        return collect($joins)->map(function ($join) use ($query) {
            $table = $this->wrapUri($join->table);

            $nestedJoins = is_null($join->joins) ? '' : ' ' . $this->compileJoins($query, $join->joins);

            $tableAndNestedJoins = is_null($join->joins) ? $table : '(' . $table . $nestedJoins . ')';

            return trim("{$join->type} join {$tableAndNestedJoins} {$this->compileWheres($join)}");
        })->implode(' ');
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @return string
     */
    protected function compileWheres(Builder $query)
    {
        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($query->wheres)) {
            return '';
        }

        // If we actually have some where clauses, we will strip off the first boolean
        // operator, which is added by the query builders for convenience so we can
        // avoid checking for the first clauses in each of the compilers methods.
        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    protected function createFilter($where)
    {
        switch ($where['operator']) {
            case '=':
                return [
                    'type' => 'Basic',
                    'attribute' => $where['column'],
                    'value' => $where['values'],
                    'boolean' => 'and',
                ];

                break;

            case 'in':
            case 'not in':
            case 'between':
            case 'not between':
                return [
                    'type' => str_replace(' ', '', ucwords($where['operator'])),
                    'attribute' => $where['column'],
                    'values' => $where['values'],
                    'boolean' => 'and',
                ];

                break;

            case 'like':
                $where['value'] = str_replace('%%', '####', $where['value']);
                $where['value'] = str_replace('%', '', $where['value']);
                $where['value'] = str_replace('####', '%', $where['value']);

            case 'regex':
                return [
                    'type' => 'Regex',
                    'attribute' => $where['column'],
                    'value' => $this->wrapValue($where['value']),
                    'boolean' => 'and',
                ];

                break;

            default:
                return [
                    'type' => 'Basic',
                    'attribute' => $where['column'],
                    'operator' => $where['operator'],
                    'value' => $where['value'],
                    'boolean' => 'and',
                ];

                break;
        }
    }

    /**
     * Get an array of all the where clauses for the query.
     *
     * @param  \LinkedData\SPARQL\Query\Builder  $query
     * @return array
     */
    protected function compileWheresToArray($query)
    {
        $sorted_where = [];

        /*
            Where conditions referring to a parameter are aggregated as filters
            into other where conditions having that same parameter as value
        */
        foreach ($query->wheres as $where) {
            if (! isset($where['filters'])) {
                $where['filters'] = [];
            }

            if (Expression::is($where['column'] ?? null, 'param')) {
                foreach ($sorted_where as $index => $s) {
                    if (Expression::same($s['value'] ?? null, $where['column'])) {
                        $sorted_where[$index]['filters'][] = $this->createFilter($where);
                        break;
                    }
                }
            } else {
                $sorted_where[] = $where;
            }
        }

        return collect($sorted_where)->map(function ($where) use ($query) {
            if (! isset($where['boolean'])) {
                return ' . ' . $this->compileFilters($query, $where);
            } else {
                switch ($where['boolean']) {
                    case 'or':
                        $boolean = 'union';
                        break;
                    default:
                        $boolean = ' . ';
                        break;
                }

                return $boolean . ' { ' . $this->{"where{$where['type']}"}($query, $where) . ' }';
            }
        })->all();
    }

    /**
     * Format the where clause statements into one string.
     *
     * @param  \LinkedData\SPARQL\Query\Builder  $query
     * @param  array  $sql
     * @return string
     */
    protected function concatenateWhereClauses($query, $sql)
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'WHERE';

        if (count($query->wheres) > 1) {
            return $conjunction . ' { ' . $this->removeLeadingBoolean(implode(' ', $sql)) . ' }';
        } else {
            return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
        }
    }

    /**
     * Compile a raw where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereRaw(Builder $query, $where)
    {
        return $where['sql'] . $this->compileFilters($query, $where);
    }

    /**
     * Compile a basic where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
        switch ($where['operator']) {
            case '=':
                // If the value is an Expression (like IRI), use it directly in the triple pattern
                // This is especially important for rdf:type constraints
                if ($where['value'] instanceof Expression) {
                    $value = $this->parameter($where['value']);
                } else {
                    $value = '?';
                }
                break;

            case 'in':
            case 'not in':
            case 'between':
            case 'not between':
                $val = $query->pushAttribute($where['column'], false);

                $where['filters'][] = [
                    'type' => str_replace(' ', '', ucwords($where['operator'])),
                    'attribute' => $val,
                    'values' => $where['values'],
                    'boolean' => 'and',
                ];

                $value = $val;
                break;

            case 'like':
                $where['value'] = str_replace('%%', '####', $where['value']);
                $where['value'] = str_replace('%', '', $where['value']);
                $where['value'] = str_replace('####', '%', $where['value']);

            case 'regex':
                $val = $query->pushAttribute($where['column'], false);

                $where['filters'][] = [
                    'type' => 'Regex',
                    'attribute' => $val,
                    'value' => $this->wrapValue($where['value']),
                    'boolean' => 'and',
                ];

                $value = $val;
                break;

            default:
                $val = $query->pushAttribute($where['column'], false);
                $value = $this->parameter($where['value']);

                $where['filters'][] = [
                    'type' => 'Basic',
                    'attribute' => $val,
                    'operator' => $where['operator'],
                    'value' => $value,
                    'boolean' => 'and',
                ];

                $value = $val;
                break;
        }

        return $query->unique_subject . ' ' . $where['column'] . ' ' . $value . $this->compileFilters($query, $where);
    }

    protected function whereReversed(Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return $value . ' ' . $where['column'] . ' ' . $query->unique_subject . $this->compileFilters($query, $where);
    }

    /**
     * Compile a "where not in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotInRaw(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . implode(', ', $where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * Compile a where in sub-select clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereInSub(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' in (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * Compile a where not in sub-select clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotInSub(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' not in (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * Compile a "where in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereInRaw(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . implode(', ', $where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNull(Builder $query, $where)
    {
        $void = '?' . Str::random(10);

        return 'FILTER NOT EXISTS { ' . $query->unique_subject . ' ' . $this->wrap($where['column']) . ' ' . $void . ' }';
    }

    /**
     * Compile a "where not null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotNull(Builder $query, $where)
    {
        $void = '?' . Str::random(10);

        return 'FILTER EXISTS { ' . $query->unique_subject . ' ' . $this->wrap($where['column']) . ' ' . $void . ' }';
    }

    /**
     * Compile a "where date" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereDate(Builder $query, $where)
    {
        return $this->dateBasedWhere('date', $query, $where);
    }

    /**
     * Compile a "where time" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereTime(Builder $query, $where)
    {
        return $this->dateBasedWhere('time', $query, $where);
    }

    /**
     * Compile a "where day" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereDay(Builder $query, $where)
    {
        return $this->dateBasedWhere('day', $query, $where);
    }

    /**
     * Compile a "where month" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('month', $query, $where);
    }

    /**
     * Compile a "where year" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereYear(Builder $query, $where)
    {
        return $this->dateBasedWhere('year', $query, $where);
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  array  $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return $type . '(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
    }

    /**
     * Compile a where clause comparing two columns..
     *
     * @param  array  $where
     * @return string
     */
    protected function whereColumn(Builder $query, $where)
    {
        return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
    }

    /**
     * Compile a nested where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNested(Builder $query, $where)
    {
        // Here we will calculate what portion of the string we need to remove. If this
        // is a join clause query, we need to remove the "on" portion of the SQL and
        // if it is a normal query we need to take the leading "where" of queries.
        $offset = $query instanceof JoinClause ? 3 : 6;

        return ' { ' . substr($this->compileWheres($where['query']), $offset) . ' } ';
    }

    /**
     * Compile a where condition with a sub-select.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . " ($select)";
    }

    /**
     * Compile an OPTIONAL graph pattern clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereOptional(Builder $query, $where)
    {
        // Similar to whereNested but wrapped with OPTIONAL keyword
        $offset = 6; // Remove leading "where "

        return ' OPTIONAL { ' . substr($this->compileWheres($where['query']), $offset) . ' } ';
    }

    /**
     * Compile a MINUS graph pattern clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereMinus(Builder $query, $where)
    {
        // Similar to whereOptional but wrapped with MINUS keyword
        $offset = 6; // Remove leading "where "

        return ' MINUS { ' . substr($this->compileWheres($where['query']), $offset) . ' } ';
    }

    /**
     * Compile a BIND expression clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereBind(Builder $query, $where)
    {
        return ' BIND(' . $where['expression'] . ' AS ' . $where['variable'] . ') ';
    }

    /**
     * Compile a VALUES data block clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereValues(Builder $query, $where)
    {
        $variables = implode(' ', $where['variables']);

        $valueRows = [];
        foreach ($where['values'] as $row) {
            if (! is_array($row)) {
                // Single value - format it properly
                $formatted = $this->formatValueForValues($row);
                $valueRows[] = '( ' . $formatted . ' )';
            } else {
                // Multiple values
                $vals = array_map(fn ($val) => $this->formatValueForValues($val), $row);
                $valueRows[] = '( ' . implode(' ', $vals) . ' )';
            }
        }

        return ' VALUES (' . $variables . ') { ' . implode(' ', $valueRows) . ' } ';
    }

    /**
     * Format a value for use in VALUES clause.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function formatValueForValues($value)
    {
        if ($value instanceof Expression) {
            return $this->getValue($value);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            // Check if it's a variable
            if (str_starts_with($value, '?') || str_starts_with($value, '$')) {
                return $value;
            }

            // Quote string literals
            return '"' . addslashes($value) . '"';
        }

        return '"' . addslashes((string) $value) . '"';
    }

    /**
     * Compile a SERVICE clause for federated queries.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereService(Builder $query, $where)
    {
        $offset = 6; // Remove leading "where "
        $serviceUri = $this->wrapUri($where['serviceUri']);

        return ' SERVICE ' . $serviceUri . ' { ' . substr($this->compileWheres($where['query']), $offset) . ' } ';
    }

    /**
     * Compile a property path clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function wherePropertyPath(Builder $query, $where)
    {
        // Property paths use the path expression directly without namespace expansion
        $subject = $query->unique_subject;
        $path = $where['column']; // This is the property path expression
        $value = $this->parameter($where['value']);

        // Property paths are always used in triple patterns
        return "{$subject} {$path} {$value}";
    }

    /**
     * Compile a where exists clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereExists(Builder $query, $where)
    {
        $ret = [];

        foreach ($where['query']->columns as $column) {
            if ($column != $where['query']->unique_subject) {
                $ret[] = sprintf('FILTER EXISTS { %s %s %s }', $query->unique_subject, $column, $where['query']->unique_subject);
            }
        }

        $query->addBinding($where['query']->getBindings(), 'where');

        return implode(' . ', $ret);
    }

    /**
     * Compile a where exists clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotExists(Builder $query, $where)
    {
        $ret = [];

        foreach ($where['query']->columns as $column) {
            $ret[] = sprintf('FILTER NOT EXISTS { %s %s %s }', $query->unique_subject, $column, $where['query']->unique_subject);
        }

        return implode(' . ', $ret);
    }

    /**
     * Compile a where row values condition.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereRowValues(Builder $query, $where)
    {
        $columns = $this->columnize($where['columns']);

        $values = $this->parameterize($where['values']);

        return '(' . $columns . ') ' . $where['operator'] . ' (' . $values . ')';
    }

    protected function compileGraph($query)
    {
        $graph = $query->getGraph();
        if ($graph) {
            return ' FROM ' . $this->wrapUri($graph);
        } else {
            return '';
        }
    }

    protected function compileFilters($query, $where)
    {
        if (! isset($where['filters']) || empty($where['filters'])) {
            return '';
        }

        if (count($sql = $this->compileFiltersToArray($query, $where)) > 0) {
            return $this->concatenateFilterClauses($query, $where, $sql);
        }

        return '';
    }

    protected function compileFiltersToArray($query, $where)
    {
        return collect($where['filters'])->map(function ($filter) use ($query, $where) {
            return $filter['boolean'] . ' ' . $this->{"filter{$filter['type']}"}($query, $where, $filter);
        })->all();
    }

    protected function concatenateFilterClauses($query, $where, $sql)
    {
        return ' . FILTER ( ' . $this->removeLeadingBoolean(implode(' ', $sql)) . ' )';
    }

    protected function filterBasic(Builder $query, $where, $filter)
    {
        $value = $this->parameter($filter['value']);

        return $this->wrap($filter['attribute']) . ' ' . $filter['operator'] . ' ' . $value;
    }

    protected function filterIn(Builder $query, $where, $filter)
    {
        $values = $this->parameterize($filter['values']);

        return $this->wrap($filter['attribute']) . ' IN ( ' . $values . ' )';
    }

    protected function filterNotIn(Builder $query, $where, $filter)
    {
        $values = $this->parameterize($filter['values']);

        return $this->wrap($filter['attribute']) . ' NOT IN ( ' . $values . ' )';
    }

    protected function filterBetween(Builder $query, $where, $filter)
    {
        $min = $this->parameter(reset($where['values']));
        $max = $this->parameter(end($where['values']));

        return sprintf('%s >= %s && %s <= %s', $this->wrap($filter['attribute']), $min, $this->wrap($filter['attribute']), $max);
    }

    protected function filterNotBetween(Builder $query, $where, $filter)
    {
        $min = $this->parameter(reset($where['values']));
        $max = $this->parameter(end($where['values']));

        return sprintf('%s < %s || %s > %s', $this->wrap($filter['attribute']), $min, $this->wrap($filter['attribute']), $max);
    }

    protected function filterRegex(Builder $query, $where, $filter)
    {
        return sprintf(' REGEX ( %s, ? ) ', $this->wrap($filter['attribute']));
    }

    /**
     * Compile the "group by" portions of the query.
     *
     * @param  array  $groups
     * @return string
     */
    protected function compileGroups(Builder $query, $groups)
    {
        return 'group by ' . $this->columnize($groups);
    }

    /**
     * Compile the "having" portions of the query.
     *
     * @param  array  $havings
     * @return string
     */
    protected function compileHavings(Builder $query, $havings)
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause.
     *
     * @return string
     */
    protected function compileHaving(array $having)
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ($having['type'] === 'Raw') {
            return $having['boolean'] . ' ' . $having['sql'];
        } elseif ($having['type'] === 'between') {
            return $this->compileHavingBetween($having);
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     *
     * @param  array  $having
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    /**
     * Compile a "between" having clause.
     *
     * @param  array  $having
     * @return string
     */
    protected function compileHavingBetween($having)
    {
        $between = $having['not'] ? 'not between' : 'between';

        $column = $this->wrap($having['column']);

        $min = $this->parameter(Arr::first($having['values']));

        $max = $this->parameter(Arr::last($having['values']));

        return $having['boolean'] . ' ' . $column . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * Compile the "order by" portions of the query.
     *
     * @param  array  $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        if (! empty($orders)) {
            return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * Compile the query orders to an array.
     *
     * @param  array  $orders
     * @return array
     */
    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order) {
            return $order['sql'] ?? $order['direction'] . '( ' . $this->wrap($order['column']) . ' )';
        }, $orders);
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string  $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RANDOM()';
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'limit ' . (int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return 'offset ' . (int) $offset;
    }

    /**
     * Compile the "union" queries attached to the main query.
     *
     * @return string
     */
    protected function compileUnions(Builder $query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (! empty($query->unionOrders)) {
            $sql .= ' ' . $this->compileOrders($query, $query->unionOrders);
        }

        if (isset($query->unionLimit)) {
            $sql .= ' ' . $this->compileLimit($query, $query->unionLimit);
        }

        if (isset($query->unionOffset)) {
            $sql .= ' ' . $this->compileOffset($query, $query->unionOffset);
        }

        return ltrim($sql);
    }

    /**
     * Compile a single union statement.
     *
     * @return string
     */
    protected function compileUnion(array $union)
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction . $union['query']->toSql();
    }

    /**
     * Compile a union aggregate query into SQL.
     *
     * @return string
     */
    protected function compileUnionAggregate(Builder $query)
    {
        $sql = $this->compileAggregate($query, $query->aggregate);

        $query->aggregate = [];

        return $sql . ' from (' . $this->compileSelect($query) . ') as ' . $this->wrapUri('temp_table');
    }

    /**
     * Compile an exists statement into SQL.
     *
     * @return string
     */
    public function compileExists(Builder $query)
    {
        $select = $this->compileSelect($query);

        return "select exists({$select}) as {$this->wrap('exists')}";
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        // Add PREFIX declarations for registered namespaces
        $prefixes = '';
        foreach (\EasyRdf\RdfNamespace::namespaces() as $prefix => $uri) {
            $prefixes .= sprintf("PREFIX %s: <%s>\n", $prefix, $uri);
        }

        $ret = $prefixes . 'INSERT DATA {';

        $graph = $query->getGraph();
        if ($graph) {
            $ret .= ' GRAPH ' . $this->wrapUri($graph) . ' {';
        }

        $subject = '_:node';
        $data = [];

        foreach ($values as $column => $value) {
            if ($column == 'id') {
                $value = Arr::wrap($value);
                $subject = $this->wrapUri(array_shift($value));
            } else {
                foreach (Arr::wrap($value) as $v) {
                    $data[] = sprintf('%s %s', $this->wrapUri($column), (new Expression($v))->getValue());
                }
            }
        }

        $data[] = sprintf('%s %s', $this->wrapUri('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'), Expression::iri($query->from)->getValue());

        // Join predicates with semicolons and add a period at the end for valid SPARQL syntax
        $ret .= sprintf(' %s %s .', $subject, implode('; ', $data));

        if ($graph) {
            $ret .= ' }';
        }

        $ret .= ' }';

        return $ret;
    }

    /**
     * Compile an insert ignore statement into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        throw new RuntimeException('This database engine does not support inserting while ignoring errors.');
    }

    /**
     * Compile an insert and get ID statement into SQL.
     *
     * @param  array  $values
     * @param  string  $sequence
     * @return string
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        throw new RuntimeException('This database engine does not support inserting while generating a new ID.');
    }

    /**
     * Compile an insert statement using a subquery into SQL.
     *
     * @return string
     */
    public function compileInsertUsing(Builder $query, array $columns, string $sql)
    {
        return "insert into {$this->wrapUri($query->from)} ({$this->columnize($columns)}) $sql";
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  array  $values
     * @return string
     */
    public function compileUpdate(Builder $query, $values)
    {
        $table = $this->wrapUri($query->from);

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = collect($values)->map(function ($value, $key) {
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        })->implode(', ');

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';

        if (isset($query->joins)) {
            $joins = ' ' . $this->compileJoins($query, $query->joins);
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $wheres = $this->compileWheres($query);

        return trim("update {$table}{$joins} set $columns $wheres");
    }

    /**
     * Prepare the bindings for an update statement.
     *
     * @return array
     */
    public function prepareBindingsForUpdate(array $bindings, array $values)
    {
        $cleanBindings = Arr::except($bindings, ['select', 'join']);

        return array_values(
            array_merge($bindings['join'], $values, Arr::flatten($cleanBindings))
        );
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        /*
        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';
        $first = substr($wheres, strpos($wheres, '{'));

        return trim("delete $first $wheres");
        */

        $ret = '';

        $graph = $query->getGraph();
        if ($graph) {
            $ret .= 'WITH ' . $this->wrapUri($graph) . "\n";
        }

        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';
        $first = substr($wheres, strpos($wheres, '{'));

        $ret .= trim("DELETE $first $wheres");

        return $ret;
    }

    /**
     * Prepare the bindings for a delete statement.
     *
     * @return array
     */
    public function prepareBindingsForDelete(array $bindings)
    {
        return Arr::flatten(
            Arr::except($bindings, 'select')
        );
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return ['truncate ' . $this->wrapUri($query->from) => []];
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Determine if the grammar supports savepoints.
     *
     * @return bool
     */
    public function supportsSavepoints()
    {
        return false;
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array  $segments
     * @return string
     */
    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * Remove the leading boolean from a statement.
     *
     * @param  string  $value
     * @return string
     */
    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/. |or |and |union /i', '', $value, 1);
    }

    /**
     * Get the grammar specific operators.
     *
     * @return array
     */
    public function getOperators()
    {
        return $this->operators;
    }

    // ============================================================
    // SPARQL 1.1 Update Operations
    // ============================================================

    /**
     * Compile an INSERT DATA statement.
     *
     * @return string
     */
    public function compileInsertData(Builder $query)
    {
        $graph = $query->targetGraph ?? $query->getGraph();

        $sparql = 'INSERT DATA {';

        if ($graph) {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph) . ' {';
        }

        $sparql .= $this->compileTriples($query->insertData);

        if ($graph) {
            $sparql .= ' }';
        }

        $sparql .= ' }';

        return $sparql;
    }

    /**
     * Compile a DELETE DATA statement.
     *
     * @return string
     */
    public function compileDeleteData(Builder $query)
    {
        $graph = $query->targetGraph ?? $query->getGraph();

        $sparql = 'DELETE DATA {';

        if ($graph) {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph) . ' {';
        }

        $sparql .= $this->compileTriples($query->deleteData);

        if ($graph) {
            $sparql .= ' }';
        }

        $sparql .= ' }';

        return $sparql;
    }

    /**
     * Compile an INSERT WHERE statement.
     *
     * @return string
     */
    public function compileInsertWhere(Builder $query)
    {
        $graph = $query->targetGraph ?? $query->getGraph();

        $sparql = 'INSERT {';

        if ($graph) {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph) . ' {';
        }

        if (is_string($query->insertTemplate)) {
            $sparql .= ' ' . $query->insertTemplate;
        } else {
            $sparql .= $this->compileTriples($query->insertTemplate);
        }

        if ($graph) {
            $sparql .= ' }';
        }

        $sparql .= ' }';

        // Add WHERE clause
        $wheres = $this->compileWheres($query);
        $sparql .= ' ' . $wheres;

        return trim($sparql);
    }

    /**
     * Compile a DELETE WHERE statement.
     *
     * @return string
     */
    public function compileDeleteWhere(Builder $query)
    {
        $graph = $query->targetGraph ?? $query->getGraph();

        $sparql = 'DELETE {';

        if ($graph) {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph) . ' {';
        }

        if (is_string($query->deleteTemplate)) {
            $sparql .= ' ' . $query->deleteTemplate;
        } else {
            $sparql .= $this->compileTriples($query->deleteTemplate);
        }

        if ($graph) {
            $sparql .= ' }';
        }

        $sparql .= ' }';

        // Add WHERE clause
        $wheres = $this->compileWheres($query);
        $sparql .= ' ' . $wheres;

        return trim($sparql);
    }

    /**
     * Compile a DELETE/INSERT statement.
     *
     * @return string
     */
    public function compileDeleteInsert(Builder $query)
    {
        $graph = $query->targetGraph ?? $query->getGraph();

        // DELETE clause
        $sparql = 'DELETE {';

        if ($graph) {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph) . ' {';
        }

        if (is_string($query->deleteTemplate)) {
            $sparql .= ' ' . $query->deleteTemplate;
        } else {
            $sparql .= $this->compileTriples($query->deleteTemplate);
        }

        if ($graph) {
            $sparql .= ' }';
        }

        $sparql .= ' }';

        // INSERT clause
        $sparql .= ' INSERT {';

        if ($graph) {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph) . ' {';
        }

        if (is_string($query->insertTemplate)) {
            $sparql .= ' ' . $query->insertTemplate;
        } else {
            $sparql .= $this->compileTriples($query->insertTemplate);
        }

        if ($graph) {
            $sparql .= ' }';
        }

        $sparql .= ' }';

        // Add WHERE clause
        $wheres = $this->compileWheres($query);
        $sparql .= ' ' . $wheres;

        return trim($sparql);
    }

    /**
     * Compile a LOAD statement.
     *
     * @return string
     */
    public function compileLoad(Builder $query)
    {
        $sparql = $query->silent ? 'LOAD SILENT ' : 'LOAD ';

        $sparql .= '<' . $query->loadUrl . '>';

        if ($query->targetGraph) {
            $sparql .= ' INTO GRAPH ' . $this->wrapUri($query->targetGraph);
        }

        return $sparql;
    }

    /**
     * Compile a CLEAR statement.
     *
     * @return string
     */
    public function compileClear(Builder $query)
    {
        $sparql = 'CLEAR';

        if ($query->silent) {
            $sparql .= ' SILENT';
        }

        $graph = $query->targetGraph;

        if ($graph === null) {
            $sparql .= ' DEFAULT';
        } elseif (strtoupper($graph) === 'NAMED') {
            $sparql .= ' NAMED';
        } elseif (strtoupper($graph) === 'ALL') {
            $sparql .= ' ALL';
        } else {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph);
        }

        return $sparql;
    }

    /**
     * Compile a DROP statement.
     *
     * @return string
     */
    public function compileDrop(Builder $query)
    {
        $sparql = 'DROP';

        if ($query->silent) {
            $sparql .= ' SILENT';
        }

        $graph = $query->targetGraph;

        if ($graph === null) {
            $sparql .= ' DEFAULT';
        } elseif (strtoupper($graph) === 'NAMED') {
            $sparql .= ' NAMED';
        } elseif (strtoupper($graph) === 'ALL') {
            $sparql .= ' ALL';
        } else {
            $sparql .= ' GRAPH ' . $this->wrapUri($graph);
        }

        return $sparql;
    }

    /**
     * Compile a CREATE statement.
     *
     * @return string
     */
    public function compileCreate(Builder $query)
    {
        $sparql = 'CREATE';

        if ($query->silent) {
            $sparql .= ' SILENT';
        }

        $sparql .= ' GRAPH ' . $this->wrapUri($query->targetGraph);

        return $sparql;
    }

    /**
     * Compile a COPY statement.
     *
     * @return string
     */
    public function compileCopy(Builder $query)
    {
        $sparql = 'COPY';

        if ($query->silent) {
            $sparql .= ' SILENT';
        }

        $sparql .= $this->compileGraphRef($query->sourceGraph);
        $sparql .= ' TO';
        $sparql .= $this->compileGraphRef($query->targetGraph);

        return $sparql;
    }

    /**
     * Compile a MOVE statement.
     *
     * @return string
     */
    public function compileMove(Builder $query)
    {
        $sparql = 'MOVE';

        if ($query->silent) {
            $sparql .= ' SILENT';
        }

        $sparql .= $this->compileGraphRef($query->sourceGraph);
        $sparql .= ' TO';
        $sparql .= $this->compileGraphRef($query->targetGraph);

        return $sparql;
    }

    /**
     * Compile an ADD statement.
     *
     * @return string
     */
    public function compileAdd(Builder $query)
    {
        $sparql = 'ADD';

        if ($query->silent) {
            $sparql .= ' SILENT';
        }

        $sparql .= $this->compileGraphRef($query->sourceGraph);
        $sparql .= ' TO';
        $sparql .= $this->compileGraphRef($query->targetGraph);

        return $sparql;
    }

    /**
     * Compile a graph reference for graph management operations.
     *
     * @return string
     */
    public function compileGraphRef(?string $graph)
    {
        if ($graph === null) {
            return ' DEFAULT';
        }

        return ' GRAPH ' . $this->wrapUri($graph);
    }

    /**
     * Compile an array of triples into SPARQL format.
     *
     * @return string
     */
    public function compileTriples(array $triples)
    {
        $statements = [];

        foreach ($triples as $triple) {
            if (is_array($triple) && count($triple) === 3) {
                [$subject, $predicate, $object] = $triple;
                $statements[] = sprintf('%s %s %s .', $subject, $predicate, $object);
            }
        }

        return ' ' . implode(' ', $statements) . ' ';
    }
}
