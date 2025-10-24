<?php

namespace LinkedData\SPARQL\Query\Concerns;

/**
 * Trait BuildsSparqlFunctions
 *
 * Provides helper methods for building SPARQL 1.1 function expressions.
 * These functions can be used in BIND, SELECT, or FILTER clauses.
 */
trait BuildsSparqlFunctions
{
    /**
     * Build a COALESCE function expression.
     *
     * Returns the first non-null value from the arguments.
     *
     * @param  mixed  ...$expressions
     * @return string
     */
    public function coalesce(...$expressions)
    {
        $args = implode(', ', array_map(fn ($expr) => $this->sparqlValue($expr), $expressions));

        return "COALESCE({$args})";
    }

    /**
     * Build an IF conditional expression.
     *
     * @param  string  $condition
     * @param  mixed  $trueValue
     * @param  mixed  $falseValue
     * @return string
     */
    public function if($condition, $trueValue, $falseValue)
    {
        $true = $this->sparqlValue($trueValue);
        $false = $this->sparqlValue($falseValue);

        return "IF({$condition}, {$true}, {$false})";
    }

    // ========================================
    // String Functions
    // ========================================

    /**
     * Build a STR function (convert to string).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function str($expression)
    {
        return 'STR(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a STRLEN function (string length).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function strlen($expression)
    {
        return 'STRLEN(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a SUBSTR function (substring).
     *
     * @param  mixed  $expression
     * @param  int  $start
     * @param  int|null  $length
     * @return string
     */
    public function substr($expression, $start, $length = null)
    {
        $str = $this->sparqlValue($expression);
        if ($length === null) {
            return "SUBSTR({$str}, {$start})";
        }

        return "SUBSTR({$str}, {$start}, {$length})";
    }

    /**
     * Build an UCASE function (uppercase).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function ucase($expression)
    {
        return 'UCASE(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build an LCASE function (lowercase).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function lcase($expression)
    {
        return 'LCASE(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a CONCAT function (string concatenation).
     *
     * @param  mixed  ...$expressions
     * @return string
     */
    public function concat(...$expressions)
    {
        $args = implode(', ', array_map(fn ($expr) => $this->sparqlValue($expr), $expressions));

        return "CONCAT({$args})";
    }

    /**
     * Build a STRSTARTS function (string starts with).
     *
     * @param  mixed  $string
     * @param  mixed  $prefix
     * @return string
     */
    public function strStarts($string, $prefix)
    {
        $str = $this->sparqlValue($string);
        $pre = $this->sparqlValue($prefix);

        return "STRSTARTS({$str}, {$pre})";
    }

    /**
     * Build a STRENDS function (string ends with).
     *
     * @param  mixed  $string
     * @param  mixed  $suffix
     * @return string
     */
    public function strEnds($string, $suffix)
    {
        $str = $this->sparqlValue($string);
        $suf = $this->sparqlValue($suffix);

        return "STRENDS({$str}, {$suf})";
    }

    /**
     * Build a CONTAINS function (string contains).
     *
     * @param  mixed  $string
     * @param  mixed  $substring
     * @return string
     */
    public function contains($string, $substring)
    {
        $str = $this->sparqlValue($string);
        $sub = $this->sparqlValue($substring);

        return "CONTAINS({$str}, {$sub})";
    }

    /**
     * Build a STRBEFORE function (substring before).
     *
     * @param  mixed  $string
     * @param  mixed  $delimiter
     * @return string
     */
    public function strBefore($string, $delimiter)
    {
        $str = $this->sparqlValue($string);
        $del = $this->sparqlValue($delimiter);

        return "STRBEFORE({$str}, {$del})";
    }

    /**
     * Build a STRAFTER function (substring after).
     *
     * @param  mixed  $string
     * @param  mixed  $delimiter
     * @return string
     */
    public function strAfter($string, $delimiter)
    {
        $str = $this->sparqlValue($string);
        $del = $this->sparqlValue($delimiter);

        return "STRAFTER({$str}, {$del})";
    }

    /**
     * Build a REPLACE function (string replace with regex).
     *
     * @param  mixed  $string
     * @param  string  $pattern
     * @param  string  $replacement
     * @param  string|null  $flags
     * @return string
     */
    public function replace($string, $pattern, $replacement, $flags = null)
    {
        $str = $this->sparqlValue($string);
        $pat = $this->sparqlValue($pattern);
        $rep = $this->sparqlValue($replacement);

        if ($flags === null) {
            return "REPLACE({$str}, {$pat}, {$rep})";
        }

        $flg = $this->sparqlValue($flags);

        return "REPLACE({$str}, {$pat}, {$rep}, {$flg})";
    }

    // ========================================
    // Numeric Functions
    // ========================================

    /**
     * Build an ABS function (absolute value).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function abs($expression)
    {
        return 'ABS(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a CEIL function (ceiling).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function ceil($expression)
    {
        return 'CEIL(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a FLOOR function (floor).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function floor($expression)
    {
        return 'FLOOR(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a ROUND function (round to nearest integer).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function round($expression)
    {
        return 'ROUND(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a RAND function (random number between 0 and 1).
     *
     * @return string
     */
    public function rand()
    {
        return 'RAND()';
    }

    // ========================================
    // Date/Time Functions
    // ========================================

    /**
     * Build a NOW function (current date/time).
     *
     * @return string
     */
    public function now()
    {
        return 'NOW()';
    }

    /**
     * Build a YEAR function (extract year from date).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function year($expression)
    {
        return 'YEAR(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a MONTH function (extract month from date).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function month($expression)
    {
        return 'MONTH(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a DAY function (extract day from date).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function day($expression)
    {
        return 'DAY(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build an HOURS function (extract hours from time).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function hours($expression)
    {
        return 'HOURS(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a MINUTES function (extract minutes from time).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function minutes($expression)
    {
        return 'MINUTES(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a SECONDS function (extract seconds from time).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function seconds($expression)
    {
        return 'SECONDS(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a TIMEZONE function (extract timezone from date/time).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function timezone($expression)
    {
        return 'TIMEZONE(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a TZ function (extract timezone string from date/time).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function tz($expression)
    {
        return 'TZ(' . $this->sparqlValue($expression) . ')';
    }

    // ========================================
    // Hash Functions
    // ========================================

    /**
     * Build an MD5 function.
     *
     * @param  mixed  $expression
     * @return string
     */
    public function md5($expression)
    {
        return 'MD5(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a SHA1 function.
     *
     * @param  mixed  $expression
     * @return string
     */
    public function sha1($expression)
    {
        return 'SHA1(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a SHA256 function.
     *
     * @param  mixed  $expression
     * @return string
     */
    public function sha256($expression)
    {
        return 'SHA256(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a SHA384 function.
     *
     * @param  mixed  $expression
     * @return string
     */
    public function sha384($expression)
    {
        return 'SHA384(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a SHA512 function.
     *
     * @param  mixed  $expression
     * @return string
     */
    public function sha512($expression)
    {
        return 'SHA512(' . $this->sparqlValue($expression) . ')';
    }

    // ========================================
    // Other Functions
    // ========================================

    /**
     * Build a REGEX function (regular expression matching).
     *
     * @param  mixed  $expression
     * @param  string  $pattern
     * @param  string|null  $flags  Flags: 'i' for case-insensitive
     * @return string
     */
    public function regex($expression, $pattern, $flags = null)
    {
        $expr = $this->sparqlValue($expression);
        $pat = $this->sparqlValue($pattern);

        if ($flags === null) {
            return "REGEX({$expr}, {$pat})";
        }

        $flg = $this->sparqlValue($flags);

        return "REGEX({$expr}, {$pat}, {$flg})";
    }

    /**
     * Build a LANG function (get language tag of literal).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function lang($expression)
    {
        return 'LANG(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a LANGMATCHES function (check language tag).
     *
     * @param  mixed  $langTag
     * @param  string  $langRange
     * @return string
     */
    public function langMatches($langTag, $langRange)
    {
        $tag = $this->sparqlValue($langTag);
        $range = $this->sparqlValue($langRange);

        return "LANGMATCHES({$tag}, {$range})";
    }

    /**
     * Build a DATATYPE function (get datatype of literal).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function datatype($expression)
    {
        return 'DATATYPE(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a STRLANG function (create language-tagged literal).
     *
     * Creates a literal with a language tag.
     * Example: STRLANG("Hello", "en") creates "Hello"@en
     *
     * @param  mixed  $string  The string value
     * @param  string  $languageTag  The language tag (e.g., "en", "fr", "de")
     * @return string
     */
    public function strLang($string, $languageTag)
    {
        $str = $this->sparqlValue($string);
        $lang = $this->sparqlValue($languageTag);

        return "STRLANG({$str}, {$lang})";
    }

    /**
     * Build a STRDT function (create typed literal).
     *
     * Creates a literal with an explicit datatype.
     * Example: STRDT("123", xsd:integer) creates "123"^^xsd:integer
     *
     * @param  mixed  $string  The string value
     * @param  string  $datatypeIri  The datatype IRI (e.g., "xsd:integer", "xsd:date")
     * @return string
     */
    public function strDt($string, $datatypeIri)
    {
        // For STRDT, the first argument must always be a string literal (quoted)
        // unless it's a variable
        if (is_string($string) && (str_starts_with($string, '?') || str_starts_with($string, '$'))) {
            $str = $string;
        } else {
            // Force string quoting even for numeric values
            $str = '"' . addslashes((string) $string) . '"';
        }

        // Datatype IRI should not be quoted
        if (! str_contains($datatypeIri, ':')) {
            // If no namespace prefix, assume xsd
            $datatypeIri = 'xsd:' . $datatypeIri;
        }

        return "STRDT({$str}, {$datatypeIri})";
    }

    /**
     * Build an IRI function (construct an IRI).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function iri($expression)
    {
        return 'IRI(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a BNODE function (construct a blank node).
     *
     * @param  mixed|null  $expression
     * @return string
     */
    public function bnode($expression = null)
    {
        if ($expression === null) {
            return 'BNODE()';
        }

        return 'BNODE(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build an ISIRI function (check if IRI).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function isIri($expression)
    {
        return 'ISIRI(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build an ISBLANK function (check if blank node).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function isBlank($expression)
    {
        return 'ISBLANK(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build an ISLITERAL function (check if literal).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function isLiteral($expression)
    {
        return 'ISLITERAL(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build an ISNUMERIC function (check if numeric).
     *
     * @param  mixed  $expression
     * @return string
     */
    public function isNumeric($expression)
    {
        return 'ISNUMERIC(' . $this->sparqlValue($expression) . ')';
    }

    /**
     * Build a BOUND function (check if variable is bound).
     *
     * @param  string  $variable
     * @return string
     */
    public function bound($variable)
    {
        // Ensure variable starts with ?
        if (! str_starts_with($variable, '?')) {
            $variable = '?' . $variable;
        }

        return "BOUND({$variable})";
    }

    /**
     * Build a SAMETERM function (check if two terms are the same).
     *
     * @param  mixed  $term1
     * @param  mixed  $term2
     * @return string
     */
    public function sameTerm($term1, $term2)
    {
        $t1 = $this->sparqlValue($term1);
        $t2 = $this->sparqlValue($term2);

        return "SAMETERM({$t1}, {$t2})";
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Convert a value to a SPARQL-compatible string.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function sparqlValue($value)
    {
        // If it's already a SPARQL variable or expression, return as-is
        if (is_string($value) && (str_starts_with($value, '?') || str_starts_with($value, '$'))) {
            return $value;
        }

        // If it's a raw SPARQL function call, return as-is
        if (is_string($value) && preg_match('/^[A-Z_]+\(.*\)$/i', $value)) {
            return $value;
        }

        // If it's a numeric value, return as-is
        if (is_numeric($value)) {
            return (string) $value;
        }

        // If it's already a quoted string, return as-is
        if (is_string($value) && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return $value;
        }

        // If it's a string literal, quote it
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }

        // For other types, convert to string
        return (string) $value;
    }
}
