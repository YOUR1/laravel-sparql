<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Connection;
use LinkedData\SPARQL\Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function test_connection_can_be_created(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:3030/test/sparql',
            'namespaces' => [
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            ],
        ];

        $connection = new Connection($config);

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertEquals('sparql', $connection->getDriverName());
    }

    public function test_connection_has_query_builder(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:3030/test/sparql',
        ];

        $connection = new Connection($config);
        $query = $connection->query();

        $this->assertInstanceOf(\LinkedData\SPARQL\Query\Builder::class, $query);
    }

    public function test_connection_registers_rdf_namespaces(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:3030/test/sparql',
            'namespaces' => [
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                'owl' => 'http://www.w3.org/2002/07/owl#',
                'foaf' => 'http://xmlns.com/foaf/0.1/',
            ],
        ];

        $connection = new Connection($config);
        $namespaces = $connection->getRdfNamespaces();

        $this->assertArrayHasKey('rdf', $namespaces);
        $this->assertArrayHasKey('rdfs', $namespaces);
        $this->assertArrayHasKey('owl', $namespaces);
        $this->assertArrayHasKey('foaf', $namespaces);
    }

    public function test_connection_can_set_graph(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:3030/test/sparql',
            'graph' => 'http://example.org/graph',
        ];

        $connection = new Connection($config);

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function test_connection_can_set_namespace(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9090/bigdata/sparql',
            'implementation' => 'blazegraph',
            'namespace' => 'test_namespace',
        ];

        $connection = new Connection($config);

        $this->assertEquals('test_namespace', $connection->getNamespace());
    }

    public function test_connection_namespace_method_sets_namespace(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9090/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $connection->namespace('my_namespace');

        $this->assertEquals('my_namespace', $connection->getNamespace());
    }

    public function test_connection_namespace_method_validates_namespace_name(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9090/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);

        $this->expectException(\InvalidArgumentException::class);
        $connection->namespace('invalid namespace with spaces');
    }

    public function test_connection_namespace_method_returns_self(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9090/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $result = $connection->namespace('test_namespace');

        $this->assertSame($connection, $result);
    }

    public function test_connection_within_namespace_restores_previous_namespace(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9090/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $connection->namespace('original_namespace');

        $capturedNamespace = null;
        $connection->withinNamespace('temporary_namespace', function ($query) use (&$capturedNamespace) {
            $capturedNamespace = $query->getNamespace();
        });

        $this->assertEquals('temporary_namespace', $capturedNamespace);
        $this->assertEquals('original_namespace', $connection->getNamespace());
    }

    public function test_query_builder_inherits_namespace_from_connection(): void
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:9090/bigdata/sparql',
            'implementation' => 'blazegraph',
        ];

        $connection = new Connection($config);
        $connection->namespace('test_namespace');

        $query = $connection->query();

        $this->assertEquals('test_namespace', $query->getNamespace());
    }
}
