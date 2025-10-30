<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Connection;
use LinkedData\SPARQL\Tests\TestCase;

/**
 * Unit tests for Blazegraph namespace support.
 *
 * These tests verify the namespace API and configuration WITHOUT
 * connecting to a real Blazegraph instance or touching any data.
 * They only test the Connection, Builder, and Adapter classes.
 *
 * For integration tests that actually insert/query data in namespaces,
 * see tests/Smoke/NamespaceOperationsTest.php
 */
class NamespaceFeaturesTest extends TestCase
{
    /** @test */
    public function it_can_create_connection_with_namespace_from_config(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
            'namespace' => 'tenant_begrippen_ds_abdl',
        ];

        $connection = new Connection($config);

        $this->assertEquals('tenant_begrippen_ds_abdl', $connection->getNamespace());
    }

    /** @test */
    public function it_can_set_namespace_fluently(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);

        $result = $connection->namespace('tenant_X_ds_Y');

        $this->assertSame($connection, $result);
        $this->assertEquals('tenant_X_ds_Y', $connection->getNamespace());
    }

    /** @test */
    public function it_validates_namespace_names(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);

        // Valid namespace names
        $connection->namespace('valid_namespace');
        $this->assertEquals('valid_namespace', $connection->getNamespace());

        $connection->namespace('tenant-123');
        $this->assertEquals('tenant-123', $connection->getNamespace());

        $connection->namespace('ns_2025_01');
        $this->assertEquals('ns_2025_01', $connection->getNamespace());

        // Invalid namespace names
        $this->expectException(\InvalidArgumentException::class);
        $connection->namespace('invalid namespace with spaces');
    }

    /** @test */
    public function query_builder_can_set_namespace(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $query = $connection->query();

        $result = $query->namespace('test_namespace');

        $this->assertSame($query, $result);
        $this->assertEquals('test_namespace', $query->getNamespace());
    }

    /** @test */
    public function query_builder_inherits_namespace_from_connection(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
            'namespace' => 'tenant_X_ds_Y',
        ];

        $connection = new Connection($config);
        $query = $connection->query();

        $this->assertEquals('tenant_X_ds_Y', $query->getNamespace());
    }

    /** @test */
    public function within_namespace_executes_callback_with_temporary_namespace(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $connection->namespace('original');

        $capturedNamespace = null;
        $result = $connection->withinNamespace('temporary', function ($query) use (&$capturedNamespace) {
            $capturedNamespace = $query->getNamespace();

            return 'test_result';
        });

        $this->assertEquals('temporary', $capturedNamespace);
        $this->assertEquals('original', $connection->getNamespace());
        $this->assertEquals('test_result', $result);
    }

    /** @test */
    public function within_namespace_restores_namespace_on_exception(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $connection->namespace('original');

        try {
            $connection->withinNamespace('temporary', function ($query) {
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException $e) {
            // Expected exception
        }

        // Namespace should be restored even after exception
        $this->assertEquals('original', $connection->getNamespace());
    }

    /** @test */
    public function namespace_can_be_chained_with_query_builder_methods(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $query = $connection->query()
            ->namespace('tenant_begrippen_ds_abdl')
            ->from('http://www.w3.org/2004/02/skos/core#Concept')
            ->where('http://www.w3.org/2004/02/skos/core#inScheme', '<http://example.org/scheme>');

        $this->assertEquals('tenant_begrippen_ds_abdl', $query->getNamespace());
        $this->assertStringContainsString('http://www.w3.org/2004/02/skos/core#Concept', $query->toSql());
    }

    /** @test */
    public function connection_can_build_namespace_specific_endpoint(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $adapter = $connection->getAdapter();

        $namespaceEndpoint = $adapter->buildNamespaceEndpoint(
            'http://localhost:9999/bigdata/sparql',
            'tenant_X_ds_Y'
        );

        $this->assertEquals(
            'http://localhost:9999/bigdata/namespace/tenant_X_ds_Y/sparql',
            $namespaceEndpoint
        );
    }

    /** @test */
    public function adapter_can_extract_namespace_from_endpoint(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $adapter = $connection->getAdapter();

        $namespace = $adapter->extractNamespace(
            'http://localhost:9999/bigdata/namespace/tenant_X_ds_Y/sparql'
        );

        $this->assertEquals('tenant_X_ds_Y', $namespace);
    }

    /** @test */
    public function adapter_can_check_if_endpoint_is_namespace_specific(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $adapter = $connection->getAdapter();

        $this->assertTrue($adapter->isNamespaceEndpoint(
            'http://localhost:9999/bigdata/namespace/my_ns/sparql'
        ));

        $this->assertFalse($adapter->isNamespaceEndpoint(
            'http://localhost:9999/bigdata/sparql'
        ));
    }
}
