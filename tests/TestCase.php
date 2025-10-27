<?php

namespace LinkedData\SPARQL\Tests;

use LinkedData\SPARQL\SPARQLEndpointServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SPARQLEndpointServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use SPARQL
        $app['config']->set('database.default', 'sparql');
        $app['config']->set('database.connections.sparql', [
            'driver' => 'sparql',
            'host' => env('SPARQL_ENDPOINT', 'http://localhost:3030/test/sparql'),
            'update_endpoint' => env('SPARQL_UPDATE_ENDPOINT', 'http://localhost:3030/test/update'),
            'implementation' => env('SPARQL_IMPLEMENTATION', 'fuseki'), // fuseki|blazegraph|generic
            'graph' => env('SPARQL_GRAPH', 'http://example.org/test-graph'),
            'auth' => [
                'type' => env('SPARQL_AUTH_TYPE', 'basic'),
                'username' => env('SPARQL_USERNAME', 'admin'),
                'password' => env('SPARQL_PASSWORD', 'admin'),
            ],
            'namespaces' => [
                'xsd' => 'http://www.w3.org/2001/XMLSchema#',
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                'owl' => 'http://www.w3.org/2002/07/owl#',
                'foaf' => 'http://xmlns.com/foaf/0.1/',
                'schema' => 'http://schema.org/',
                'geo' => 'http://www.geonames.org/ontology#',
            ],
        ]);
    }
}
