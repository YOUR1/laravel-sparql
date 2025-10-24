<?php

namespace LinkedData\SPARQL;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Database\Connection as BaseConnection;
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
     * The default graph URI for queries.
     *
     * @var string|null
     */
    protected $graph;

    /**
     * Create a new SPARQL connection instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
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

            // Detect if this is an update operation (INSERT, DELETE, CLEAR, etc.)
            // Skip PREFIX, BASE, and WITH clauses when checking
            $is_update = preg_match('/^\s*(?:(?:PREFIX|BASE)\s+.*\s*)*(?:WITH\s+<[^>]+>\s*)?\s*(INSERT|DELETE|LOAD|CLEAR|CREATE|DROP|COPY|MOVE|ADD)/is', trim($binded_query));
            if ($is_update) {
                $ret = $this->connection->update($binded_query);

                return $ret;
            }

            $ret = $this->connection->query($binded_query);

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

            return $this->connection->query($binded_query);
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
}
