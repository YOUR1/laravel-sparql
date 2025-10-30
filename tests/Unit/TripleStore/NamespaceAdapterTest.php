<?php

namespace LinkedData\SPARQL\Tests\Unit\TripleStore;

use LinkedData\SPARQL\TripleStore\BlazegraphAdapter;
use LinkedData\SPARQL\TripleStore\FusekiAdapter;
use LinkedData\SPARQL\TripleStore\GenericAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for namespace support across different adapters.
 */
class NamespaceAdapterTest extends TestCase
{
    /** @test */
    public function blazegraph_adapter_supports_namespaces(): void
    {
        $adapter = new BlazegraphAdapter;

        $this->assertTrue($adapter->supportsNamespaces());
    }

    /** @test */
    public function fuseki_adapter_does_not_support_namespaces(): void
    {
        $adapter = new FusekiAdapter;

        $this->assertFalse($adapter->supportsNamespaces());
    }

    /** @test */
    public function generic_adapter_does_not_support_namespaces(): void
    {
        $adapter = new GenericAdapter;

        $this->assertFalse($adapter->supportsNamespaces());
    }

    /** @test */
    public function fuseki_adapter_returns_unchanged_endpoint_for_namespace(): void
    {
        $adapter = new FusekiAdapter;
        $endpoint = 'http://localhost:3030/test/sparql';

        $result = $adapter->buildNamespaceEndpoint($endpoint, 'my_namespace');

        // Fuseki doesn't support namespaces, so it should return the endpoint unchanged
        $this->assertEquals($endpoint, $result);
    }

    /** @test */
    public function generic_adapter_returns_null_for_namespace_extraction(): void
    {
        $adapter = new GenericAdapter;

        $namespace = $adapter->extractNamespace('http://localhost:8080/sparql');

        $this->assertNull($namespace);
    }

    /** @test */
    public function blazegraph_adapter_extracts_namespace_correctly(): void
    {
        $adapter = new BlazegraphAdapter;

        $namespace = $adapter->extractNamespace('http://localhost:9999/bigdata/namespace/test_ns/sparql');

        $this->assertEquals('test_ns', $namespace);
    }

    /** @test */
    public function blazegraph_adapter_returns_null_for_non_namespace_endpoint(): void
    {
        $adapter = new BlazegraphAdapter;

        $namespace = $adapter->extractNamespace('http://localhost:9999/bigdata/sparql');

        $this->assertNull($namespace);
    }
}
