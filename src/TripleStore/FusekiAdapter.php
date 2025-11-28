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
     * Fuseki supports multiple datasets which function like namespaces.
     * Each dataset has its own /sparql endpoint.
     */
    public function supportsNamespaces(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Build a namespace-specific endpoint for Fuseki.
     * Fuseki uses /{dataset}/sparql pattern (not /namespace/{ns}/sparql like Blazegraph).
     *
     * Example: http://localhost:3030/ds/sparql + tenant_test -> http://localhost:3030/tenant_test/sparql
     */
    public function buildNamespaceEndpoint(string $baseEndpoint, string $namespace): string
    {
        // Extract base URL (host:port)
        $baseUrl = $this->getBaseUrl($baseEndpoint);

        // Build namespace-specific endpoint
        return $baseUrl . '/' . $namespace . '/sparql';
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
        $baseUrl = $this->getBaseUrl($baseEndpoint);
        $adminEndpoint = $baseUrl . '/$/datasets';

        // Get authentication from properties or try to detect from HTTP client config
        $authHeader = null;
        if (isset($properties['username']) && isset($properties['password'])) {
            $authHeader = 'Basic ' . base64_encode($properties['username'] . ':' . $properties['password']);
        }

        // Default to TDB2 persistent storage
        $dbType = $properties['dbType'] ?? 'tdb2';

        try {
            $options = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'dbName' => $namespace,
                    'dbType' => $dbType,
                ]),
            ];

            if ($authHeader) {
                $options['headers']['Authorization'] = $authHeader;
            }

            $response = $httpClient->request('POST', $adminEndpoint, $options);
            $statusCode = $response->getStatusCode();

            return $statusCode >= 200 && $statusCode < 300;
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
            $response = $httpClient->request('DELETE', $adminEndpoint);
            $statusCode = $response->getStatusCode();

            return $statusCode >= 200 && $statusCode < 300;
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
            $response = $httpClient->request('GET', $adminEndpoint);
            $statusCode = $response->getStatusCode();

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Exception $e) {
            return false;
        }
    }
}
