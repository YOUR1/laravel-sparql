<?php

namespace LinkedData\SPARQL\Tests\Feature;

use LinkedData\SPARQL\Tests\IntegrationTestCase;

/**
 * Integration tests for Basic CRUD Operations.
 *
 * These tests verify that the package can actually interact with a real SPARQL endpoint
 * to perform INSERT, DELETE, UPDATE (DELETE/INSERT combined), and SELECT operations.
 *
 * Prerequisites:
 * - Fuseki must be running (./setup-fuseki.sh)
 * - Test endpoint: http://localhost:3030/test/sparql
 */
class BasicCrudOperationsTest extends IntegrationTestCase
{
    /**
     * Test INSERT DATA operations that actually write triples to Fuseki.
     *
     * @test
     */
    public function it_can_insert_data_into_fuseki(): void
    {
        // Verify the test graph starts empty
        $this->assertEquals(0, $this->countTriplesInTestGraph());

        // Insert test triples using INSERT DATA
        $triples = [
            '<http://example.org/person/1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "John Doe" .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/age> "30"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ];

        $this->insertTestTriples($triples);

        // Verify triples were inserted
        $this->assertEquals(3, $this->countTriplesInTestGraph());

        // Verify specific triples exist
        $this->assertTripleExists(
            '<http://example.org/person/1>',
            '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>',
            '<http://xmlns.com/foaf/0.1/Person>'
        );

        $this->assertTripleExists(
            '<http://example.org/person/1>',
            '<http://xmlns.com/foaf/0.1/name>',
            '"John Doe"'
        );
    }

    /**
     * Test INSERT DATA with multiple resources.
     *
     * @test
     */
    public function it_can_insert_multiple_resources(): void
    {
        // Insert multiple people
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "Alice" .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/name> "Bob" .',
            '<http://example.org/person/3> <http://xmlns.com/foaf/0.1/name> "Charlie" .',
        ];

        $this->insertTestTriples($triples);

        // Verify all triples were inserted
        $this->assertEquals(3, $this->countTriplesInTestGraph());
    }

    /**
     * Test INSERT DATA with different data types.
     *
     * @test
     */
    public function it_can_insert_different_data_types(): void
    {
        // Insert triples with various data types
        $triples = [
            '<http://example.org/resource/1> <http://example.org/prop/string> "text value" .',
            '<http://example.org/resource/1> <http://example.org/prop/integer> "42"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.org/resource/1> <http://example.org/prop/decimal> "3.14"^^<http://www.w3.org/2001/XMLSchema#decimal> .',
            '<http://example.org/resource/1> <http://example.org/prop/boolean> "true"^^<http://www.w3.org/2001/XMLSchema#boolean> .',
            '<http://example.org/resource/1> <http://example.org/prop/langString> "Hello"@en .',
        ];

        $this->insertTestTriples($triples);

        // Verify all data types were inserted
        $this->assertEquals(5, $this->countTriplesInTestGraph());

        // Verify specific typed values exist
        $this->assertTripleExists(
            '<http://example.org/resource/1>',
            '<http://example.org/prop/integer>',
            '"42"^^<http://www.w3.org/2001/XMLSchema#integer>'
        );

        $this->assertTripleExists(
            '<http://example.org/resource/1>',
            '<http://example.org/prop/langString>',
            '"Hello"@en'
        );
    }

    /**
     * Test DELETE DATA operations that remove triples from Fuseki.
     *
     * @test
     */
    public function it_can_delete_data_from_fuseki(): void
    {
        // First, insert test data
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "John Doe" .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/age> "30"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/email> "john@example.org" .',
        ];

        $this->insertTestTriples($triples);
        $this->assertEquals(3, $this->countTriplesInTestGraph());

        // Delete specific triples using DELETE DATA
        $deleteQuery = "DELETE DATA { GRAPH <{$this->testGraph}> {
            <http://example.org/person/1> <http://xmlns.com/foaf/0.1/email> \"john@example.org\" .
        } }";

        $this->connection->statement($deleteQuery);

        // Verify triple was deleted
        $this->assertEquals(2, $this->countTriplesInTestGraph());

        $this->assertTripleDoesNotExist(
            '<http://example.org/person/1>',
            '<http://xmlns.com/foaf/0.1/email>',
            '"john@example.org"'
        );

        // Verify other triples still exist
        $this->assertTripleExists(
            '<http://example.org/person/1>',
            '<http://xmlns.com/foaf/0.1/name>',
            '"John Doe"'
        );
    }

    /**
     * Test DELETE DATA with multiple triples.
     *
     * @test
     */
    public function it_can_delete_multiple_triples(): void
    {
        // Insert test data
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "Alice" .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/name> "Bob" .',
            '<http://example.org/person/3> <http://xmlns.com/foaf/0.1/name> "Charlie" .',
        ];

        $this->insertTestTriples($triples);
        $this->assertEquals(3, $this->countTriplesInTestGraph());

        // Delete multiple triples
        $deleteQuery = "DELETE DATA { GRAPH <{$this->testGraph}> {
            <http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> \"Alice\" .
            <http://example.org/person/2> <http://xmlns.com/foaf/0.1/name> \"Bob\" .
        } }";

        $this->connection->statement($deleteQuery);

        // Verify triples were deleted
        $this->assertEquals(1, $this->countTriplesInTestGraph());

        // Verify Charlie's triple still exists
        $this->assertTripleExists(
            '<http://example.org/person/3>',
            '<http://xmlns.com/foaf/0.1/name>',
            '"Charlie"'
        );
    }

    /**
     * Test UPDATE operations (DELETE/INSERT combined) on real data.
     *
     * @test
     */
    public function it_can_update_data_with_delete_insert_combined(): void
    {
        // Insert initial data
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "John Doe" .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/age> "30"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ];

        $this->insertTestTriples($triples);

        // Update: Change name and age using DELETE/INSERT
        $updateQuery = "
            DELETE { GRAPH <{$this->testGraph}> {
                ?person <http://xmlns.com/foaf/0.1/name> \"John Doe\" .
                ?person <http://xmlns.com/foaf/0.1/age> \"30\"^^<http://www.w3.org/2001/XMLSchema#integer> .
            } }
            INSERT { GRAPH <{$this->testGraph}> {
                ?person <http://xmlns.com/foaf/0.1/name> \"John Smith\" .
                ?person <http://xmlns.com/foaf/0.1/age> \"31\"^^<http://www.w3.org/2001/XMLSchema#integer> .
            } }
            WHERE { GRAPH <{$this->testGraph}> {
                ?person <http://xmlns.com/foaf/0.1/name> \"John Doe\" .
            } }
        ";

        $this->connection->statement($updateQuery);

        // Verify old values are gone
        $this->assertTripleDoesNotExist(
            '<http://example.org/person/1>',
            '<http://xmlns.com/foaf/0.1/name>',
            '"John Doe"'
        );

        $this->assertTripleDoesNotExist(
            '<http://example.org/person/1>',
            '<http://xmlns.com/foaf/0.1/age>',
            '"30"^^<http://www.w3.org/2001/XMLSchema#integer>'
        );

        // Verify new values exist
        $this->assertTripleExists(
            '<http://example.org/person/1>',
            '<http://xmlns.com/foaf/0.1/name>',
            '"John Smith"'
        );

        $this->assertTripleExists(
            '<http://example.org/person/1>',
            '<http://xmlns.com/foaf/0.1/age>',
            '"31"^^<http://www.w3.org/2001/XMLSchema#integer>'
        );
    }

    /**
     * Test UPDATE with conditional logic (INSERT only if condition is met).
     *
     * @test
     */
    public function it_can_conditionally_update_data(): void
    {
        // Insert initial data
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "Alice" .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/age> "25"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/name> "Bob" .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/age> "35"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ];

        $this->insertTestTriples($triples);

        // Add a "senior" flag only to people over 30
        $updateQuery = "
            INSERT { GRAPH <{$this->testGraph}> {
                ?person <http://example.org/prop/isSenior> \"true\"^^<http://www.w3.org/2001/XMLSchema#boolean> .
            } }
            WHERE { GRAPH <{$this->testGraph}> {
                ?person <http://xmlns.com/foaf/0.1/age> ?age .
                FILTER(?age > 30)
            } }
        ";

        $this->connection->statement($updateQuery);

        // Verify only Bob got the senior flag
        $this->assertTripleExists(
            '<http://example.org/person/2>',
            '<http://example.org/prop/isSenior>',
            '"true"^^<http://www.w3.org/2001/XMLSchema#boolean>'
        );

        $this->assertTripleDoesNotExist(
            '<http://example.org/person/1>',
            '<http://example.org/prop/isSenior>',
            '"true"^^<http://www.w3.org/2001/XMLSchema#boolean>'
        );
    }

    /**
     * Test basic SELECT queries retrieving actual data from Fuseki.
     *
     * @test
     */
    public function it_can_select_data_from_fuseki(): void
    {
        // Insert test data
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "Alice" .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/age> "25"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/name> "Bob" .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/age> "30"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ];

        $this->insertTestTriples($triples);

        // Execute SELECT query
        $selectQuery = "SELECT ?name ?age WHERE { GRAPH <{$this->testGraph}> {
            ?person <http://xmlns.com/foaf/0.1/name> ?name .
            ?person <http://xmlns.com/foaf/0.1/age> ?age .
        } } ORDER BY ?name";

        $results = $this->connection->select($selectQuery);

        // Verify results
        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        // Check first result (Alice)
        $this->assertEquals('Alice', $results[0]->name);
        $this->assertEquals('25', $results[0]->age);

        // Check second result (Bob)
        $this->assertEquals('Bob', $results[1]->name);
        $this->assertEquals('30', $results[1]->age);
    }

    /**
     * Test SELECT with FILTER conditions.
     *
     * @test
     */
    public function it_can_select_data_with_filter(): void
    {
        // Insert test data
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "Alice" .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/age> "25"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/name> "Bob" .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/age> "30"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.org/person/3> <http://xmlns.com/foaf/0.1/name> "Charlie" .',
            '<http://example.org/person/3> <http://xmlns.com/foaf/0.1/age> "35"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ];

        $this->insertTestTriples($triples);

        // Select only people over 28
        $selectQuery = "SELECT ?name ?age WHERE { GRAPH <{$this->testGraph}> {
            ?person <http://xmlns.com/foaf/0.1/name> ?name .
            ?person <http://xmlns.com/foaf/0.1/age> ?age .
            FILTER(?age > 28)
        } } ORDER BY ?age";

        $results = $this->connection->select($selectQuery);

        // Verify only Bob and Charlie are returned
        $this->assertCount(2, $results);
        $this->assertEquals('Bob', $results[0]->name);
        $this->assertEquals('Charlie', $results[1]->name);
    }

    /**
     * Test query results are correctly parsed and returned as collections.
     *
     * @test
     */
    public function it_returns_results_as_properly_parsed_objects(): void
    {
        // Insert test data with various types
        $triples = [
            '<http://example.org/resource/1> <http://example.org/prop/string> "text value" .',
            '<http://example.org/resource/1> <http://example.org/prop/integer> "42"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.org/resource/1> <http://example.org/prop/uri> <http://example.org/linked-resource> .',
        ];

        $this->insertTestTriples($triples);

        // Query all properties
        $selectQuery = "SELECT ?p ?o WHERE { GRAPH <{$this->testGraph}> {
            <http://example.org/resource/1> ?p ?o .
        } } ORDER BY ?p";

        $results = $this->connection->select($selectQuery);

        // Verify results are objects
        $this->assertIsArray($results);
        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('p', $result);
            $this->assertObjectHasProperty('o', $result);
        }

        // Verify values are accessible
        $this->assertNotEmpty($results[0]->p);
        $this->assertNotEmpty($results[0]->o);
    }

    /**
     * Test SELECT DISTINCT removes duplicates.
     *
     * @test
     */
    public function it_can_select_distinct_values(): void
    {
        // Insert data with duplicate values
        $triples = [
            '<http://example.org/person/1> <http://example.org/prop/city> "London" .',
            '<http://example.org/person/2> <http://example.org/prop/city> "London" .',
            '<http://example.org/person/3> <http://example.org/prop/city> "Paris" .',
            '<http://example.org/person/4> <http://example.org/prop/city> "London" .',
        ];

        $this->insertTestTriples($triples);

        // Select distinct cities
        $selectQuery = "SELECT DISTINCT ?city WHERE { GRAPH <{$this->testGraph}> {
            ?person <http://example.org/prop/city> ?city .
        } } ORDER BY ?city";

        $results = $this->connection->select($selectQuery);

        // Verify only 2 distinct cities
        $this->assertCount(2, $results);
        $this->assertEquals('London', $results[0]->city);
        $this->assertEquals('Paris', $results[1]->city);
    }

    /**
     * Test COUNT aggregate function.
     *
     * @test
     */
    public function it_can_count_results(): void
    {
        // Insert test data
        $triples = [
            '<http://example.org/person/1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .',
            '<http://example.org/person/2> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .',
            '<http://example.org/person/3> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .',
        ];

        $this->insertTestTriples($triples);

        // Count persons
        $selectQuery = "SELECT (COUNT(?person) as ?count) WHERE { GRAPH <{$this->testGraph}> {
            ?person <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .
        } }";

        $results = $this->connection->select($selectQuery);

        // Verify count
        $this->assertCount(1, $results);

        // Extract count value (may be EasyRdf\Literal)
        $count = $results[0]->count;
        if ($count instanceof \EasyRdf\Literal) {
            $count = (int) $count->getValue();
        }
        $this->assertEquals(3, $count);
    }

    /**
     * Test OPTIONAL patterns.
     *
     * @test
     */
    public function it_can_query_with_optional_patterns(): void
    {
        // Insert test data (Alice has email, Bob doesn't)
        $triples = [
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/name> "Alice" .',
            '<http://example.org/person/1> <http://xmlns.com/foaf/0.1/email> "alice@example.org" .',
            '<http://example.org/person/2> <http://xmlns.com/foaf/0.1/name> "Bob" .',
        ];

        $this->insertTestTriples($triples);

        // Query with OPTIONAL email
        $selectQuery = "SELECT ?name ?email WHERE { GRAPH <{$this->testGraph}> {
            ?person <http://xmlns.com/foaf/0.1/name> ?name .
            OPTIONAL { ?person <http://xmlns.com/foaf/0.1/email> ?email . }
        } } ORDER BY ?name";

        $results = $this->connection->select($selectQuery);

        // Verify both results returned
        $this->assertCount(2, $results);

        // Alice should have email
        $this->assertEquals('Alice', $results[0]->name);
        $this->assertEquals('alice@example.org', $results[0]->email);

        // Bob should have no email (or null/empty)
        $this->assertEquals('Bob', $results[1]->name);
        $this->assertTrue(! isset($results[1]->email) || empty($results[1]->email));
    }
}
