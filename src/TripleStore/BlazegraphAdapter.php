<?php

namespace LinkedData\SPARQL\TripleStore;

/**
 * Adapter for Blazegraph triple store.
 *
 * Blazegraph-specific conventions:
 * - GSP endpoint is the same as SPARQL endpoint
 * - Uses ?context-uri=<uri> for named graphs (not ?graph=)
 * - Uses text/plain content type (not application/n-triples)
 * - Returns XML responses with modified count
 *
 * @see https://github.com/blazegraph/database/wiki/REST_API
 */
class BlazegraphAdapter extends AbstractAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'blazegraph';
    }

    /**
     * {@inheritdoc}
     *
     * Blazegraph uses the same endpoint for both SPARQL and GSP.
     *
     * Examples:
     * - http://localhost:9999/bigdata/sparql -> http://localhost:9999/bigdata/sparql (unchanged)
     * - http://localhost:9999/blazegraph/namespace/kb/sparql -> http://localhost:9999/blazegraph/namespace/kb/sparql
     */
    public function deriveGspEndpoint(string $queryEndpoint): string
    {
        // Blazegraph uses the same endpoint
        return $queryEndpoint;
    }

    /**
     * {@inheritdoc}
     *
     * Blazegraph uses ?context-uri=<uri> instead of standard ?graph=<uri>.
     */
    public function buildGspUrl(string $gspEndpoint, ?string $graph): string
    {
        if ($graph === null) {
            return $gspEndpoint;
        }

        // Blazegraph uses context-uri parameter
        return $gspEndpoint . '?context-uri=' . urlencode($graph);
    }

    /**
     * {@inheritdoc}
     *
     * Blazegraph prefers text/plain for N-Triples.
     */
    public function getNTriplesContentType(): string
    {
        return 'text/plain';
    }

    /**
     * {@inheritdoc}
     *
     * Blazegraph returns XML response with modified count on success.
     * Example: <?xml version="1.0"?><data modified="1" milliseconds="58"/>
     */
    public function isSuccessResponse(int $statusCode, string $responseBody): bool
    {
        if ($statusCode < 200 || $statusCode >= 300) {
            return false;
        }

        // Blazegraph returns XML with modified attribute
        // Check if response looks like XML (starts with <)
        $trimmed = ltrim($responseBody);
        if (isset($trimmed[0]) && $trimmed[0] === '<') {
            // Look for modified attribute in XML
            if (preg_match('/modified="\d+"/', $trimmed)) {
                return true;
            }
        }

        // If not XML or can't detect modified attribute, rely on status code
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Blazegraph supports query hints for optimization.
     */
    public function getQueryHints(): array
    {
        return [
            // Blazegraph-specific optimization hints can be added here
            // Example: 'analytic' => 'hint:Query hint:analytic "true" .',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Blazegraph supports namespaces.
     */
    public function supportsNamespaces(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Converts:
     *   http://localhost:9999/bigdata/sparql
     * To:
     *   http://localhost:9999/bigdata/namespace/NAMESPACE/sparql
     */
    public function buildNamespaceEndpoint(string $baseEndpoint, string $namespace): string
    {
        // Extract base URL (remove /sparql or /namespace/kb/sparql from end)
        $baseUrl = preg_replace('#/(?:namespace/[^/]+/)?sparql$#', '', $baseEndpoint);

        // Build namespace-specific endpoint
        return "{$baseUrl}/namespace/{$namespace}/sparql";
    }

    /**
     * {@inheritdoc}
     */
    public function extractNamespace(string $endpoint): ?string
    {
        if (preg_match('#/namespace/([^/]+)/sparql$#', $endpoint, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if endpoint is a namespace-specific URL.
     *
     * @param  string  $endpoint
     * @return bool
     */
    public function isNamespaceEndpoint(string $endpoint): bool
    {
        return $this->extractNamespace($endpoint) !== null;
    }
}
