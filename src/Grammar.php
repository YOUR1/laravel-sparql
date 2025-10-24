<?php

namespace LinkedData\SPARQL;

use Illuminate\Support\Traits\Macroable;
use LinkedData\SPARQL\Query\Expression;

abstract class Grammar
{
    use Macroable;

    /**
     * The grammar table prefix.
     *
     * @var string
     */
    protected $tablePrefix = '';

    protected $serializer = null;

    protected function getSerializer()
    {
        if (is_null($this->serializer)) {
            $this->serializer = new \EasyRdf\Serialiser\Turtle;
        }

        return $this->serializer;
    }

    /**
     * Wrap an array of values.
     *
     * @return array
     */
    public function wrapArray(array $values)
    {
        return array_map([$this, 'wrap'], $values);
    }

    /**
     * Wrap a URI/IRI in keyword identifiers.
     *
     * Handles:
     * - Expression objects
     * - Namespace prefix expansion (e.g., "foaf:Person" -> "http://xmlns.com/foaf/0.1/Person")
     * - URL validation
     * - URN validation
     * - Blank node identifiers (_:node)
     * - IRI validation (IRIs with Unicode characters)
     *
     * @param  \Illuminate\Database\Query\Expression|string  $uri
     * @return string
     */
    public function wrapUri($uri)
    {
        if ($uri instanceof Expression) {
            return $uri->getValue();
        }

        // Handle blank nodes (they should be wrapped in angle brackets)
        if (is_string($uri) && str_starts_with($uri, '_:')) {
            return sprintf('<%s>', $uri);
        }

        // Expand namespace prefixes (e.g., foaf:Person -> http://xmlns.com/foaf/0.1/Person)
        $expandedUri = \EasyRdf\RdfNamespace::expand($uri);

        // Validate URL (ASCII URIs)
        if (filter_var($expandedUri, FILTER_VALIDATE_URL)) {
            return sprintf('<%s>', $this->normalizeUri($expandedUri));
        }

        // Validate URN format
        if (preg_match('/^urn(:[^:]*)*$/', $expandedUri) === 1) {
            return sprintf('<%s>', $expandedUri);
        }

        // Validate IRI (may contain Unicode characters)
        // RFC 3987 defines IRIs as extending URIs to include Unicode characters
        if ($this->isValidIri($expandedUri)) {
            return sprintf('<%s>', $this->normalizeUri($expandedUri));
        }

        // Return as-is if it's a prefixed name or variable
        return $uri;
    }

    /**
     * Check if a string is a valid IRI (Internationalized Resource Identifier).
     *
     * IRIs extend URIs to allow Unicode characters.
     * This is a simplified validation based on RFC 3987.
     *
     * @param  string  $iri
     * @return bool
     */
    protected function isValidIri($iri)
    {
        // Must contain a scheme separator
        if (! str_contains($iri, ':')) {
            return false;
        }

        // Basic IRI pattern: scheme:path
        // Allow Unicode characters in the path
        $pattern = '/^[a-z][a-z0-9+.\-]*:.+/ui';

        return preg_match($pattern, $iri) === 1;
    }

    /**
     * Normalize a URI/IRI.
     *
     * Performs basic normalization like removing extra slashes,
     * but preserves the overall structure.
     *
     * @param  string  $uri
     * @return string
     */
    protected function normalizeUri($uri)
    {
        // Remove any existing angle brackets
        $uri = trim($uri, '<>');

        // Normalize excessive slashes (but not in the scheme part)
        if (preg_match('/^([a-z][a-z0-9+.\-]*:\/\/)(.+)/i', $uri, $matches)) {
            $scheme = $matches[1];
            $path = $matches[2];
            // Reduce multiple slashes to single slash in path
            $path = preg_replace('/\/+/', '/', $path);
            $uri = $scheme . $path;
        }

        return $uri;
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param  \Illuminate\Database\Query\Expression|string  $value
     * @param  bool  $prefixAlias
     * @return string
     */
    public function wrap($value, $prefixAlias = false)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        } elseif ($this->isLiteral($value)) {
            return $this->getSerializer()->serialiseLiteral($value);
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
     * Wrap a value that has an alias.
     *
     * @param  string  $value
     * @param  bool  $prefixAlias
     * @return string
     */
    protected function wrapAliasedValue($value, $prefixAlias = false)
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        // If we are wrapping a table we need to prefix the alias with the table prefix
        // as well in order to generate proper syntax. If this is a column of course
        // no prefix is necessary.
        if ($prefixAlias) {
            $segments[1] = $this->tablePrefix . $segments[1];
        }

        return $this->wrap(
            $segments[0]
        ) . ' as ' . $this->wrapValue(
            $segments[1]
        );
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * Convert an array of column names into a delimited string.
     *
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(' ', $columns);
    }

    /**
     * Create query parameter place-holders for an array.
     *
     * @return string
     */
    public function parameterize(array $values)
    {
        /*
            Remember to keep blanks around '?' to permit bindings substitution
        */
        return implode(' , ', array_map([$this, 'parameter'], $values));
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed  $value
     * @return string
     */
    public function parameter($value)
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * Quote the given string literal.
     *
     * @param  string|array  $value
     * @return string
     */
    public function quoteString($value)
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }

        return "'$value'";
    }

    /**
     * Determine if the given value is a raw expression.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function isExpression($value)
    {
        return $value instanceof Expression;
    }

    public function isLiteral($value)
    {
        return $value instanceof \EasyRdf\Literal;
    }

    /**
     * Get the value of a raw expression.
     *
     * @param  \Illuminate\Database\Query\Expression|\LinkedData\SPARQL\Query\Expression  $expression
     * @return string
     */
    public function getValue($expression)
    {
        return $expression->getValue();
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the grammar's table prefix.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Set the grammar's table prefix.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        return $this;
    }
}
