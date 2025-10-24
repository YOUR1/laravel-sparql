<?php

namespace LinkedData\SPARQL\Query\Concerns;

use LinkedData\SPARQL\Query\Expression;

/**
 * Trait BuildsDataTypes
 *
 * Provides helper methods for building SPARQL data types with proper type casting.
 * Supports XSD datatypes, language-tagged literals, IRIs, and blank nodes.
 */
trait BuildsDataTypes
{
    /**
     * Create a language-tagged literal and bind it to a variable.
     *
     * This method provides a convenient way to bind a language-tagged string literal.
     * It can be used with the bind() method or in select expressions.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The string value
     * @param  string  $languageTag  The language tag (e.g., "en", "fr", "de")
     * @return $this
     *
     * @example
     * $query->langTagged('label', 'Hello', 'en')
     * // Generates: BIND(STRLANG("Hello", "en") AS ?label)
     */
    public function langTagged($variable, $value, $languageTag)
    {
        $expr = $this->strLang($value, $languageTag);

        return $this->bind($expr, $variable);
    }

    /**
     * Create a typed literal with explicit XSD datatype.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The value
     * @param  string  $datatype  The XSD datatype (e.g., "integer", "decimal", "boolean")
     * @return $this
     *
     * @example
     * $query->typed('age', '25', 'integer')
     * // Generates: BIND(STRDT("25", xsd:integer) AS ?age)
     */
    public function typed($variable, $value, $datatype)
    {
        $expr = $this->strDt($value, $datatype);

        return $this->bind($expr, $variable);
    }

    /**
     * Create an xsd:integer typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The integer value
     * @return $this
     */
    public function integer($variable, $value)
    {
        return $this->typed($variable, $value, 'integer');
    }

    /**
     * Create an xsd:decimal typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The decimal value
     * @return $this
     */
    public function decimal($variable, $value)
    {
        return $this->typed($variable, $value, 'decimal');
    }

    /**
     * Create an xsd:float typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The float value
     * @return $this
     */
    public function float($variable, $value)
    {
        return $this->typed($variable, $value, 'float');
    }

    /**
     * Create an xsd:double typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The double value
     * @return $this
     */
    public function double($variable, $value)
    {
        return $this->typed($variable, $value, 'double');
    }

    /**
     * Create an xsd:boolean typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The boolean value
     * @return $this
     */
    public function boolean($variable, $value)
    {
        $boolValue = $value ? 'true' : 'false';

        return $this->typed($variable, $boolValue, 'boolean');
    }

    /**
     * Create an xsd:string typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The string value
     * @return $this
     */
    public function string($variable, $value)
    {
        return $this->typed($variable, $value, 'string');
    }

    /**
     * Create an xsd:date typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The date value (YYYY-MM-DD format)
     * @return $this
     */
    public function date($variable, $value)
    {
        return $this->typed($variable, $value, 'date');
    }

    /**
     * Create an xsd:dateTime typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The dateTime value (ISO 8601 format)
     * @return $this
     */
    public function dateTime($variable, $value)
    {
        return $this->typed($variable, $value, 'dateTime');
    }

    /**
     * Create an xsd:time typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The time value (HH:MM:SS format)
     * @return $this
     */
    public function time($variable, $value)
    {
        return $this->typed($variable, $value, 'time');
    }

    /**
     * Create an xsd:duration typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The duration value (ISO 8601 duration format)
     * @return $this
     */
    public function duration($variable, $value)
    {
        return $this->typed($variable, $value, 'duration');
    }

    /**
     * Create an xsd:gYear typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The year value (YYYY format)
     * @return $this
     */
    public function gYear($variable, $value)
    {
        return $this->typed($variable, $value, 'gYear');
    }

    /**
     * Create an xsd:gYearMonth typed literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $value  The year-month value (YYYY-MM format)
     * @return $this
     */
    public function gYearMonth($variable, $value)
    {
        return $this->typed($variable, $value, 'gYearMonth');
    }

    /**
     * Create a blank node identifier.
     *
     * If an expression is provided, creates a blank node based on that expression.
     * If no expression is provided, creates a new unique blank node.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed|null  $expression  Optional expression for blank node generation
     * @return $this
     *
     * @example
     * $query->blankNode('person')
     * // Generates: BIND(BNODE() AS ?person)
     *
     * $query->blankNode('person', '?id')
     * // Generates: BIND(BNODE(?id) AS ?person)
     */
    public function blankNode($variable, $expression = null)
    {
        $bnodeExpr = $this->bnode($expression);

        return $this->bind($bnodeExpr, $variable);
    }

    /**
     * Create an IRI from a string expression.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $expression  The expression to convert to an IRI
     * @return $this
     *
     * @example
     * $query->iri('resource', $query->concat('http://example.org/', '?id'))
     * // Generates: BIND(IRI(CONCAT("http://example.org/", ?id)) AS ?resource)
     */
    public function iriFromExpression($variable, $expression)
    {
        $iriExpr = $this->iri($expression);

        return $this->bind($iriExpr, $variable);
    }

    /**
     * Cast a value to xsd:string using the STR function.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $expression  The expression to convert to string
     * @return $this
     *
     * @example
     * $query->castToString('labelStr', '?label')
     * // Generates: BIND(STR(?label) AS ?labelStr)
     */
    public function castToString($variable, $expression)
    {
        $strExpr = $this->str($expression);

        return $this->bind($strExpr, $variable);
    }

    /**
     * Get the datatype IRI of a literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $expression  The expression to get datatype from
     * @return $this
     *
     * @example
     * $query->getDatatypeOf('type', '?value')
     * // Generates: BIND(DATATYPE(?value) AS ?type)
     */
    public function getDatatypeOf($variable, $expression)
    {
        $dtExpr = $this->datatype($expression);

        return $this->bind($dtExpr, $variable);
    }

    /**
     * Get the language tag of a literal.
     *
     * @param  string  $variable  The variable name to bind to (without ?)
     * @param  mixed  $expression  The expression to get language tag from
     * @return $this
     *
     * @example
     * $query->getLanguageOf('lang', '?label')
     * // Generates: BIND(LANG(?label) AS ?lang)
     */
    public function getLanguageOf($variable, $expression)
    {
        $langExpr = $this->lang($expression);

        return $this->bind($langExpr, $variable);
    }
}
