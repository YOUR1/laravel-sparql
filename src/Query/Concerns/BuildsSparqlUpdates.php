<?php

namespace LinkedData\SPARQL\Query\Concerns;

use RuntimeException;

/**
 * Trait BuildsSparqlUpdates
 *
 * Provides SPARQL 1.1 Update operations support.
 *
 * @see https://www.w3.org/TR/sparql11-update/
 */
trait BuildsSparqlUpdates
{
    /**
     * The update operation type (insertData, deleteData, insertWhere, deleteWhere, deleteInsert, etc.).
     *
     * @var string|null
     */
    public $updateType = null;

    /**
     * The triples to insert for INSERT DATA operations.
     *
     * @var array|null
     */
    public $insertData = null;

    /**
     * The triples to delete for DELETE DATA operations.
     *
     * @var array|null
     */
    public $deleteData = null;

    /**
     * The template for INSERT WHERE operations.
     *
     * @var array|string|null
     */
    public $insertTemplate = null;

    /**
     * The template for DELETE WHERE operations.
     *
     * @var array|string|null
     */
    public $deleteTemplate = null;

    /**
     * The graph management operation (CLEAR, DROP, CREATE, COPY, MOVE, ADD).
     *
     * @var string|null
     */
    public $graphOperation = null;

    /**
     * The source graph for graph management operations.
     *
     * @var string|null
     */
    public $sourceGraph = null;

    /**
     * The target graph for graph management operations.
     *
     * @var string|null
     */
    public $targetGraph = null;

    /**
     * Whether to use SILENT flag for graph operations.
     *
     * @var bool
     */
    public $silent = false;

    /**
     * The URL to load RDF data from.
     *
     * @var string|null
     */
    public $loadUrl = null;

    /**
     * Insert static RDF data (INSERT DATA operation).
     * No variables or FILTER expressions allowed.
     *
     * @param  array  $triples  Array of triples [subject, predicate, object]
     * @return $this
     *
     * @example
     * $query->insertData([
     *     ['<http://example.org/book1>', 'dc:title', '"A new book"'],
     *     ['<http://example.org/book1>', 'dc:creator', '"A.N.Author"']
     * ])
     */
    public function insertData(array $triples)
    {
        $this->updateType = 'insertData';
        $this->insertData = $triples;

        return $this;
    }

    /**
     * Delete static RDF data (DELETE DATA operation).
     * No variables or FILTER expressions allowed.
     *
     * @param  array  $triples  Array of triples [subject, predicate, object]
     * @return $this
     *
     * @example
     * $query->deleteData([
     *     ['<http://example.org/book1>', 'dc:title', '"A new book"']
     * ])
     */
    public function deleteData(array $triples)
    {
        $this->updateType = 'deleteData';
        $this->deleteData = $triples;

        return $this;
    }

    /**
     * Insert triples based on a WHERE clause pattern (INSERT WHERE operation).
     * Use with where() methods to specify the pattern.
     *
     * @param  array|string  $template  Template triples or SPARQL template string
     * @return $this
     *
     * @example
     * $query->insertWhere([
     *     ['?book', 'dc:hasReview', '?review']
     * ])->where('?book', 'dc:title', '?title')
     */
    public function insertWhere($template)
    {
        $this->updateType = 'insertWhere';
        $this->insertTemplate = $template;

        return $this;
    }

    /**
     * Delete triples based on a WHERE clause pattern (DELETE WHERE operation).
     * Use with where() methods to specify the pattern.
     *
     * @param  array|string  $template  Template triples or SPARQL template string
     * @return $this
     *
     * @example
     * $query->deleteWhere([
     *     ['?book', 'dc:title', '?title']
     * ])->where('?book', 'dc:title', '"SPARQL Tutorial"')
     */
    public function deleteWhere($template)
    {
        $this->updateType = 'deleteWhere';
        $this->deleteTemplate = $template;

        return $this;
    }

    /**
     * Combined DELETE/INSERT operation to modify data.
     * Deletes triples matching the delete template and inserts triples from insert template
     * based on the WHERE clause.
     *
     * @param  array|string  $deleteTemplate  Triples to delete
     * @param  array|string  $insertTemplate  Triples to insert
     * @return $this
     *
     * @example
     * $query->deleteInsert(
     *     ['?book', 'dc:title', '?oldTitle'],
     *     ['?book', 'dc:title', '"New Title"']
     * )->where('?book', 'dc:title', '?oldTitle')
     */
    public function deleteInsert($deleteTemplate, $insertTemplate)
    {
        $this->updateType = 'deleteInsert';
        $this->deleteTemplate = $deleteTemplate;
        $this->insertTemplate = $insertTemplate;

        return $this;
    }

    /**
     * Load RDF data from a URL into a graph.
     *
     * @param  string  $url  URL to load RDF data from
     * @param  string|null  $graph  Target graph (null for default graph)
     * @return bool
     *
     * @example
     * $query->load('http://example.org/data.rdf')
     * $query->load('http://example.org/data.rdf', 'http://example.org/graph1')
     */
    public function load(string $url, ?string $graph = null)
    {
        $this->updateType = 'load';
        $this->loadUrl = $url;

        if ($graph) {
            $this->targetGraph = $graph;
        }

        $sql = $this->grammar->compileLoad($this);

        return $this->connection->statement($sql);
    }

    /**
     * Remove all triples from a graph (CLEAR operation).
     *
     * @param  string|null  $graph  Graph to clear (null for default graph, 'NAMED' for all named graphs, 'ALL' for all graphs)
     * @param  bool  $silent  If true, don't report errors if graph doesn't exist
     * @return bool
     *
     * @example
     * $query->clear('http://example.org/graph1')
     * $query->clear('NAMED')  // Clear all named graphs
     * $query->clear('ALL')    // Clear all graphs
     */
    public function clear(?string $graph = null, bool $silent = false)
    {
        $this->updateType = 'clear';
        $this->graphOperation = 'CLEAR';
        $this->targetGraph = $graph;
        $this->silent = $silent;

        $sql = $this->grammar->compileClear($this);

        return $this->connection->statement($sql);
    }

    /**
     * Remove a graph and all its contents (DROP operation).
     *
     * @param  string|null  $graph  Graph to drop (null for default graph, 'NAMED' for all named graphs, 'ALL' for all graphs)
     * @param  bool  $silent  If true, don't report errors if graph doesn't exist
     * @return bool
     *
     * @example
     * $query->drop('http://example.org/graph1')
     * $query->drop('NAMED', true)  // Silently drop all named graphs
     */
    public function drop(?string $graph = null, bool $silent = false)
    {
        $this->updateType = 'drop';
        $this->graphOperation = 'DROP';
        $this->targetGraph = $graph;
        $this->silent = $silent;

        $sql = $this->grammar->compileDrop($this);

        return $this->connection->statement($sql);
    }

    /**
     * Create a new graph (CREATE operation).
     *
     * @param  string  $graph  Graph to create
     * @param  bool  $silent  If true, don't report errors if graph already exists
     * @return bool
     *
     * @example
     * $query->create('http://example.org/newGraph')
     * $query->create('http://example.org/newGraph', true)  // Silently ignore if exists
     */
    public function create(string $graph, bool $silent = false)
    {
        $this->updateType = 'create';
        $this->graphOperation = 'CREATE';
        $this->targetGraph = $graph;
        $this->silent = $silent;

        $sql = $this->grammar->compileCreate($this);

        return $this->connection->statement($sql);
    }

    /**
     * Copy all data from one graph to another (COPY operation).
     * The target graph is cleared before copying.
     *
     * @param  string|null  $source  Source graph (null for default graph)
     * @param  string|null  $target  Target graph (null for default graph)
     * @param  bool  $silent  If true, don't report errors
     * @return bool
     *
     * @example
     * $query->copy('http://example.org/graph1', 'http://example.org/graph2')
     * $query->copy(null, 'http://example.org/graph2')  // Copy from default graph
     */
    public function copy(?string $source, ?string $target, bool $silent = false)
    {
        $this->updateType = 'copy';
        $this->graphOperation = 'COPY';
        $this->sourceGraph = $source;
        $this->targetGraph = $target;
        $this->silent = $silent;

        $sql = $this->grammar->compileCopy($this);

        return $this->connection->statement($sql);
    }

    /**
     * Move all data from one graph to another (MOVE operation).
     * The target graph is cleared before moving, and the source graph is cleared after.
     *
     * @param  string|null  $source  Source graph (null for default graph)
     * @param  string|null  $target  Target graph (null for default graph)
     * @param  bool  $silent  If true, don't report errors
     * @return bool
     *
     * @example
     * $query->move('http://example.org/graph1', 'http://example.org/graph2')
     */
    public function move(?string $source, ?string $target, bool $silent = false)
    {
        $this->updateType = 'move';
        $this->graphOperation = 'MOVE';
        $this->sourceGraph = $source;
        $this->targetGraph = $target;
        $this->silent = $silent;

        $sql = $this->grammar->compileMove($this);

        return $this->connection->statement($sql);
    }

    /**
     * Add all data from one graph to another (ADD operation).
     * The source graph remains unchanged.
     *
     * @param  string|null  $source  Source graph (null for default graph)
     * @param  string|null  $target  Target graph (null for default graph)
     * @param  bool  $silent  If true, don't report errors
     * @return bool
     *
     * @example
     * $query->add('http://example.org/graph1', 'http://example.org/graph2')
     */
    public function add(?string $source, ?string $target, bool $silent = false)
    {
        $this->updateType = 'add';
        $this->graphOperation = 'ADD';
        $this->sourceGraph = $source;
        $this->targetGraph = $target;
        $this->silent = $silent;

        $sql = $this->grammar->compileAdd($this);

        return $this->connection->statement($sql);
    }

    /**
     * Execute multiple update operations in a single request.
     * Note: True transaction support depends on the SPARQL endpoint's capabilities.
     *
     * @param  \Closure  $callback  Callback that receives a builder instance
     * @return bool
     *
     * @example
     * $query->transaction(function ($query) {
     *     $query->deleteData([['<http://example.org/book1>', 'dc:title', '"Old Title"']]);
     *     $query->insertData([['<http://example.org/book1>', 'dc:title', '"New Title"']]);
     * });
     */
    public function transaction(\Closure $callback)
    {
        // Note: True ACID transaction support is endpoint-specific
        // This implementation sends multiple operations in sequence
        // Some endpoints may support batching these operations

        try {
            $result = $callback($this);

            return $result !== false;
        } catch (\Exception $e) {
            throw new RuntimeException('SPARQL Update transaction failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Batch insert multiple records in a single SPARQL INSERT DATA operation.
     * This is much more efficient than inserting records one by one.
     *
     * @param  array  $records  Array of records, each containing triples
     * @return bool
     *
     * @example
     * $query->insertBatch([
     *     [['<http://example.org/book1>', 'dc:title', '"Book One"'], ['<http://example.org/book1>', 'dc:creator', '"Author 1"']],
     *     [['<http://example.org/book2>', 'dc:title', '"Book Two"'], ['<http://example.org/book2>', 'dc:creator', '"Author 2"']]
     * ])
     */
    public function insertBatch(array $records)
    {
        if (empty($records)) {
            return true;
        }

        // Flatten all triples from all records into a single array
        $allTriples = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                foreach ($record as $triple) {
                    $allTriples[] = $triple;
                }
            }
        }

        if (empty($allTriples)) {
            return true;
        }

        $this->updateType = 'insertData';
        $this->insertData = $allTriples;

        $sql = $this->grammar->compileInsertData($this);

        return $this->connection->statement($sql);
    }

    /**
     * Batch delete resources by their URIs in a single SPARQL DELETE WHERE operation.
     * This is much more efficient than deleting resources one by one.
     *
     * @param  array  $uris  Array of resource URIs to delete
     * @return int Number of URIs deleted
     *
     * @example
     * $query->deleteBatch([
     *     'http://example.org/book1',
     *     'http://example.org/book2',
     *     'http://example.org/book3'
     * ])
     */
    public function deleteBatch(array $uris)
    {
        if (empty($uris)) {
            return 0;
        }

        // Build a FILTER clause to match any of the given URIs
        $uriList = array_map(function ($uri) {
            return '<' . $uri . '>';
        }, $uris);
        $filterList = implode(', ', $uriList);

        // Use DELETE WHERE to remove all triples for the specified subjects
        $sql = "DELETE WHERE { ?s ?p ?o . FILTER(?s IN ({$filterList})) }";

        if ($this->graph) {
            $sql = "WITH <{$this->graph}> " . $sql;
        }

        $this->connection->statement($sql);

        return count($uris);
    }
}
