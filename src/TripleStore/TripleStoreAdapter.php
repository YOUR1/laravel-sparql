<?php

namespace LinkedData\SPARQL\TripleStore;

/**
 * Interface for triple store implementation-specific adapters.
 *
 * Different SPARQL endpoints (Fuseki, Blazegraph, Virtuoso, etc.) have
 * slightly different conventions for Graph Store Protocol and other features.
 * This interface allows the package to support multiple implementations.
 */
interface TripleStoreAdapter
{
    /**
     * Get the name of this triple store implementation.
     *
     * @return string (e.g., 'fuseki', 'blazegraph', 'virtuoso')
     */
    public function getName(): string;

    /**
     * Derive the Graph Store Protocol (GSP) endpoint from the query endpoint.
     *
     * Examples:
     * - Fuseki: http://localhost:3030/test/sparql -> http://localhost:3030/test
     * - Blazegraph: http://localhost:9999/bigdata/sparql -> http://localhost:9999/bigdata/sparql
     * - Generic: http://example.com/sparql -> http://example.com/data
     *
     * @param  string  $queryEndpoint  The SPARQL query endpoint URL
     * @return string The GSP endpoint URL
     */
    public function deriveGspEndpoint(string $queryEndpoint): string;

    /**
     * Build the complete GSP URL with graph parameter.
     *
     * Different implementations use different parameter names:
     * - Fuseki/Generic: ?graph=<uri>
     * - Blazegraph: ?context-uri=<uri>
     * - Virtuoso: ?graph=<uri>
     *
     * @param  string  $gspEndpoint  The base GSP endpoint
     * @param  string|null  $graph  Optional named graph URI
     * @return string Complete URL with query parameters
     */
    public function buildGspUrl(string $gspEndpoint, ?string $graph): string;

    /**
     * Get the Content-Type header value for N-Triples format.
     *
     * Most implementations accept 'application/n-triples', but some
     * may prefer 'text/plain' or require charset specification.
     *
     * @return string MIME type for N-Triples
     */
    public function getNTriplesContentType(): string;

    /**
     * Check if the GSP response indicates success.
     *
     * @param  int  $statusCode  HTTP status code
     * @param  string  $responseBody  Response body
     * @return bool True if the operation succeeded
     */
    public function isSuccessResponse(int $statusCode, string $responseBody): bool;

    /**
     * Get implementation-specific query hints or optimizations.
     *
     * Returns SPARQL query fragments that can optimize queries for
     * this specific triple store (e.g., Virtuoso query hints).
     *
     * @return array<string, string> Key-value pairs of optimization hints
     */
    public function getQueryHints(): array;
}
