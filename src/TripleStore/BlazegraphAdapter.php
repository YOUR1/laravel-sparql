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
     * - http://localhost:9090/bigdata/sparql -> http://localhost:9090/bigdata/sparql (unchanged)
     * - http://localhost:9090/blazegraph/namespace/kb/sparql -> http://localhost:9090/blazegraph/namespace/kb/sparql
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
     *   http://localhost:9090/bigdata/sparql
     * To:
     *   http://localhost:9090/bigdata/namespace/NAMESPACE/sparql
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
     */
    public function isNamespaceEndpoint(string $endpoint): bool
    {
        return $this->extractNamespace($endpoint) !== null;
    }

    /**
     * {@inheritdoc}
     *
     * Create a Blazegraph namespace via REST API.
     *
     * Configurable properties (pass as $properties array):
     * - 'com.bigdata.rdf.store.AbstractTripleStore.quads' => 'true'|'false'
     *   Enable/disable named graphs (quads mode)
     * - 'com.bigdata.rdf.store.AbstractTripleStore.textIndex' => 'true'|'false'
     *   Enable/disable full-text search index
     * - 'com.bigdata.rdf.store.AbstractTripleStore.axiomsClass' => class name
     *   Set axioms class (e.g., 'com.bigdata.rdf.axioms.NoAxioms' for no ontology)
     * - 'com.bigdata.rdf.sail.truthMaintenance' => 'true'|'false'
     *   Enable/disable truth maintenance (incompatible with quads)
     *
     * Default: Uses Blazegraph defaults (includes RDF/RDFS/OWL vocabulary)
     *
     * @see https://github.com/blazegraph/database/wiki/REST_API#create-a-namespace
     * @see https://github.com/blazegraph/database/wiki/Configuration_Options
     */
    public function createNamespace(string $baseEndpoint, $httpClient, string $namespace, array $properties = []): bool
    {
        // Check if namespace already exists
        if ($this->namespaceExists($baseEndpoint, $httpClient, $namespace)) {
            return true;
        }

        // Build the namespace creation endpoint
        $baseUrl = preg_replace('#/(?:namespace/[^/]+/)?sparql$#', '', $baseEndpoint);
        $url = "{$baseUrl}/namespace";

        // The namespace property is always required
        $defaultProperties = [
            'com.bigdata.rdf.sail.namespace' => $namespace,
        ];

        // Merge user properties with defaults (user properties can override)
        $properties = array_merge($defaultProperties, $properties);

        // Build properties string
        $propertiesStr = '';
        foreach ($properties as $key => $value) {
            $propertiesStr .= "{$key}={$value}\n";
        }

        try {
            $httpClient->setUri($url);
            $httpClient->setRawData($propertiesStr);
            $httpClient->setHeaders('Content-Type', 'text/plain');

            $response = $httpClient->request('POST');
            $statusCode = $response->getStatus();

            // 201 = created, 409 = already exists (both are OK)
            if ($statusCode === 201 || $statusCode === 409) {
                return true;
            }

            throw new \RuntimeException(
                "Failed to create namespace '{$namespace}'. Status: {$statusCode}, Body: " . $response->getBody()
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to create Blazegraph namespace '{$namespace}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Delete a Blazegraph namespace via REST API.
     *
     * @see https://github.com/blazegraph/database/wiki/REST_API#delete-a-namespace
     */
    public function deleteNamespace(string $baseEndpoint, $httpClient, string $namespace): bool
    {
        // Build the namespace deletion endpoint
        $baseUrl = preg_replace('#/(?:namespace/[^/]+/)?sparql$#', '', $baseEndpoint);
        $url = "{$baseUrl}/namespace/{$namespace}";

        try {
            $httpClient->setUri($url);
            $response = $httpClient->request('DELETE');
            $statusCode = $response->getStatus();

            // 200 = deleted, 404 = doesn't exist (both are OK)
            if ($statusCode === 200 || $statusCode === 404) {
                return true;
            }

            throw new \RuntimeException(
                "Failed to delete namespace '{$namespace}'. Status: {$statusCode}, Body: " . $response->getBody()
            );
        } catch (\Exception $e) {
            // If namespace doesn't exist, that's fine
            if (strpos($e->getMessage(), '404') !== false) {
                return true;
            }

            throw new \RuntimeException(
                "Failed to delete Blazegraph namespace '{$namespace}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Check if a Blazegraph namespace exists by attempting to access it.
     */
    public function namespaceExists(string $baseEndpoint, $httpClient, string $namespace): bool
    {
        // Build the namespace endpoint
        $baseUrl = preg_replace('#/(?:namespace/[^/]+/)?sparql$#', '', $baseEndpoint);
        $url = "{$baseUrl}/namespace/{$namespace}/sparql";

        try {
            $httpClient->setUri($url);
            $response = $httpClient->request('GET');
            $statusCode = $response->getStatus();

            // 200 = exists, 404 = doesn't exist
            return $statusCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
