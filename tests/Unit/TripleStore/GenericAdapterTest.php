<?php

namespace LinkedData\SPARQL\Tests\Unit\TripleStore;

use LinkedData\SPARQL\TripleStore\GenericAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GenericAdapter.
 *
 * Tests the W3C standard SPARQL 1.1 conventions for Graph Store Protocol.
 * This adapter should work with most SPARQL endpoints (Virtuoso, GraphDB, etc.).
 */
class GenericAdapterTest extends TestCase
{
    private GenericAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new GenericAdapter();
    }

    /** @test */
    public function it_returns_correct_adapter_name(): void
    {
        $this->assertEquals('generic', $this->adapter->getName());
    }

    /** @test */
    public function it_derives_gsp_endpoint_from_sparql_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:8890/sparql';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:8890/data', $gspEndpoint);
    }

    /** @test */
    public function it_derives_gsp_endpoint_from_update_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:8890/update';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:8890/data', $gspEndpoint);
    }

    /** @test */
    public function it_derives_gsp_endpoint_from_query_endpoint(): void
    {
        $queryEndpoint = 'http://localhost:8890/query';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:8890/data', $gspEndpoint);
    }

    /** @test */
    public function it_derives_gsp_endpoint_with_dataset_path(): void
    {
        $queryEndpoint = 'http://example.com/dataset/sparql';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://example.com/dataset/data', $gspEndpoint);
    }

    /** @test */
    public function it_appends_data_to_dataset_root(): void
    {
        $queryEndpoint = 'http://localhost:8890/test';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:8890/test/data', $gspEndpoint);
    }

    /** @test */
    public function it_handles_trailing_slashes(): void
    {
        $queryEndpoint = 'http://localhost:8890/sparql/';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:8890/data', $gspEndpoint);
    }

    /** @test */
    public function it_handles_complex_paths(): void
    {
        $queryEndpoint = 'http://example.com/rdf/datasets/mydata/sparql';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://example.com/rdf/datasets/mydata/data', $gspEndpoint);
    }

    /** @test */
    public function it_builds_gsp_url_without_graph(): void
    {
        $gspEndpoint = 'http://localhost:8890/data';
        $url = $this->adapter->buildGspUrl($gspEndpoint, null);

        $this->assertEquals('http://localhost:8890/data', $url);
    }

    /** @test */
    public function it_builds_gsp_url_with_graph(): void
    {
        $gspEndpoint = 'http://localhost:8890/data';
        $graph = 'http://example.org/my-graph';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        $expectedUrl = 'http://localhost:8890/data?graph=' . urlencode($graph);
        $this->assertEquals($expectedUrl, $url);
    }

    /** @test */
    public function it_uses_graph_parameter_name(): void
    {
        $gspEndpoint = 'http://localhost:8890/data';
        $graph = 'http://example.org/my-graph';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        // Generic uses standard ?graph= (not ?context-uri=)
        $this->assertStringContainsString('?graph=', $url);
        $this->assertStringNotContainsString('context-uri', $url);
    }

    /** @test */
    public function it_encodes_graph_uri_in_url(): void
    {
        $gspEndpoint = 'http://localhost:8890/data';
        $graph = 'http://example.org/my graph with spaces';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        // Check that the graph URI is properly URL-encoded
        $this->assertStringContainsString(urlencode($graph), $url);
    }

    /** @test */
    public function it_returns_correct_ntriples_content_type(): void
    {
        $contentType = $this->adapter->getNTriplesContentType();

        // W3C standard MIME type (without charset)
        $this->assertEquals('application/n-triples', $contentType);
    }

    /** @test */
    public function it_recognizes_200_ok_as_success(): void
    {
        $statusCode = 200;
        $responseBody = 'OK';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_recognizes_201_created_as_success(): void
    {
        $statusCode = 201;
        $responseBody = 'Created';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_recognizes_204_no_content_as_success(): void
    {
        $statusCode = 204;
        $responseBody = '';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_accepts_all_2xx_status_codes(): void
    {
        foreach ([200, 201, 202, 203, 204, 205, 206] as $statusCode) {
            $isSuccess = $this->adapter->isSuccessResponse($statusCode, '');
            $this->assertTrue($isSuccess, "Status code $statusCode should be success");
        }
    }

    /** @test */
    public function it_rejects_4xx_status_codes(): void
    {
        $statusCode = 400;
        $responseBody = 'Bad Request';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertFalse($isSuccess);
    }

    /** @test */
    public function it_rejects_404_not_found(): void
    {
        $statusCode = 404;
        $responseBody = 'Not Found';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertFalse($isSuccess);
    }

    /** @test */
    public function it_rejects_5xx_status_codes(): void
    {
        $statusCode = 500;
        $responseBody = 'Internal Server Error';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertFalse($isSuccess);
    }

    /** @test */
    public function it_ignores_response_body_content(): void
    {
        // Generic adapter only looks at status code, not body
        $statusCode = 200;
        $responseBody = 'Any random content here';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_returns_empty_query_hints_by_default(): void
    {
        $hints = $this->adapter->getQueryHints();

        $this->assertIsArray($hints);
        $this->assertEmpty($hints);
    }
}
