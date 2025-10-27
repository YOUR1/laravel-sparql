<?php

namespace LinkedData\SPARQL\Tests\Unit\TripleStore;

use LinkedData\SPARQL\TripleStore\FusekiAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FusekiAdapter.
 *
 * Tests the Fuseki-specific conventions for Graph Store Protocol.
 */
class FusekiAdapterTest extends TestCase
{
    private FusekiAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new FusekiAdapter();
    }

    /** @test */
    public function it_returns_correct_adapter_name(): void
    {
        $this->assertEquals('fuseki', $this->adapter->getName());
    }

    /** @test */
    public function it_derives_gsp_endpoint_from_sparql_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:3030/test/sparql';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:3030/test', $gspEndpoint);
    }

    /** @test */
    public function it_derives_gsp_endpoint_from_update_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:3030/test/update';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:3030/test', $gspEndpoint);
    }

    /** @test */
    public function it_derives_gsp_endpoint_from_data_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:3030/test/data';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:3030/test', $gspEndpoint);
    }

    /** @test */
    public function it_derives_gsp_endpoint_from_query_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:3030/test/query';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:3030/test', $gspEndpoint);
    }

    /** @test */
    public function it_handles_dataset_root_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:3030/test';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:3030/test', $gspEndpoint);
    }

    /** @test */
    public function it_handles_trailing_slashes(): void
    {
        $queryEndpoint = 'http://localhost:3030/test/sparql/';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:3030/test', $gspEndpoint);
    }

    /** @test */
    public function it_builds_gsp_url_without_graph(): void
    {
        $gspEndpoint = 'http://localhost:3030/test';
        $url = $this->adapter->buildGspUrl($gspEndpoint, null);

        $this->assertEquals('http://localhost:3030/test', $url);
    }

    /** @test */
    public function it_builds_gsp_url_with_graph(): void
    {
        $gspEndpoint = 'http://localhost:3030/test';
        $graph = 'http://example.org/my-graph';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        $expectedUrl = 'http://localhost:3030/test?graph=' . urlencode($graph);
        $this->assertEquals($expectedUrl, $url);
    }

    /** @test */
    public function it_uses_graph_parameter_name(): void
    {
        $gspEndpoint = 'http://localhost:3030/test';
        $graph = 'http://example.org/my-graph';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        // Fuseki uses ?graph= (not ?context-uri=)
        $this->assertStringContainsString('?graph=', $url);
        $this->assertStringNotContainsString('context-uri', $url);
    }

    /** @test */
    public function it_encodes_graph_uri_in_url(): void
    {
        $gspEndpoint = 'http://localhost:3030/test';
        $graph = 'http://example.org/my graph with spaces';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        // Check that the graph URI is properly URL-encoded
        $this->assertStringContainsString(urlencode($graph), $url);
    }

    /** @test */
    public function it_returns_correct_ntriples_content_type(): void
    {
        $contentType = $this->adapter->getNTriplesContentType();

        $this->assertEquals('application/n-triples; charset=utf-8', $contentType);
    }

    /** @test */
    public function it_recognizes_successful_response_with_json(): void
    {
        $statusCode = 200;
        $responseBody = '{"count": 1, "tripleCount": 1, "quadCount": 0}';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_recognizes_successful_response_with_triple_count(): void
    {
        $statusCode = 200;
        $responseBody = '{"tripleCount": 5}';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_recognizes_successful_response_with_quad_count(): void
    {
        $statusCode = 200;
        $responseBody = '{"quadCount": 10}';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_accepts_2xx_status_codes_without_json(): void
    {
        $statusCode = 204; // No Content
        $responseBody = '';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_rejects_4xx_status_codes(): void
    {
        $statusCode = 400;
        $responseBody = '{"error": "Bad Request"}';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertFalse($isSuccess);
    }

    /** @test */
    public function it_rejects_5xx_status_codes(): void
    {
        $statusCode = 500;
        $responseBody = '{"error": "Internal Server Error"}';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertFalse($isSuccess);
    }

    /** @test */
    public function it_returns_empty_query_hints_by_default(): void
    {
        $hints = $this->adapter->getQueryHints();

        $this->assertIsArray($hints);
        $this->assertEmpty($hints);
    }
}
