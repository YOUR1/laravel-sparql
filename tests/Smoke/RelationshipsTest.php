<?php

namespace LinkedData\SPARQL\Tests\Smoke;

use LinkedData\SPARQL\Tests\IntegrationTestCase;

/**
 * Smoke tests for relationship queries.
 *
 * These tests verify that basic relationship patterns work correctly
 * across different SPARQL triple store implementations.
 */
class RelationshipsTest extends IntegrationTestCase
{
    /** @test */
    public function it_can_query_related_resources(): void
    {
        // Insert related data
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Person> .',
            '<http://example.com/person/1> <http://schema.org/name> "Alice" .',
            '<http://example.com/post/1> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/BlogPosting> .',
            '<http://example.com/post/1> <http://schema.org/author> <http://example.com/person/1> .',
            '<http://example.com/post/1> <http://schema.org/headline> "Test Post" .',
        ]);

        // Query for person and their posts
        $query = '
            SELECT ?personName ?postHeadline
            WHERE {
                ?person <http://schema.org/name> ?personName .
                ?post <http://schema.org/author> ?person .
                ?post <http://schema.org/headline> ?postHeadline .
            }
        ';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', (string) $rows[0]->personName);
        $this->assertEquals('Test Post', (string) $rows[0]->postHeadline);
    }

    /** @test */
    public function it_can_query_with_optional_relationships(): void
    {
        // Insert data with and without relationships
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/2> <http://schema.org/name> "Bob" .',
            '<http://example.com/person/3> <http://schema.org/name> "Carol" .',
            '<http://example.com/person/3> <http://schema.org/email> "carol@example.com" .',
        ]);

        // Query with OPTIONAL
        $query = '
            SELECT ?name ?email
            WHERE {
                ?person <http://schema.org/name> ?name .
                OPTIONAL { ?person <http://schema.org/email> ?email }
            }
        ';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $this->assertGreaterThanOrEqual(2, count($rows));

        // Find Bob (no email)
        $bob = array_filter($rows, fn ($row) => (string) $row->name === 'Bob');
        $this->assertNotEmpty($bob);

        // Find Carol (has email)
        $carol = array_filter($rows, fn ($row) => (string) $row->name === 'Carol');
        $this->assertNotEmpty($carol);
    }

    /** @test */
    public function it_can_query_nested_relationships(): void
    {
        // Insert nested data: Person -> Post -> Comment
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/4> <http://schema.org/name> "David" .',
            '<http://example.com/post/2> <http://schema.org/author> <http://example.com/person/4> .',
            '<http://example.com/post/2> <http://schema.org/headline> "My Post" .',
            '<http://example.com/comment/1> <http://schema.org/about> <http://example.com/post/2> .',
            '<http://example.com/comment/1> <http://schema.org/text> "Great post!" .',
        ]);

        // Query across multiple relationships
        $query = '
            SELECT ?authorName ?postHeadline ?commentText
            WHERE {
                ?post <http://schema.org/author> ?author .
                ?author <http://schema.org/name> ?authorName .
                ?post <http://schema.org/headline> ?postHeadline .
                ?comment <http://schema.org/about> ?post .
                ?comment <http://schema.org/text> ?commentText .
            }
        ';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertEquals('David', (string) $rows[0]->authorName);
        $this->assertEquals('My Post', (string) $rows[0]->postHeadline);
        $this->assertEquals('Great post!', (string) $rows[0]->commentText);
    }

    /** @test */
    public function it_can_count_related_resources(): void
    {
        // Insert person with multiple posts
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/5> <http://schema.org/name> "Eve" .',
            '<http://example.com/post/3> <http://schema.org/author> <http://example.com/person/5> .',
            '<http://example.com/post/4> <http://schema.org/author> <http://example.com/person/5> .',
            '<http://example.com/post/5> <http://schema.org/author> <http://example.com/person/5> .',
        ]);

        // Count posts per author
        $query = '
            SELECT ?authorName (COUNT(?post) as ?postCount)
            WHERE {
                ?person <http://schema.org/name> ?authorName .
                ?post <http://schema.org/author> ?person .
            }
            GROUP BY ?authorName
        ';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $eve = array_filter($rows, fn ($row) => (string) $row->authorName === 'Eve');
        $this->assertNotEmpty($eve);

        $eveRow = reset($eve);
        $count = $eveRow->postCount;
        if ($count instanceof \EasyRdf\Literal) {
            $count = (int) $count->getValue();
        }
        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_can_filter_by_relationship(): void
    {
        // Insert multiple people and posts
        $this->insertTestTriplesIntoDefaultGraph([
            '<http://example.com/person/6> <http://schema.org/name> "Frank" .',
            '<http://example.com/person/7> <http://schema.org/name> "Grace" .',
            '<http://example.com/post/6> <http://schema.org/author> <http://example.com/person/6> .',
            '<http://example.com/post/6> <http://schema.org/headline> "Frank Post" .',
            '<http://example.com/post/7> <http://schema.org/author> <http://example.com/person/7> .',
            '<http://example.com/post/7> <http://schema.org/headline> "Grace Post" .',
        ]);

        // Query for specific person's posts
        $query = '
            SELECT ?headline
            WHERE {
                ?person <http://schema.org/name> "Frank" .
                ?post <http://schema.org/author> ?person .
                ?post <http://schema.org/headline> ?headline .
            }
        ';
        $result = $this->connection->select($query);

        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
        $this->assertEquals('Frank Post', (string) $rows[0]->headline);
    }

    protected function tearDown(): void
    {
        // Clean up default graph after each test
        $this->clearDefaultGraph();
        parent::tearDown();
    }
}
