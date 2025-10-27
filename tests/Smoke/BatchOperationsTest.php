<?php

namespace LinkedData\SPARQL\Tests\Smoke;

use LinkedData\SPARQL\Eloquent\GenericResource;
use LinkedData\SPARQL\Tests\IntegrationTestCase;

/**
 * Smoke tests for batch operations.
 *
 * These tests verify that Graph Store Protocol (GSP) bulk operations
 * work correctly across different SPARQL implementations.
 *
 * Critical for validating Fuseki, Blazegraph, and Generic adapter compatibility.
 */
class BatchOperationsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Batch operations in this test should use default graph, not test graph
        // This matches the expected behavior for smoke tests
        $this->connection->graph(null);
    }

    /** @test */
    public function it_can_batch_insert_multiple_resources(): void
    {
        // Create multiple resources
        $resources = [];
        for ($i = 1; $i <= 5; $i++) {
            $resource = GenericResource::make(
                "http://example.com/product/{$i}",
                'http://schema.org/Product'
            );
            $resource->setConnection('sparql');
            $resource->setAttribute('http://schema.org/name', "Product {$i}");
            $resource->setAttribute('http://schema.org/price', (float) $i * 10);
            $resources[] = $resource;
        }

        // Batch insert using GSP
        $result = GenericResource::insertBatch($resources);

        // Verify success
        $this->assertTrue($result);

        // Verify data exists
        $count = $this->countTriplesInDefaultGraph();
        $this->assertGreaterThanOrEqual(15, $count); // 5 resources × 3 triples each
    }

    /** @test */
    public function it_can_batch_delete_resources(): void
    {
        // Insert test data first
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/del/1> <http://schema.org/name> "Delete 1" .',
            '<http://example.com/del/2> <http://schema.org/name> "Delete 2" .',
            '<http://example.com/del/3> <http://schema.org/name> "Delete 3" .',
        ]);

        // Batch delete
        $uris = [
            'http://example.com/del/1',
            'http://example.com/del/2',
            'http://example.com/del/3',
        ];

        $deleted = GenericResource::deleteBatch($uris);

        // Verify deletion
        $this->assertEquals(3, $deleted);

        // Verify data is gone
        $query = 'ASK { ?s <http://schema.org/name> ?name . FILTER(CONTAINS(STR(?s), "del")) }';
        $result = $this->connection->select($query);
        $this->assertFalse($result->isTrue());
    }

    /** @test */
    public function it_handles_large_batch_inserts(): void
    {
        // Create 50 resources
        $resources = [];
        for ($i = 1; $i <= 50; $i++) {
            $resource = GenericResource::make(
                "http://example.com/large/{$i}",
                'http://schema.org/Thing'
            );
            $resource->setConnection('sparql');
            $resource->setAttribute('http://schema.org/name', "Item {$i}");
            $resources[] = $resource;
        }

        // Batch insert
        $result = GenericResource::insertBatch($resources);

        // Verify success
        $this->assertTrue($result);

        // Verify count
        $query = 'SELECT (COUNT(?s) as ?count) WHERE { ?s <http://schema.org/name> ?name . FILTER(CONTAINS(STR(?s), "large")) }';
        $result = $this->connection->select($query);
        $rows = iterator_to_array($result);

        $count = $rows[0]->count;
        if ($count instanceof \EasyRdf\Literal) {
            $count = (int) $count->getValue();
        }

        $this->assertEquals(50, $count);
    }

    /** @test */
    public function it_handles_empty_batch_operations(): void
    {
        // Empty insert
        $result = GenericResource::insertBatch([]);
        $this->assertTrue($result);

        // Empty delete
        $deleted = GenericResource::deleteBatch([]);
        $this->assertEquals(0, $deleted);
    }

    /** @test */
    public function it_can_insert_resources_with_multiple_properties(): void
    {
        $resource = GenericResource::make(
            'http://example.com/complex/1',
            'http://schema.org/Person'
        );
        $resource->setConnection('sparql');
        $resource->setAttribute('http://schema.org/name', 'Complex Person');
        $resource->setAttribute('http://schema.org/age', 25);
        $resource->setAttribute('http://schema.org/email', 'test@example.com');
        $resource->setAttribute('http://schema.org/url', 'http://example.com');

        $result = GenericResource::insertBatch([$resource]);

        $this->assertTrue($result);

        // Verify all properties
        $query = 'SELECT * WHERE { <http://example.com/complex/1> ?p ?o }';
        $result = $this->connection->select($query);
        $rows = iterator_to_array($result);

        $this->assertGreaterThanOrEqual(5, count($rows)); // rdf:type + 4 properties
    }

    protected function tearDown(): void
    {
        // Clean up default graph after each test
        $this->clearDefaultGraph();
        parent::tearDown();
    }
}
