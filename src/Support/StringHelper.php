<?php

namespace LinkedData\SPARQL\Support;

/**
 * String utility functions for SPARQL operations
 */
class StringHelper
{
    /**
     * Escape a string value for use in SPARQL literals
     *
     * This properly escapes special characters according to SPARQL spec
     * while preserving UTF-8 multibyte characters (unlike addslashes).
     *
     * According to the SPARQL 1.1 specification, string literals require
     * escaping of backslash, double quote, newline, carriage return, and tab.
     *
     * @param  string  $value  The value to escape
     * @return string The escaped value
     *
     * @see https://www.w3.org/TR/sparql11-query/#grammarEscapes
     */
    public static function escapeSparqlLiteral($value): string
    {
        // Convert value to string if needed
        $value = (string) $value;

        // Escape special characters according to SPARQL spec
        // Order matters: backslash must be escaped first
        $replacements = [
            '\\' => '\\\\',  // Backslash
            '"' => '\\"',    // Double quote
            "\n" => '\\n',   // Newline
            "\r" => '\\r',   // Carriage return
            "\t" => '\\t',   // Tab
        ];

        return strtr($value, $replacements);
    }
}
