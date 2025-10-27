<?php

namespace LinkedData\SPARQL\TripleStore;

/**
 * Generic adapter following W3C SPARQL 1.1 standards.
 *
 * This adapter implements the standard W3C specifications and should work
 * with most SPARQL 1.1 compliant endpoints (Virtuoso, GraphDB, Stardog, etc.).
 *
 * Standards followed:
 * - SPARQL 1.1 Graph Store HTTP Protocol
 * - Standard /data endpoint convention
 * - Standard ?graph=<uri> parameter
 * - Standard application/n-triples content type
 *
 * @see https://www.w3.org/TR/sparql11-http-rdf-update/
 */
class GenericAdapter extends AbstractAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'generic';
    }

    /**
     * {@inheritdoc}
     *
     * Standard W3C convention: replace /sparql or /update with /data.
     *
     * Examples:
     * - http://example.com/sparql -> http://example.com/data
     * - http://example.com/dataset/sparql -> http://example.com/dataset/data
     * - http://example.com/dataset/update -> http://example.com/dataset/data
     */
    public function deriveGspEndpoint(string $queryEndpoint): string
    {
        // Standard pattern: replace 'sparql' or 'update' with 'data'
        if (preg_match('~^(.+?)/(sparql|update|query)/?$~', $queryEndpoint, $matches)) {
            return $matches[1] . '/data';
        }

        // If no match, try appending /data
        // e.g., http://localhost:3030/test -> http://localhost:3030/test/data
        return rtrim($queryEndpoint, '/') . '/data';
    }

    /**
     * {@inheritdoc}
     *
     * W3C standard uses ?graph=<uri> parameter.
     */
    public function buildGspUrl(string $gspEndpoint, ?string $graph): string
    {
        if ($graph === null) {
            return $gspEndpoint;
        }

        return $gspEndpoint . '?graph=' . urlencode($graph);
    }

    /**
     * {@inheritdoc}
     *
     * W3C standard MIME type for N-Triples.
     */
    public function getNTriplesContentType(): string
    {
        return 'application/n-triples';
    }

    /**
     * {@inheritdoc}
     *
     * Generic adapter relies on HTTP status codes.
     * 200 OK, 201 Created, 204 No Content are all success.
     */
    public function isSuccessResponse(int $statusCode, string $responseBody): bool
    {
        return $statusCode >= 200 && $statusCode < 300;
    }
}
