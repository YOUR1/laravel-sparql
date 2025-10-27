<?php

namespace LinkedData\SPARQL\TripleStore;

/**
 * Adapter for Apache Jena Fuseki triple store.
 *
 * Fuseki-specific conventions:
 * - GSP endpoint is the dataset root (not /data)
 * - Uses ?graph=<uri> for named graphs
 * - Returns JSON responses with tripleCount/quadCount
 * - Supports application/n-triples content type
 *
 * @see https://jena.apache.org/documentation/fuseki2/
 */
class FusekiAdapter extends AbstractAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'fuseki';
    }

    /**
     * {@inheritdoc}
     *
     * Fuseki GSP endpoint is the dataset root.
     *
     * Examples:
     * - http://localhost:3030/test/sparql -> http://localhost:3030/test
     * - http://localhost:3030/test/update -> http://localhost:3030/test
     * - http://localhost:3030/test/data   -> http://localhost:3030/test
     * - http://localhost:3030/test        -> http://localhost:3030/test
     */
    public function deriveGspEndpoint(string $queryEndpoint): string
    {
        // Remove any service suffix (/sparql, /update, /query, /data)
        return $this->removeServiceSuffix($queryEndpoint);
    }

    /**
     * {@inheritdoc}
     *
     * Fuseki uses standard W3C ?graph=<uri> parameter.
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
     * Fuseki accepts standard MIME type with UTF-8 charset.
     */
    public function getNTriplesContentType(): string
    {
        return 'application/n-triples; charset=utf-8';
    }

    /**
     * {@inheritdoc}
     *
     * Fuseki returns JSON response with count information on success.
     * Example: {"count": 1, "tripleCount": 1, "quadCount": 0}
     */
    public function isSuccessResponse(int $statusCode, string $responseBody): bool
    {
        if ($statusCode < 200 || $statusCode >= 300) {
            return false;
        }

        // Fuseki returns JSON with count info
        // Check if response looks like JSON (starts with {)
        $trimmed = ltrim($responseBody);
        if (isset($trimmed[0]) && $trimmed[0] === '{') {
            $data = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Consider it successful if we got a valid JSON response with HTTP 2xx
                // The presence of 'tripleCount' or 'quadCount' indicates success
                return isset($data['tripleCount']) || isset($data['quadCount']) || isset($data['count']);
            }
        }

        // If not JSON or can't parse, rely on status code
        return true;
    }
}
