<?php

namespace LinkedData\SPARQL\Tests\Smoke;

use LinkedData\SPARQL\Tests\IntegrationTestCase;

/**
 * Smoke tests for basic CRUD operations.
 *
 * These tests verify that fundamental Create, Read, Update, Delete operations
 * work correctly across different SPARQL triple store implementations.
 *
 * Run these tests against each implementation to ensure compatibility.
 */
class BasicCrudTest extends IntegrationTestCase
{
    /** @test */
    public function it_can_create_a_resource(): void
    {
        // Insert a test triple
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Person> .',
            '<http://example.com/person/1> <http://schema.org/name> "John Doe" .',
        ]);

        // Verify it exists
        $query = 'ASK { <http://example.com/person/1> <http://schema.org/name> "John Doe" }';
        $result = $this->connection->select($query);

        $this->assertTrue($result->isTrue());
    }

    /** @test */
    public function it_can_read_a_resource(): void
    {
        // Insert test data
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/2> <http://schema.org/name> "Jane Smith" .',
            '<http://example.com/person/2> <http://schema.org/age> "30"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ]);

        // Query the data
        $query = 'SELECT ?name ?age WHERE { <http://example.com/person/2> <http://schema.org/name> ?name . <http://example.com/person/2> <http://schema.org/age> ?age }';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertEquals('Jane Smith', (string) $rows[0]->name);
        $this->assertEquals('30', (string) $rows[0]->age);
    }

    /** @test */
    public function it_can_update_a_resource(): void
    {
        // Insert initial data
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/3> <http://schema.org/name> "Old Name" .',
        ]);

        // Update the name
        $updateQuery = '
            DELETE { <http://example.com/person/3> <http://schema.org/name> "Old Name" }
            INSERT { <http://example.com/person/3> <http://schema.org/name> "New Name" }
            WHERE { <http://example.com/person/3> <http://schema.org/name> "Old Name" }
        ';
        $this->connection->statement($updateQuery);

        // Verify the update
        $query = 'ASK { <http://example.com/person/3> <http://schema.org/name> "New Name" }';
        $result = $this->connection->select($query);

        $this->assertTrue($result->isTrue());
    }

    /** @test */
    public function it_can_delete_a_resource(): void
    {
        // Insert test data
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/4> <http://schema.org/name> "To Delete" .',
        ]);

        // Delete the triple
        $deleteQuery = 'DELETE DATA { <http://example.com/person/4> <http://schema.org/name> "To Delete" }';
        $this->connection->statement($deleteQuery);

        // Verify deletion
        $query = 'ASK { <http://example.com/person/4> <http://schema.org/name> "To Delete" }';
        $result = $this->connection->select($query);

        $this->assertFalse($result->isTrue());
    }

    /** @test */
    public function it_can_count_triples(): void
    {
        // Insert test data
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/test/1> <http://schema.org/name> "Test 1" .',
            '<http://example.com/test/2> <http://schema.org/name> "Test 2" .',
            '<http://example.com/test/3> <http://schema.org/name> "Test 3" .',
        ]);

        // Count triples
        $count = $this->countTriplesInDefaultGraph();

        $this->assertGreaterThanOrEqual(3, $count);
    }

    /** @test */
    public function it_handles_special_characters_in_literals(): void
    {
        // Insert data with special characters
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/5> <http://schema.org/name> "O\'Reilly & Co." .',
        ]);

        // Query the data
        $query = 'SELECT ?name WHERE { <http://example.com/person/5> <http://schema.org/name> ?name }';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertEquals("O'Reilly & Co.", (string) $rows[0]->name);
    }

    /** @test */
    public function it_supports_filter_queries(): void
    {
        // Insert test data
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/6> <http://schema.org/age> "25"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            '<http://example.com/person/7> <http://schema.org/age> "35"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ]);

        // Query with filter
        $query = 'SELECT ?person WHERE { ?person <http://schema.org/age> ?age . FILTER(?age > 30) }';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('person/7', (string) $rows[0]->person);
    }

    protected function tearDown(): void
    {
        // Clean up default graph after each test
        $this->clearDefaultGraph();
        parent::tearDown();
    }
}
