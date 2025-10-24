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
}
