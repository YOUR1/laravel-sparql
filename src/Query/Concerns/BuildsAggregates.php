<?php

namespace LinkedData\SPARQL\Query\Concerns;

use Illuminate\Support\Arr;

trait BuildsAggregates
{
    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        return (int) $this->aggregate(__FUNCTION__, Arr::wrap($columns));
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        $var = $this->pushAttribute($column);

        return $this->aggregate(__FUNCTION__, [$var]);
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        $var = $this->pushAttribute($column);

        return $this->aggregate(__FUNCTION__, [$var]);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        $var = $this->pushAttribute($column);
        $result = $this->numericAggregate(__FUNCTION__, [$var]);

        return $result ?: 0;
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        $var = $this->pushAttribute($column);

        return $this->aggregate(__FUNCTION__, [$var]);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  string  $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    /**
     * Retrieve the GROUP_CONCAT aggregate of a given column.
     *
     * @param  string  $column
     * @param  string|null  $separator
     * @return mixed
     */
    public function groupConcat($column, $separator = null)
    {
        $var = $this->pushAttribute($column);

        $function = 'group_concat';
        if ($separator !== null) {
            $function .= '_separator_' . $separator;
        }

        return $this->aggregate($function, [$var]);
    }

    /**
     * Retrieve a sample value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sample($column)
    {
        $var = $this->pushAttribute($column);

        return $this->aggregate(__FUNCTION__, [$var]);
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array  $columns
     * @return mixed
     */
    public function aggregate($function, $columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        $results = $this->cloneWithout($this->unions ? [] : ['columns'])
            ->cloneWithoutBindings($this->unions ? [] : ['select'])
            ->setAggregate($function, $columns)
            ->get();

        if (! $results->isEmpty()) {
            return array_change_key_case((array) $results[0])['aggregate'][0]->getValue();
        }
    }

    /**
     * Execute a numeric aggregate function on the database.
     *
     * @param  string  $function
     * @param  array  $columns
     * @return float|int
     */
    public function numericAggregate($function, $columns = false)
    {
        if ($columns === false) {
            $columns = $this->defaultColumns();
        }

        $result = $this->aggregate($function, $columns);

        // If there is no result, we can obviously just return 0 here. Next, we will check
        // if the result is an integer or float. If it is already one of these two data
        // types we can just return the result as-is, otherwise we will convert this.
        if (! $result) {
            return 0;
        }

        if (is_a($result, '\EasyRdf\Literal')) {
            $result = $result->getValue();
        }

        if (is_int($result) || is_float($result)) {
            return $result;
        }

        // If the result doesn't contain a decimal place, we will assume it is an int then
        // cast it to one. When it does we will cast it to a float since it needs to be
        // cast to the expected data type for the developers out of pure convenience.
        return strpos((string) $result, '.') === false
                ? (int) $result : (float) $result;
    }

    /**
     * Set the aggregate property without running the query.
     *
     * @param  string  $function
     * @param  array  $columns
     * @return $this
     */
    protected function setAggregate($function, $columns)
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = [];

            $this->bindings['order'] = [];
        }

        return $this;
    }

    /**
     * Execute the given callback while selecting the given columns.
     *
     * After running the callback, the columns are reset to the original value.
     *
     * @param  array  $columns
     * @param  callable  $callback
     * @return mixed
     */
    protected function onceWithColumns($columns, $callback)
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }
}
