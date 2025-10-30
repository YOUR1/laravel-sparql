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
     * This properly escapes special characters according to SPARQL/N-Triples spec.
     *
     * According to the N-Triples specification, string literals require:
     * - Escaping of backslash, double quote, newline, carriage return, and tab
     * - Non-ASCII characters (> U+007F) should be escaped as \uXXXX or \UXXXXXXXX
     *
     * The Unicode escaping is crucial for compatibility with triple stores like
     * Blazegraph that may not properly handle UTF-8 in HTTP bodies despite
     * Content-Type charset parameters.
     *
     * @param  string  $value  The value to escape
     * @return string The escaped value
     *
     * @see https://www.w3.org/TR/n-triples/#grammar-production-STRING_LITERAL_QUOTE
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

        $value = strtr($value, $replacements);

        // Escape non-ASCII characters (> U+007F) as \uXXXX
        // This ensures compatibility with triple stores that don't properly
        // handle UTF-8 in HTTP request bodies
        $value = preg_replace_callback('/[\x80-\xFF]+/u', function ($matches) {
            $result = '';
            // Convert UTF-8 string to array of Unicode code points
            $codepoints = mb_str_split($matches[0], 1, 'UTF-8');
            foreach ($codepoints as $char) {
                $codepoint = mb_ord($char, 'UTF-8');
                if ($codepoint <= 0xFFFF) {
                    // Use \uXXXX for code points up to U+FFFF
                    $result .= sprintf('\\u%04X', $codepoint);
                } else {
                    // Use \UXXXXXXXX for code points above U+FFFF
                    $result .= sprintf('\\U%08X', $codepoint);
                }
            }

            return $result;
        }, $value);

        return $value;
    }
}
