<?php

/*
SPDX-FileCopyrightText: 2020, Roberto Guido
SPDX-License-Identifier: MIT
*/

namespace SolidDataWorkers\SPARQL\Query;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Query\JoinClause;
use SolidDataWorkers\SPARQL\Grammar as BaseGrammar;
use SolidDataWorkers\SPARQL\Query\Builder;

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
     * @param  \Illuminate\Database\Query\Builder  $query
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
        $sql = trim($this->concatenate(
            $this->compileComponents($query))
        );

        $query->columns = $original;

        return $sql;
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
                $method = 'compile'.ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
            $column = 'distinct '.$column;
        }

        return 'select '.$aggregate['function'].'('.$column.') as ?aggregate';
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (! is_null($query->aggregate)) {
            return;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select.$this->columnize($columns);
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        $query->where('rdf:type', new Expression($table, 'class'));
        return '';
    }

    /**
     * Compile the "join" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
        return collect($joins)->map(function ($join) use ($query) {
            $table = $this->wrapUri($join->table);

            $nestedJoins = is_null($join->joins) ? '' : ' '.$this->compileJoins($query, $join->joins);

            $tableAndNestedJoins = is_null($join->joins) ? $table : '('.$table.$nestedJoins.')';

            return trim("{$join->type} join {$tableAndNestedJoins} {$this->compileWheres($join)}");
        })->implode(' ');
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
        switch($where['operator']) {
            case '=':
                return [
                    'type' => 'Basic',
                    'attribute' => $where['column'],
                    'value' => $where['values'],
                    'boolean' => 'and'
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
                    'boolean' => 'and'
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
                    'boolean' => 'and'
                ];

                break;

            default:
                return [
                    'type' => 'Basic',
                    'attribute' => $where['column'],
                    'operator' => $where['operator'],
                    'value' => $where['value'],
                    'boolean' => 'and'
                ];

                break;
        }
    }

    /**
     * Get an array of all the where clauses for the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array
     */
    protected function compileWheresToArray($query)
    {
        $sorted_where = [];

        /*
            Where conditions referring to a parameter are aggregated as filters
            into other where conditions having that same parameter as value
        */
        foreach($query->wheres as $where) {
            if (!isset($where['filters'])) {
                $where['filters'] = [];
            }

            if (Expression::is($where['column'] ?? null, 'param')) {
                foreach($sorted_where as $index => $s) {
                    if (Expression::same($s['value'] ?? null, $where['column'])) {
                        $sorted_where[$index]['filters'][] = $this->createFilter($where);
                        break;
                    }
                }
            }
            else {
                $sorted_where[] = $where;
            }
        }

        return collect($sorted_where)->map(function ($where) use ($query) {
            if (!isset($where['boolean'])) {
                return ' . ' . $this->compileFilters($query, $where);
            }
            else {
                switch($where['boolean']) {
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $sql
     * @return string
     */
    protected function concatenateWhereClauses($query, $sql)
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction.' { '.$this->removeLeadingBoolean(implode(' ', $sql)) . ' }';
    }

    /**
     * Compile a raw where clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereRaw(Builder $query, $where)
    {
        return $where['sql'] . $this->compileFilters($query, $where);;
    }

    /**
     * Compile a basic where clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
        switch($where['operator']) {
            case '=':
                $value = $this->parameter($where['value']);
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
                    'boolean' => 'and'
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
                    'boolean' => 'and'
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
                    'boolean' => 'and'
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotInRaw(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            return $this->wrap($where['column']).' not in ('.implode(', ', $where['values']).')';
        }

        return '1 = 1';
    }

    /**
     * Compile a where in sub-select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereInSub(Builder $query, $where)
    {
        return $this->wrap($where['column']).' in ('.$this->compileSelect($where['query']).')';
    }

    /**
     * Compile a where not in sub-select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotInSub(Builder $query, $where)
    {
        return $this->wrap($where['column']).' not in ('.$this->compileSelect($where['query']).')';
    }

    /**
     * Compile a "where in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereInRaw(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            return $this->wrap($where['column']).' in ('.implode(', ', $where['values']).')';
        }

        return '0 = 1';
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return $type.'('.$this->wrap($where['column']).') '.$where['operator'].' '.$value;
    }

    /**
     * Compile a where clause comparing two columns..
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereColumn(Builder $query, $where)
    {
        return $this->wrap($where['first']).' '.$where['operator'].' '.$this->wrap($where['second']);
    }

    /**
     * Compile a nested where clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array   $where
     * @return string
     */
    protected function whereSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']).' '.$where['operator']." ($select)";
    }

    /**
     * Compile a where exists clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereExists(Builder $query, $where)
    {
        $ret = [];

        foreach($where['query']->columns as $column) {
            if ($column != $where['query']->unique_subject) {
                $ret[] = sprintf('FILTER EXISTS { %s %s %s }', $query->unique_subject, $column, $where['query']->unique_subject);
            }
        }

        $query->addBinding($where['query']->getBindings(), 'where');

        return join(' . ', $ret);
    }

    /**
     * Compile a where exists clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotExists(Builder $query, $where)
    {
        $ret = [];

        foreach($where['query']->columns as $column) {
            $ret[] = sprintf('FILTER NOT EXISTS { %s %s %s }', $query->unique_subject, $column, $where['query']->unique_subject);
        }

        return join(' . ', $ret);
    }

    /**
     * Compile a where row values condition.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereRowValues(Builder $query, $where)
    {
        $columns = $this->columnize($where['columns']);

        $values = $this->parameterize($where['values']);

        return '('.$columns.') '.$where['operator'].' ('.$values.')';
    }

    protected function compileFilters($query, $where)
    {
        if (!isset($where['filters']) || empty($where['filters'])) {
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
            return $filter['boolean'].' '.$this->{"filter{$filter['type']}"}($query, $where, $filter);
        })->all();
    }

    protected function concatenateFilterClauses($query, $where, $sql)
    {
        return ' . FILTER ( '.$this->removeLeadingBoolean(implode(' ', $sql)) . ' )';
    }

    protected function filterBasic(Builder $query, $where, $filter)
    {
        $value = $this->parameter($filter['value']);
        return $this->wrap($filter['attribute']).' '.$filter['operator'].' '.$value;
    }

    protected function filterIn(Builder $query, $where, $filter)
    {
        $values = $this->parameterize($filter['values']);
        return $this->wrap($filter['attribute']).' IN ( ' . $values . ' )';
    }

    protected function filterNotIn(Builder $query, $where, $filter)
    {
        $values = $this->parameterize($filter['values']);
        return $this->wrap($filter['attribute']).' NOT IN ( ' . $values . ' )';
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
        return sprintf(" REGEX ( %s, ? ) ", $this->wrap($filter['attribute']));
    }

    /**
     * Compile the "group by" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $groups
     * @return string
     */
    protected function compileGroups(Builder $query, $groups)
    {
        return 'group by '.$this->columnize($groups);
    }

    /**
     * Compile the "having" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $havings
     * @return string
     */
    protected function compileHavings(Builder $query, $havings)
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having '.$this->removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileHaving(array $having)
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ($having['type'] === 'Raw') {
            return $having['boolean'].' '.$having['sql'];
        } elseif ($having['type'] === 'between') {
            return $this->compileHavingBetween($having);
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'].' '.$column.' '.$having['operator'].' '.$parameter;
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

        $min = $this->parameter(head($having['values']));

        $max = $this->parameter(last($having['values']));

        return $having['boolean'].' '.$column.' '.$between.' '.$min.' and '.$max;
    }

    /**
     * Compile the "order by" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        if (! empty($orders)) {
            return 'order by '.implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * Compile the query orders to an array.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'limit '.(int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return 'offset '.(int) $offset;
    }

    /**
     * Compile the "union" queries attached to the main query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    protected function compileUnions(Builder $query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (! empty($query->unionOrders)) {
            $sql .= ' '.$this->compileOrders($query, $query->unionOrders);
        }

        if (isset($query->unionLimit)) {
            $sql .= ' '.$this->compileLimit($query, $query->unionLimit);
        }

        if (isset($query->unionOffset)) {
            $sql .= ' '.$this->compileOffset($query, $query->unionOffset);
        }

        return ltrim($sql);
    }

    /**
     * Compile a single union statement.
     *
     * @param  array  $union
     * @return string
     */
    protected function compileUnion(array $union)
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction.$union['query']->toSql();
    }

    /**
     * Compile a union aggregate query into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    protected function compileUnionAggregate(Builder $query)
    {
        $sql = $this->compileAggregate($query, $query->aggregate);

        $query->aggregate = null;

        return $sql.' from ('.$this->compileSelect($query).') as '.$this->wrapUri('temp_table');
    }

    /**
     * Compile an exists statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        $ret = 'INSERT';

        $graph = $query->getGraph();
        if ($graph) {
            $ret .= ' INTO ' . $this->wrapUri($graph);
        }

        $ret .= ' {';

        foreach(reset($values) as $column => $value) {
            $ret .= sprintf('%s %s %s . ', $this->wrapUri($query->unique_subject), $column, $value);
        }

        $ret .= ' }';

        return $ret;
    }

    /**
     * Compile an insert ignore statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        throw new RuntimeException('This database engine does not support inserting while ignoring errors.');
    }

    /**
     * Compile an insert and get ID statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array   $values
     * @param  string  $sequence
     * @return string
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        return $this->compileInsert($query, $values);
    }

    /**
     * Compile an insert statement using a subquery into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $columns
     * @param  string  $sql
     * @return string
     */
    public function compileInsertUsing(Builder $query, array $columns, string $sql)
    {
        return "insert into {$this->wrapUri($query->from)} ({$this->columnize($columns)}) $sql";
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
            return $this->wrap($key).' = '.$this->parameter($value);
        })->implode(', ');

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';

        if (isset($query->joins)) {
            $joins = ' '.$this->compileJoins($query, $query->joins);
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
     * @param  array  $bindings
     * @param  array  $values
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from {$this->wrapUri($query->from)} $wheres");
    }

    /**
     * Prepare the bindings for a delete statement.
     *
     * @param  array  $bindings
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return ['truncate '.$this->wrapUri($query->from) => []];
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * Wrap a value in keyword identifiers.
     *
     * @param  \Illuminate\Database\Query\Expression|string  $value
     * @param  bool    $prefixAlias
     * @return string
     */
    public function wrap($value, $prefixAlias = false)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        // If the value being wrapped has a column alias we will need to separate out
        // the pieces so we can wrap each of the segments of the expression on its
        // own, and then join these both back together using the "as" connector.
        if (stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        return $value;
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array   $segments
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
}
