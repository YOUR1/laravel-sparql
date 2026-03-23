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

    /**
     * {@inheritdoc}
     *
     * Fuseki supports namespace isolation via datasets.
     * Each dataset gets its own SPARQL endpoint (/{dataset}/sparql)
     * and is managed via the Fuseki admin API (/$/datasets).
     */
    public function supportsNamespaces(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Build a namespace-specific endpoint for Fuseki.
     * Replaces the dataset name in the URL while preserving the service suffix.
     *
     * Examples:
     *   http://localhost:3030/ds/sparql + tenant_test -> http://localhost:3030/tenant_test/sparql
     *   http://localhost:3030/ds/update + tenant_test -> http://localhost:3030/tenant_test/update
     *   http://localhost:3030/ds         + tenant_test -> http://localhost:3030/tenant_test
     */
    public function buildNamespaceEndpoint(string $baseEndpoint, string $namespace): string
    {
        $baseUrl = $this->getBaseUrl($baseEndpoint);

        // Extract service suffix (/sparql, /update, /query, /data) if present
        $suffix = '';
        if (preg_match('~/(sparql|update|query|data)/?$~', $baseEndpoint, $matches)) {
            $suffix = '/' . $matches[1];
        }

        return $baseUrl . '/' . $namespace . $suffix;
    }

    /**
     * {@inheritdoc}
     *
     * Extract namespace (dataset name) from Fuseki endpoint URL.
     */
    public function extractNamespace(string $endpoint): ?string
    {
        return $this->extractDatasetName($endpoint);
    }

    /**
     * {@inheritdoc}
     *
     * Create a new dataset in Fuseki via the admin API.
     */
    public function createNamespace(string $baseEndpoint, $httpClient, string $namespace, array $properties = []): bool
    {
        // Check if dataset already exists
        if ($this->namespaceExists($baseEndpoint, $httpClient, $namespace)) {
            return true;
        }

        $baseUrl = $this->getBaseUrl($baseEndpoint);
        $adminEndpoint = $baseUrl . '/$/datasets';

        // Default to TDB2 persistent storage
        $dbType = $properties['dbType'] ?? 'tdb2';

        try {
            $httpClient->setUri($adminEndpoint);
            $httpClient->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
            $httpClient->setRawData(http_build_query([
                'dbName' => $namespace,
                'dbType' => $dbType,
            ]));

            $response = $httpClient->request('POST');
            $statusCode = $response->getStatus();

            // 200 = created, 409 = already exists (both are OK)
            return ($statusCode >= 200 && $statusCode < 300) || $statusCode === 409;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Delete a dataset from Fuseki via the admin API.
     */
    public function deleteNamespace(string $baseEndpoint, $httpClient, string $namespace): bool
    {
        $baseUrl = $this->getBaseUrl($baseEndpoint);
        $adminEndpoint = $baseUrl . '/$/datasets/' . $namespace;

        try {
            $httpClient->setUri($adminEndpoint);

            $response = $httpClient->request('DELETE');
            $statusCode = $response->getStatus();

            // 200 = deleted, 404 = doesn't exist (both are OK)
            return ($statusCode >= 200 && $statusCode < 300) || $statusCode === 404;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Check if a dataset exists in Fuseki via the admin API.
     */
    public function namespaceExists(string $baseEndpoint, $httpClient, string $namespace): bool
    {
        $baseUrl = $this->getBaseUrl($baseEndpoint);
        $adminEndpoint = $baseUrl . '/$/datasets/' . $namespace;

        try {
            $httpClient->setUri($adminEndpoint);

            $response = $httpClient->request('GET');
            $statusCode = $response->getStatus();

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Exception $e) {
            return false;
        }
    }
}
