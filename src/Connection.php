<?php

namespace LinkedData\SPARQL;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Database\Connection as BaseConnection;
use LinkedData\SPARQL\TripleStore\BlazegraphAdapter;
use LinkedData\SPARQL\TripleStore\FusekiAdapter;
use LinkedData\SPARQL\TripleStore\GenericAdapter;
use LinkedData\SPARQL\TripleStore\TripleStoreAdapter;
use MadBob\EasyRDFonGuzzle\HttpClient;

class Connection extends BaseConnection
{
    /**
     * The SPARQL client connection.
     *
     * @var \EasyRdf\Sparql\Client|null
     */
    protected $connection;

    /**
     * The HTTP client for SPARQL requests.
     *
     * @var HttpClient
     */
    protected $httpclient;

    /**
     * The triple store adapter for implementation-specific behavior.
     *
     * @var TripleStoreAdapter
     */
    protected $adapter;

    /**
     * The default graph URI for queries.
     *
     * @var string|null
     */
    protected $graph;

    /**
     * The Blazegraph namespace for queries (optional).
     *
     * @var string|null
     */
    protected $namespace = null;

    /**
     * Create a new SPARQL connection instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->adapter = $this->createAdapter($config);
        $this->connection = $this->createConnection($config);

        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultQueryGrammar();
    }

    /**
     * Query resources of a specific RDF type.
     *
     * @param  string  $collection  The RDF class/type URI
     * @return \LinkedData\SPARQL\Query\Builder
     */
    public function rdftype($collection)
    {
        $query = self::query();

        return $query->from($collection)->graph($this->graph);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \LinkedData\SPARQL\Query\Builder
     */
    public function query()
    {
        $default = new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());
        $default->graph($this->graph);

        // Pass namespace to query builder if set
        if ($this->namespace !== null) {
            $default->namespace($this->namespace);
        }

        return $default;
    }

    /**
     * Begin a fluent query against a table (RDF type).
     *
     * @param  string  $table  The RDF class/type URI
     * @param  string|null  $as  Alias (not used in SPARQL)
     * @return \LinkedData\SPARQL\Query\Builder
     */
    public function table($table, $as = null)
    {
        return $this->rdftype($table);
    }

    /**
     * Set the default graph for queries.
     *
     * @param  string  $graph  The graph URI
     * @return $this
     */
    public function graph($graph)
    {
        $this->graph = $graph;

        return $this;
    }

    /**
     * Get the default graph for queries.
     *
     * @return string|null
     */
    public function getGraph()
    {
        return $this->graph;
    }

    /**
     * Set the Blazegraph namespace for subsequent queries.
     *
     * @param  string  $namespace  The Blazegraph namespace name
     * @return $this
     */
    public function namespace(string $namespace): static
    {
        // Validate namespace name (only alphanumeric, underscore, hyphen)
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $namespace)) {
            throw new \InvalidArgumentException('Invalid namespace name. Only alphanumeric characters, underscores, and hyphens are allowed.');
        }

        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get the current Blazegraph namespace.
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Execute a query within a specific namespace scope.
     *
     * @param  string  $namespace  The Blazegraph namespace
     * @param  \Closure  $callback  The query callback
     */
    public function withinNamespace(string $namespace, \Closure $callback): mixed
    {
        $previousNamespace = $this->namespace;
        $this->namespace = $namespace;

        try {
            return $callback($this->query());
        } finally {
            $this->namespace = $previousNamespace;
        }
    }

    /**
     * Bind values to query placeholders.
     * Converts ? placeholders to actual values for SPARQL execution.
     *
     * @param  string  $query  The SPARQL query with placeholders
     * @param  array  $bindings  The values to bind
     * @return string The query with values bound
     */
    private function altBindValues($query, $bindings)
    {
        $index = 0;

        // Match ' ? ' but NOT when followed by a letter/underscore (which would be a SPARQL variable like ?foo)
        // This prevents replacing the '?' in SPARQL variables when they appear in SELECT clauses
        return preg_replace_callback('/ \? (?![a-zA-Z_])/', function ($matches) use (&$index, $bindings) {
            $value = $bindings[$index++];

            if (is_string($value) && preg_match('/^<.*>$/', $value) === 0 && preg_match('/^\?.*$/', $value) === 0) {
                $value = "'" . $value . "'";
            }

            return ' ' . $value . ' ';
        }, $query);
    }

    /**
     * Execute a SPARQL query against the endpoint.
     *
     * @param  string  $query  The SPARQL query
     * @param  array  $bindings  Values to bind to placeholders
     * @param  bool  $useReadPdo  Not used in SPARQL
     * @return array|\EasyRdf\Sparql\Result
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $binded_query = $this->altBindValues($query, $bindings);

            \Illuminate\Support\Facades\Log::debug('SPARQL Query', [
                'query' => $query,
                'bindings' => $bindings,
                'compiled' => $binded_query,
            ]);

            // Get the effective connection (namespace-aware)
            $connection = $this->getEffectiveConnection();

            // Detect if this is an update operation (INSERT, DELETE, CLEAR, etc.)
            // Skip PREFIX, BASE, and WITH clauses when checking
            $is_update = preg_match('/^\s*(?:(?:PREFIX|BASE)\s+.*\s*)*(?:WITH\s+<[^>]+>\s*)?\s*(INSERT|DELETE|LOAD|CLEAR|CREATE|DROP|COPY|MOVE|ADD)/is', trim($binded_query));
            if ($is_update) {
                $ret = $connection->update($binded_query);

                return $ret;
            }

            $ret = $connection->query($binded_query);

            // ASK queries return a Result object with isTrue() method - don't convert
            if (preg_match('/^\s*ASK/i', trim($binded_query))) {
                return $ret;
            }

            // Convert EasyRdf\Sparql\Result to array for Laravel compatibility
            // Laravel's Connection::select() is expected to return an array
            if ($ret instanceof \EasyRdf\Sparql\Result) {
                $result = iterator_to_array($ret);

                return $result;
            }

            return $ret;
        });
    }

    /**
     * Get the effective SPARQL client connection (namespace-aware).
     */
    protected function getEffectiveConnection(): \EasyRdf\Sparql\Client
    {
        // If no namespace is set, use the default connection
        if ($this->namespace === null) {
            return $this->connection;
        }

        // Create a new client with namespace-aware endpoints
        $queryUrl = $this->getEffectiveEndpoint();
        $updateUrl = $this->getEffectiveUpdateEndpoint();

        return new \EasyRdf\Sparql\Client($queryUrl, $updateUrl);
    }

    /**
     * Execute a SPARQL query and return a generator.
     *
     * @param  string  $query  The SPARQL query
     * @param  array  $bindings  Values to bind to placeholders
     * @param  bool  $useReadPdo  Not used in SPARQL
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $ret = $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $binded_query = $this->altBindValues($query, $bindings);

            // Get the effective connection (namespace-aware)
            $connection = $this->getEffectiveConnection();

            return $connection->query($binded_query);
        });

        foreach ($ret as $record) {
            yield $record;
        }
    }

    /**
     * Execute a SPARQL statement (INSERT, DELETE, etc.).
     *
     * @param  string  $query  The SPARQL statement
     * @param  array  $bindings  Values to bind to placeholders
     */
    public function statement($query, $bindings = []): bool
    {
        $this->select($query, $bindings);

        return true;
    }

    /**
     * Execute a SPARQL statement that affects data.
     *
     * @param  string  $query  The SPARQL statement
     * @param  array  $bindings  Values to bind to placeholders
     * @return int Number of affected rows (always 0 for SPARQL)
     */
    public function affectingStatement($query, $bindings = []): int
    {
        /*
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $binded_query = $this->altBindValues($query, $bindings);

            $this->connection->update($binded_query);
            return 0;
        });
        */

        $this->select($query, $bindings);

        return 0;
    }

    /**
     * Execute an unprepared SPARQL query.
     *
     * @param  string  $query  The SPARQL query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }
            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->exec($query) !== false
            );

            return $change;
        });
    }

    /**
     * Get the HTTP client instance.
     *
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpclient;
    }

    /**
     * Register a custom RDF namespace prefix.
     *
     * @param  string  $prefix  The namespace prefix
     * @param  string  $uri  The full namespace URI
     * @return void
     */
    public function addRdfNamespace($prefix, $uri)
    {
        \EasyRdf\RdfNamespace::set($prefix, $uri);
    }

    /**
     * Get all registered RDF namespaces.
     *
     * @return array
     */
    public function getRdfNamespaces()
    {
        return \EasyRdf\RdfNamespace::namespaces();
    }

    /**
     * Get the SPARQL endpoint URL, with namespace if set.
     */
    protected function getEffectiveEndpoint(): string
    {
        $endpoint = $this->config['host'] ?? $this->config['endpoint'];

        // If namespace is set and the adapter supports namespaces, modify endpoint
        if ($this->namespace !== null && $this->adapter->supportsNamespaces()) {
            $endpoint = $this->adapter->buildNamespaceEndpoint($endpoint, $this->namespace);
        }

        return $endpoint;
    }

    /**
     * Get the SPARQL update endpoint URL, with namespace if set.
     */
    protected function getEffectiveUpdateEndpoint(): ?string
    {
        $updateEndpoint = $this->config['update_endpoint'] ?? null;

        // If no explicit update endpoint, derive from query endpoint
        if ($updateEndpoint === null) {
            $queryEndpoint = $this->getEffectiveEndpoint();

            // For Fuseki, the update endpoint is /update instead of /sparql
            if ($this->adapter->getName() === 'fuseki') {
                return preg_replace('/\/sparql$/', '/update', $queryEndpoint);
            }

            return $queryEndpoint;
        }

        // If namespace is set and the adapter supports namespaces, modify endpoint
        if ($this->namespace !== null && $this->adapter->supportsNamespaces()) {
            $updateEndpoint = $this->adapter->buildNamespaceEndpoint($updateEndpoint, $this->namespace);
        }

        return $updateEndpoint;
    }

    /**
     * Create a new SPARQL client connection.
     *
     * @return \EasyRdf\Sparql\Client
     */
    protected function createConnection(array $config)
    {
        // Initialize HTTP client
        $this->httpclient = new HttpClient;
        \EasyRdf\Http::setDefaultHttpClient($this->httpclient);

        // Configure RDF namespaces
        if (isset($config['namespaces'])) {
            // Clear existing namespaces
            foreach (\EasyRdf\RdfNamespace::namespaces() as $prefix => $url) {
                \EasyRdf\RdfNamespace::delete($prefix);
            }

            // Set custom namespaces
            foreach ($config['namespaces'] as $prefix => $uri) {
                $this->addRdfNamespace($prefix, $uri);
            }
        }

        // Set default graph if specified
        if (isset($config['graph'])) {
            $this->graph = $config['graph'];
        }

        // Set default namespace if specified
        if (isset($config['namespace'])) {
            $this->namespace = $config['namespace'];
        }

        // Configure authentication if provided
        if (isset($config['auth'])) {
            $handler = new CurlHandler;
            $stack = HandlerStack::create($handler);
            $this->httpclient->setOptions('handler', $stack);

            switch ($config['auth']['type']) {
                case 'basic':
                    $this->httpclient->setOptions('auth', [
                        $config['auth']['username'],
                        $config['auth']['password'],
                        'basic',
                    ]);
                    break;
                case 'digest':
                    $this->httpclient->setOptions('auth', [
                        $config['auth']['username'],
                        $config['auth']['password'],
                        'digest',
                    ]);
                    break;
            }
        }

        // Configure datatype mappings
        \EasyRdf\Literal::setDatatypeMapping('xsd:double', 'LinkedData\SPARQL\Query\Literal\Double');

        // Support separate query and update endpoints
        $queryUrl = $config['host'];
        $updateUrl = $config['update_endpoint'] ?? null;

        // Create and return the SPARQL client
        return new \EasyRdf\Sparql\Client($queryUrl, $updateUrl);
    }

    /**
     * Reconnect to the SPARQL endpoint if the connection is missing.
     *
     * @return void
     */
    public function reconnectIfMissingConnection()
    {
        if (is_null($this->connection)) {
            $this->reconnect();
        }
    }

    /**
     * Disconnect from the SPARQL endpoint.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->connection = null;
    }

    /**
     * Get the name of the driver.
     *
     * @return string
     */
    public function getDriverName()
    {
        return 'sparql';
    }

    /**
     * Get the default post processor instance.
     *
     * @return \LinkedData\SPARQL\Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \LinkedData\SPARQL\Query\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar;
    }

    /**
     * Create the appropriate triple store adapter based on configuration.
     */
    protected function createAdapter(array $config): TripleStoreAdapter
    {
        $implementation = $config['implementation'] ?? 'generic';

        return match (strtolower($implementation)) {
            'fuseki' => new FusekiAdapter,
            'blazegraph' => new BlazegraphAdapter,
            'generic' => new GenericAdapter,
            default => throw new \InvalidArgumentException(
                "Unsupported triple store implementation: {$implementation}. " .
                'Supported: fuseki, blazegraph, generic'
            ),
        };
    }

    /**
     * Get the triple store adapter.
     */
    public function getAdapter(): TripleStoreAdapter
    {
        return $this->adapter;
    }

    /**
     * Create a namespace in the triple store.
     *
     * For Blazegraph, you can customize namespace properties:
     *
     * Example - Create namespace without ontology triples:
     * ```php
     * $connection->createNamespace('my_namespace', [
     *     'com.bigdata.rdf.store.AbstractTripleStore.axiomsClass' => 'com.bigdata.rdf.axioms.NoAxioms'
     * ]);
     * ```
     *
     * Example - Create namespace with quads support:
     * ```php
     * $connection->createNamespace('my_namespace', [
     *     'com.bigdata.rdf.store.AbstractTripleStore.quads' => 'true'
     * ]);
     * ```
     *
     * @param  string  $namespace  The namespace name
     * @param  array  $properties  Optional implementation-specific properties
     * @return bool True if created or already exists
     *
     * @throws \RuntimeException If namespace creation fails
     *
     * @see \LinkedData\SPARQL\TripleStore\BlazegraphAdapter::createNamespace() For Blazegraph-specific options
     */
    public function createNamespace(string $namespace, array $properties = []): bool
    {
        return $this->adapter->createNamespace(
            $this->config['host'],
            $this->httpclient,
            $namespace,
            $properties
        );
    }

    /**
     * Delete a namespace from the triple store.
     *
     * @param  string  $namespace  The namespace name
     * @return bool True if deleted or doesn't exist
     *
     * @throws \RuntimeException If namespace deletion fails
     */
    public function deleteNamespace(string $namespace): bool
    {
        return $this->adapter->deleteNamespace(
            $this->config['host'],
            $this->httpclient,
            $namespace
        );
    }

    /**
     * Check if a namespace exists in the triple store.
     *
     * @param  string  $namespace  The namespace name
     * @return bool True if exists
     */
    public function namespaceExists(string $namespace): bool
    {
        return $this->adapter->namespaceExists(
            $this->config['host'],
            $this->httpclient,
            $namespace
        );
    }

    /**
     * Post RDF data directly to the Graph Store Protocol endpoint.
     * This is more efficient than INSERT DATA for bulk operations.
     *
     * @param  string  $rdfData  RDF data in specified format
     * @param  string  $contentType  MIME type (e.g., 'application/n-triples')
     * @param  string|null  $graph  Optional graph URI
     *
     * @see https://www.w3.org/TR/sparql11-http-rdf-update/
     */
    public function postGraphStoreData(string $rdfData, string $contentType, ?string $graph = null): bool
    {
        // Use adapter to derive GSP endpoint
        $queryEndpoint = $this->config['host'];
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        // Use adapter to build complete URL with graph parameter
        $targetGraph = $graph ?? $this->graph;
        $url = $this->adapter->buildGspUrl($gspEndpoint, $targetGraph);

        // Use adapter-specific content type if not explicitly provided
        if ($contentType === 'application/n-triples') {
            $contentType = $this->adapter->getNTriplesContentType();
        }

        // Ensure charset=utf-8 is specified in Content-Type header
        // This prevents encoding issues when triple store receives UTF-8 data
        if (strpos($contentType, 'charset') === false) {
            $contentType .= '; charset=utf-8';
        }

        try {
            \Illuminate\Support\Facades\Log::debug('GSP POST', [
                'implementation' => $this->adapter->getName(),
                'endpoint' => $url,
                'contentType' => $contentType,
                'dataSize' => strlen($rdfData),
                'graph' => $targetGraph,
            ]);

            // Set up the HTTP client for POST request
            $this->httpclient->setUri($url);
            $this->httpclient->setRawData($rdfData);
            $this->httpclient->setHeaders('Content-Type', $contentType);

            // Make the request
            $response = $this->httpclient->request('POST');

            // EasyRdf Response uses getStatus() not getStatusCode()
            $statusCode = $response->getStatus();
            $responseBody = $response->getBody();

            \Illuminate\Support\Facades\Log::debug('GSP POST Response', [
                'implementation' => $this->adapter->getName(),
                'status' => $statusCode,
                'body' => substr($responseBody, 0, 500),
            ]);

            // Use adapter to determine success
            return $this->adapter->isSuccessResponse($statusCode, $responseBody);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('GSP POST failed', [
                'implementation' => $this->adapter->getName(),
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException(
                "Graph Store Protocol POST failed ({$this->adapter->getName()}): " .
                $e->getMessage() . ' (URL: ' . $url . ')',
                0,
                $e
            );
        }
    }
}
