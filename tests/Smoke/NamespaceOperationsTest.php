<?php

namespace LinkedData\SPARQL\Tests\Smoke;

use LinkedData\SPARQL\Tests\IntegrationTestCase;

/**
 * Integration tests for Blazegraph namespace operations.
 *
 * These tests require a running Blazegraph instance with namespace support.
 * They create a test namespace, insert data, verify operations, and clean up.
 *
 * Run with: SPARQL_IMPLEMENTATION=blazegraph vendor/bin/phpunit tests/Smoke/NamespaceOperationsTest.php
 */
class NamespaceOperationsTest extends IntegrationTestCase
{
    /**
     * Test namespace - isolated from production data.
     */
    protected string $testNamespace = 'laravel_sparql_test_namespace';

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if not using Blazegraph
        if (! $this->isBlazegraph()) {
            $this->markTestSkipped('Namespace tests require Blazegraph implementation');
        }

        // Clear test namespace before each test
        $this->clearTestNamespace();
    }

    protected function tearDown(): void
    {
        // Clean up test namespace after each test
        $this->clearTestNamespace();

        parent::tearDown();
    }

    /**
     * Check if we're using Blazegraph.
     */
    protected function isBlazegraph(): bool
    {
        return $this->connection->getAdapter()->supportsNamespaces();
    }

    /**
     * Clear all triples from the test namespace.
     */
    protected function clearTestNamespace(): void
    {
        try {
            // Switch to test namespace and clear it
            $this->connection->withinNamespace($this->testNamespace, function ($query) {
                // Use DELETE WHERE instead of CLEAR to be more compatible
                $deleteQuery = 'DELETE WHERE { ?s ?p ?o }';
                $this->connection->statement($deleteQuery);
            });
        } catch (\Exception $e) {
            // If namespace doesn't exist yet or is already empty, that's fine
        }
    }

    /** @test */
    public function it_can_insert_data_into_namespace(): void
    {
        // Insert test data into the namespace
        $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $triples = [
                '<http://example.org/test/person/1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Person> .',
                '<http://example.org/test/person/1> <http://schema.org/name> "Test Person" .',
            ];

            $triplesStr = implode("\n", $triples);
            $insertQuery = "INSERT DATA { {$triplesStr} }";
            $this->connection->statement($insertQuery);
        });

        // Verify the data exists in the namespace
        $result = $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $askQuery = 'ASK { <http://example.org/test/person/1> <http://schema.org/name> "Test Person" }';

            return $this->connection->select($askQuery);
        });

        $this->assertTrue($result->isTrue());
    }

    /** @test */
    public function it_can_query_data_from_namespace(): void
    {
        // Insert test data
        $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $triples = [
                '<http://example.org/test/book/1> <http://schema.org/name> "Test Book" .',
                '<http://example.org/test/book/1> <http://schema.org/author> "Test Author" .',
            ];

            $triplesStr = implode("\n", $triples);
            $insertQuery = "INSERT DATA { {$triplesStr} }";
            $this->connection->statement($insertQuery);
        });

        // Query the data
        $result = $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $selectQuery = 'SELECT ?name ?author WHERE {
                <http://example.org/test/book/1> <http://schema.org/name> ?name .
                <http://example.org/test/book/1> <http://schema.org/author> ?author
            }';

            return $this->connection->select($selectQuery);
        });

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertEquals('Test Book', (string) $rows[0]->name);
        $this->assertEquals('Test Author', (string) $rows[0]->author);
    }

    /** @test */
    public function it_can_count_triples_in_namespace(): void
    {
        // Insert multiple test triples
        $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $triples = [
                '<http://example.org/test/item/1> <http://schema.org/name> "Item 1" .',
                '<http://example.org/test/item/2> <http://schema.org/name> "Item 2" .',
                '<http://example.org/test/item/3> <http://schema.org/name> "Item 3" .',
            ];

            $triplesStr = implode("\n", $triples);
            $insertQuery = "INSERT DATA { {$triplesStr} }";
            $this->connection->statement($insertQuery);
        });

        // Count triples in namespace
        $count = $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $countQuery = 'SELECT (COUNT(*) as ?count) WHERE { ?s ?p ?o }';
            $result = $this->connection->select($countQuery);

            foreach ($result as $row) {
                $count = $row->count ?? 0;
                if ($count instanceof \EasyRdf\Literal) {
                    return (int) $count->getValue();
                }

                return (int) $count;
            }

            return 0;
        });

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_can_update_data_in_namespace(): void
    {
        // Insert initial data
        $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $triple = '<http://example.org/test/doc/1> <http://schema.org/name> "Old Name" .';
            $insertQuery = "INSERT DATA { {$triple} }";
            $this->connection->statement($insertQuery);
        });

        // Update the data
        $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $updateQuery = '
                DELETE { <http://example.org/test/doc/1> <http://schema.org/name> "Old Name" }
                INSERT { <http://example.org/test/doc/1> <http://schema.org/name> "New Name" }
                WHERE { <http://example.org/test/doc/1> <http://schema.org/name> "Old Name" }
            ';
            $this->connection->statement($updateQuery);
        });

        // Verify the update
        $result = $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $askQuery = 'ASK { <http://example.org/test/doc/1> <http://schema.org/name> "New Name" }';

            return $this->connection->select($askQuery);
        });

        $this->assertTrue($result->isTrue());
    }

    /** @test */
    public function it_can_delete_data_from_namespace(): void
    {
        // Insert test data
        $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $triple = '<http://example.org/test/temp/1> <http://schema.org/name> "To Delete" .';
            $insertQuery = "INSERT DATA { {$triple} }";
            $this->connection->statement($insertQuery);
        });

        // Delete the data
        $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $deleteQuery = 'DELETE DATA { <http://example.org/test/temp/1> <http://schema.org/name> "To Delete" }';
            $this->connection->statement($deleteQuery);
        });

        // Verify deletion
        $result = $this->connection->withinNamespace($this->testNamespace, function ($query) {
            $askQuery = 'ASK { <http://example.org/test/temp/1> <http://schema.org/name> "To Delete" }';

            return $this->connection->select($askQuery);
        });

        $this->assertFalse($result->isTrue());
    }

    /** @test */
    public function namespaces_are_isolated_from_each_other(): void
    {
        $otherNamespace = 'laravel_sparql_test_namespace_2';

        try {
            // Insert data in first namespace
            $this->connection->withinNamespace($this->testNamespace, function ($query) {
                $triple = '<http://example.org/test/isolated/1> <http://schema.org/name> "Namespace 1" .';
                $insertQuery = "INSERT DATA { {$triple} }";
                $this->connection->statement($insertQuery);
            });

            // Insert different data in second namespace
            $this->connection->withinNamespace($otherNamespace, function ($query) {
                $triple = '<http://example.org/test/isolated/1> <http://schema.org/name> "Namespace 2" .';
                $insertQuery = "INSERT DATA { {$triple} }";
                $this->connection->statement($insertQuery);
            });

            // Verify data in first namespace
            $result1 = $this->connection->withinNamespace($this->testNamespace, function ($query) {
                $askQuery = 'ASK { <http://example.org/test/isolated/1> <http://schema.org/name> "Namespace 1" }';

                return $this->connection->select($askQuery);
            });

            // Verify data in second namespace
            $result2 = $this->connection->withinNamespace($otherNamespace, function ($query) {
                $askQuery = 'ASK { <http://example.org/test/isolated/1> <http://schema.org/name> "Namespace 2" }';

                return $this->connection->select($askQuery);
            });

            $this->assertTrue($result1->isTrue(), 'First namespace should contain its own data');
            $this->assertTrue($result2->isTrue(), 'Second namespace should contain its own data');

            // Verify cross-namespace isolation
            $crossCheck = $this->connection->withinNamespace($this->testNamespace, function ($query) {
                $askQuery = 'ASK { <http://example.org/test/isolated/1> <http://schema.org/name> "Namespace 2" }';

                return $this->connection->select($askQuery);
            });

            $this->assertFalse($crossCheck->isTrue(), 'First namespace should not see second namespace data');
        } finally {
            // Clean up second namespace
            try {
                $this->connection->withinNamespace($otherNamespace, function ($query) {
                    $deleteQuery = 'DELETE WHERE { ?s ?p ?o }';
                    $this->connection->statement($deleteQuery);
                });
            } catch (\Exception $e) {
                // Cleanup failed, but test already ran
            }
        }
    }

    /** @test */
    public function it_can_use_query_builder_with_namespace(): void
    {
        // Insert test data using query builder
        $this->connection->namespace($this->testNamespace)
            ->statement('INSERT DATA { <http://example.org/test/qb/1> <http://schema.org/name> "Query Builder Test" }');

        // Query using query builder
        $result = $this->connection->namespace($this->testNamespace)
            ->select('ASK { <http://example.org/test/qb/1> <http://schema.org/name> "Query Builder Test" }');

        $this->assertTrue($result->isTrue());
    }
}
