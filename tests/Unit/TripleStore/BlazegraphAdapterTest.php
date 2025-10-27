<?php

namespace LinkedData\SPARQL\Tests\Unit\TripleStore;

use LinkedData\SPARQL\TripleStore\BlazegraphAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BlazegraphAdapter.
 *
 * Tests the Blazegraph-specific conventions for Graph Store Protocol.
 */
class BlazegraphAdapterTest extends TestCase
{
    private BlazegraphAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new BlazegraphAdapter;
    }

    /** @test */
    public function it_returns_correct_adapter_name(): void
    {
        $this->assertEquals('blazegraph', $this->adapter->getName());
    }

    /** @test */
    public function it_keeps_sparql_endpoint_unchanged(): void
    {
        $queryEndpoint = 'http://localhost:9999/bigdata/sparql';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        // Blazegraph uses the same endpoint for both SPARQL and GSP
        $this->assertEquals('http://localhost:9999/bigdata/sparql', $gspEndpoint);
    }

    /** @test */
    public function it_keeps_namespace_endpoint_unchanged(): void
    {
        $queryEndpoint = 'http://localhost:9999/blazegraph/namespace/kb/sparql';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://localhost:9999/blazegraph/namespace/kb/sparql', $gspEndpoint);
    }

    /** @test */
    public function it_keeps_custom_paths_unchanged(): void
    {
        $queryEndpoint = 'http://example.com/my/custom/path/sparql';
        $gspEndpoint = $this->adapter->deriveGspEndpoint($queryEndpoint);

        $this->assertEquals('http://example.com/my/custom/path/sparql', $gspEndpoint);
    }

    /** @test */
    public function it_builds_gsp_url_without_graph(): void
    {
        $gspEndpoint = 'http://localhost:9999/bigdata/sparql';
        $url = $this->adapter->buildGspUrl($gspEndpoint, null);

        $this->assertEquals('http://localhost:9999/bigdata/sparql', $url);
    }

    /** @test */
    public function it_builds_gsp_url_with_graph(): void
    {
        $gspEndpoint = 'http://localhost:9999/bigdata/sparql';
        $graph = 'http://example.org/my-graph';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        $expectedUrl = 'http://localhost:9999/bigdata/sparql?context-uri=' . urlencode($graph);
        $this->assertEquals($expectedUrl, $url);
    }

    /** @test */
    public function it_uses_context_uri_parameter_name(): void
    {
        $gspEndpoint = 'http://localhost:9999/bigdata/sparql';
        $graph = 'http://example.org/my-graph';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        // Blazegraph uses ?context-uri= (not ?graph=)
        $this->assertStringContainsString('?context-uri=', $url);
        $this->assertStringNotContainsString('?graph=', $url);
    }

    /** @test */
    public function it_encodes_graph_uri_in_url(): void
    {
        $gspEndpoint = 'http://localhost:9999/bigdata/sparql';
        $graph = 'http://example.org/my graph with spaces';
        $url = $this->adapter->buildGspUrl($gspEndpoint, $graph);

        // Check that the graph URI is properly URL-encoded
        $this->assertStringContainsString(urlencode($graph), $url);
    }

    /** @test */
    public function it_returns_correct_ntriples_content_type(): void
    {
        $contentType = $this->adapter->getNTriplesContentType();

        // Blazegraph uses text/plain, not application/n-triples
        $this->assertEquals('text/plain', $contentType);
    }

    /** @test */
    public function it_recognizes_successful_response_with_xml(): void
    {
        $statusCode = 200;
        $responseBody = '<?xml version="1.0"?><data modified="1" milliseconds="58"/>';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_recognizes_successful_response_with_multiple_modifications(): void
    {
        $statusCode = 200;
        $responseBody = '<?xml version="1.0"?><data modified="100" milliseconds="234"/>';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_recognizes_successful_response_with_zero_modifications(): void
    {
        $statusCode = 200;
        $responseBody = '<?xml version="1.0"?><data modified="0" milliseconds="12"/>';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_accepts_2xx_status_codes_without_xml(): void
    {
        $statusCode = 204; // No Content
        $responseBody = '';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_accepts_201_created_status(): void
    {
        $statusCode = 201;
        $responseBody = '<?xml version="1.0"?><data modified="1" milliseconds="45"/>';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_rejects_4xx_status_codes(): void
    {
        $statusCode = 400;
        $responseBody = '<html><body>Bad Request</body></html>';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertFalse($isSuccess);
    }

    /** @test */
    public function it_rejects_404_not_found(): void
    {
        $statusCode = 404;
        $responseBody = '<html><body>Not Found</body></html>';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertFalse($isSuccess);
    }

    /** @test */
    public function it_rejects_5xx_status_codes(): void
    {
        $statusCode = 500;
        $responseBody = '<html><body>Internal Server Error</body></html>';

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

    /** @test */
    public function it_handles_xml_response_without_declaration(): void
    {
        $statusCode = 200;
        $responseBody = '<data modified="5" milliseconds="100"/>';

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }

    /** @test */
    public function it_handles_response_with_whitespace(): void
    {
        $statusCode = 200;
        $responseBody = "\n  <?xml version=\"1.0\"?><data modified=\"1\" milliseconds=\"50\"/>  \n";

        $isSuccess = $this->adapter->isSuccessResponse($statusCode, $responseBody);

        $this->assertTrue($isSuccess);
    }
}
