<?php

namespace LinkedData\SPARQL\Tests;

use LinkedData\SPARQL\Connection;

/**
 * Base test case for integration tests that interact with a real SPARQL endpoint.
 *
 * These tests require a running Fuseki instance. Start it with:
 * ./setup-fuseki.sh
 */
abstract class IntegrationTestCase extends TestCase
{
    protected Connection $connection;

    protected string $testGraph = 'http://example.org/test-graph';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = app('db')->connection('sparql');

        // Ensure Fuseki is running before running integration tests
        $this->ensureFusekiIsRunning();

        // Clear test graph before each test
        $this->clearTestGraph();
    }

    protected function tearDown(): void
    {
        // Clean up test data after each test
        $this->clearTestGraph();

        parent::tearDown();
    }

    /**
     * Clear all triples from the test graph.
     */
    protected function clearTestGraph(): void
    {
        try {
            $query = "CLEAR GRAPH <{$this->testGraph}>";
            $this->connection->statement($query);
        } catch (\Exception $e) {
            // If graph doesn't exist yet, that's fine
        }
    }

    /**
     * Clear the default graph.
     */
    protected function clearDefaultGraph(): void
    {
        try {
            $query = 'CLEAR DEFAULT';
            $this->connection->statement($query);
        } catch (\Exception $e) {
            // If graph is already empty, that's fine
        }
    }

    /**
     * Insert test triples into the test graph.
     *
     * @param  array<string>  $triples  Array of triple strings (e.g., "<s> <p> <o> .")
     */
    protected function insertTestTriples(array $triples): void
    {
        $triplesStr = implode("\n", $triples);
        $query = "INSERT DATA { GRAPH <{$this->testGraph}> { {$triplesStr} } }";
        $this->connection->statement($query);
    }

    /**
     * Insert test triples into the default graph.
     *
     * @param  array<string>  $triples  Array of triple strings (e.g., "<s> <p> <o> .")
     */
    protected function insertTestTriplesIntoDefaultGraph(array $triples): void
    {
        $triplesStr = implode("\n", $triples);
        $query = "INSERT DATA { {$triplesStr} }";
        $this->connection->statement($query);
    }

    /**
     * Count triples in the test graph.
     */
    protected function countTriplesInTestGraph(): int
    {
        $query = "SELECT (COUNT(*) as ?count) WHERE { GRAPH <{$this->testGraph}> { ?s ?p ?o } }";
        $result = $this->connection->select($query);

        // EasyRdf returns an iterable result, get the first row
        foreach ($result as $row) {
            $count = $row->count ?? 0;
            // Handle EasyRdf\Literal objects
            if ($count instanceof \EasyRdf\Literal) {
                return (int) $count->getValue();
            }

            return (int) $count;
        }

        return 0;
    }

    /**
     * Count triples in the default graph.
     */
    protected function countTriplesInDefaultGraph(): int
    {
        $query = 'SELECT (COUNT(*) as ?count) WHERE { ?s ?p ?o }';
        $result = $this->connection->select($query);

        // EasyRdf returns an iterable result, get the first row
        foreach ($result as $row) {
            $count = $row->count ?? 0;
            // Handle EasyRdf\Literal objects
            if ($count instanceof \EasyRdf\Literal) {
                return (int) $count->getValue();
            }

            return (int) $count;
        }

        return 0;
    }

    /**
     * Check if a triple exists in the test graph.
     */
    protected function tripleExistsInTestGraph(string $subject, string $predicate, string $object): bool
    {
        $query = "ASK { GRAPH <{$this->testGraph}> { {$subject} {$predicate} {$object} } }";
        $result = $this->connection->select($query);

        // ASK queries return an EasyRdf\Sparql\Result with a boolean property
        return $result->isTrue() ?? false;
    }

    /**
     * Check if a triple exists in the configured graph.
     * Uses the test graph by default (same as where data is inserted).
     */
    protected function tripleExists(string $subject, string $predicate, string $object): bool
    {
        $query = "ASK { GRAPH <{$this->testGraph}> { {$subject} {$predicate} {$object} } }";
        $result = $this->connection->select($query);

        // ASK queries return an EasyRdf\Sparql\Result with a boolean property
        return $result->isTrue() ?? false;
    }

    /**
     * Assert that a triple exists in the test graph.
     */
    protected function assertTripleExists(string $subject, string $predicate, string $object, string $message = ''): void
    {
        $exists = $this->tripleExistsInTestGraph($subject, $predicate, $object);
        $this->assertTrue($exists, $message ?: "Failed asserting that triple {$subject} {$predicate} {$object} exists in test graph.");
    }

    /**
     * Assert that a triple does not exist in the test graph.
     */
    protected function assertTripleDoesNotExist(string $subject, string $predicate, string $object, string $message = ''): void
    {
        $exists = $this->tripleExistsInTestGraph($subject, $predicate, $object);
        $this->assertFalse($exists, $message ?: "Failed asserting that triple {$subject} {$predicate} {$object} does not exist in test graph.");
    }

    /**
     * Skip test if Fuseki is not running.
     */
    protected function ensureFusekiIsRunning(): void
    {
        try {
            $query = 'ASK { ?s ?p ?o }';
            $this->connection->select($query);
        } catch (\Exception $e) {
            $this->markTestSkipped('Fuseki is not running. Start it with: ./setup-fuseki.sh');
        }
    }
}
