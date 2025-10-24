<?php

namespace LinkedData\SPARQL\Query\Concerns;

use Closure;
use LinkedData\SPARQL\Query\Expression;

trait BuildsSparqlQueries
{
    /**
     * Set the query to be a CONSTRUCT query with the given template.
     *
     * @param  array|string  $template
     * @return $this
     */
    public function construct($template)
    {
        $this->queryType = 'construct';

        if (is_string($template)) {
            // If a raw SPARQL string is provided, use it directly
            $this->constructTemplate = $template;
        } else {
            // Otherwise, expect an array of triples
            $this->constructTemplate = is_array($template) ? $template : func_get_args();
        }

        return $this;
    }

    /**
     * Set the query to be an ASK query (returns boolean).
     *
     * @return $this
     */
    public function ask()
    {
        $this->queryType = 'ask';

        return $this;
    }

    /**
     * Set the query to be a DESCRIBE query for the given resources.
     *
     * @param  array|string|null  $resources
     * @return $this
     */
    public function describe($resources = null)
    {
        $this->queryType = 'describe';

        if (is_null($resources)) {
            // DESCRIBE with no arguments describes the subject
            $this->describeResources = null;
        } elseif (is_string($resources)) {
            $this->describeResources = [$resources];
        } else {
            $this->describeResources = is_array($resources) ? $resources : func_get_args();
        }

        return $this;
    }

    public function graph($graph)
    {
        $this->graph = $graph;

        return $this;
    }

    public function getGraph()
    {
        return $this->graph;
    }

    /**
     * Add an OPTIONAL graph pattern to the query.
     *
     * @param  string  $boolean
     * @return $this
     */
    public function optional(Closure $callback, $boolean = 'and')
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addOptionalWhereQuery($query, $boolean);
    }

    /**
     * Add another query builder as an optional where to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder|static  $query
     * @param  string  $boolean
     * @return $this
     */
    public function addOptionalWhereQuery($query, $boolean = 'and')
    {
        if (count($query->wheres)) {
            $type = 'Optional';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['where'], 'where');
        }

        return $this;
    }

    /**
     * Add a BIND expression to the query.
     *
     * @param  string  $expression
     * @param  string  $variable
     * @param  string  $boolean
     * @return $this
     */
    public function bind($expression, $variable, $boolean = 'and')
    {
        $type = 'Bind';

        // Ensure variable starts with ?
        if (! str_starts_with($variable, '?')) {
            $variable = '?' . $variable;
        }

        $this->wheres[] = compact('type', 'expression', 'variable', 'boolean');

        return $this;
    }

    /**
     * Add a VALUES data block to the query.
     *
     * @param  array|string  $variables
     * @param  string  $boolean
     * @return $this
     */
    public function values($variables, array $values, $boolean = 'and')
    {
        $type = 'Values';

        // Normalize variables to array
        if (is_string($variables)) {
            $variables = [$variables];
        }

        // Ensure all variables start with ?
        $variables = array_map(function ($var) {
            return str_starts_with($var, '?') ? $var : '?' . $var;
        }, $variables);

        $this->wheres[] = compact('type', 'variables', 'values', 'boolean');

        return $this;
    }

    /**
     * Add a MINUS graph pattern to the query.
     *
     * @param  string  $boolean
     * @return $this
     */
    public function minus(Closure $callback, $boolean = 'and')
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addMinusWhereQuery($query, $boolean);
    }

    /**
     * Add another query builder as a minus where to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder|static  $query
     * @param  string  $boolean
     * @return $this
     */
    public function addMinusWhereQuery($query, $boolean = 'and')
    {
        if (count($query->wheres)) {
            $type = 'Minus';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['where'], 'where');
        }

        return $this;
    }

    /**
     * Add a SERVICE clause for federated queries.
     *
     * @param  string  $serviceUri
     * @param  string  $boolean
     * @return $this
     */
    public function service($serviceUri, Closure $callback, $boolean = 'and')
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addServiceWhereQuery($serviceUri, $query, $boolean);
    }

    /**
     * Add another query builder as a service where to the query builder.
     *
     * @param  string  $serviceUri
     * @param  \Illuminate\Database\Query\Builder|static  $query
     * @param  string  $boolean
     * @return $this
     */
    public function addServiceWhereQuery($serviceUri, $query, $boolean = 'and')
    {
        if (count($query->wheres)) {
            $type = 'Service';

            $this->wheres[] = compact('type', 'serviceUri', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['where'], 'where');
        }

        return $this;
    }

    /**
     * Add a property path pattern to the query.
     *
     * Property paths support operators:
     * - `/` : Sequence path
     * - `|` : Alternative path
     * - `^` : Inverse path
     * - `*` : Zero or more occurrences
     * - `+` : One or more occurrences
     * - `?` : Zero or one occurrence
     *
     * @param  string  $propertyPath
     * @param  mixed  $value
     * @param  string  $operator
     * @param  string  $boolean
     * @return $this
     */
    public function propertyPath($propertyPath, $value, $operator = '=', $boolean = 'and')
    {
        $type = 'PropertyPath';

        // Store the property path as the column
        $column = $propertyPath;

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }
}
