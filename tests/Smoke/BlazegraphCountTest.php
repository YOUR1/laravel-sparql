<?php

namespace LinkedData\SPARQL\Tests\Smoke;

use Illuminate\Support\Facades\DB;
use LinkedData\SPARQL\Query\Expression;
use LinkedData\SPARQL\Tests\IntegrationTestCase;

/**
 * Smoke tests for count queries with Blazegraph namespaces.
 *
 * This test reproduces the issue where count() queries with table() + where(Expression::iri())
 * fail when executed against a real Blazegraph instance, even though the generated SPARQL is correct.
 */
class BlazegraphCountTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure for Blazegraph with namespace
        config(['database.connections.sparql' => [
            'driver' => 'sparql',
            'endpoint' => env('SPARQL_ENDPOINT', 'http://localhost:9999/bigdata/sparql'),
            'host' => env('SPARQL_ENDPOINT', 'http://localhost:9999/bigdata/sparql'),
            'implementation' => 'blazegraph',
            'auth' => ['type' => 'none'],
            'namespaces' => [
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                'skos' => 'http://www.w3.org/2004/02/skos/core#',
            ],
        ]]);

        // Reconnect with new config
        $this->connection = app('db')->connection('sparql');
    }

    /** @test */
    public function it_can_count_with_query_builder_and_namespace()
    {
        $namespace = env('SPARQL_NAMESPACE', 'tenant_begrippen_ds_hunze-en-aas');
        $schemeIri = 'http://example.com/scheme/test123';
        $conceptIri = 'http://www.w3.org/2004/02/skos/core#Concept';
        $inSchemeRelationIri = 'http://www.w3.org/2004/02/skos/core#inScheme';

        // Try to count using the query builder without inserting data
        // We're just testing that the query executes without HTTP errors
        try {
            $count = DB::connection('sparql')
                ->graph('')
                ->namespace($namespace)
                ->table($conceptIri)
                ->where($inSchemeRelationIri, Expression::iri($schemeIri))
                ->count();

            // The count might be 0 if there's no data, but the query should execute successfully
            $this->assertIsInt($count, 'Count should return an integer');
            $this->assertGreaterThanOrEqual(0, $count, 'Count should be non-negative');
        } catch (\Exception $e) {
            // Capture the error details
            $this->fail('Count query failed: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_count_with_raw_sparql_query()
    {
        $namespace = env('SPARQL_NAMESPACE', 'tenant_begrippen_ds_hunze-en-aas');
        $schemeIri = 'http://example.com/scheme/rawtest456';
        $conceptIri = 'http://www.w3.org/2004/02/skos/core#Concept';
        $inSchemeRelationIri = 'http://www.w3.org/2004/02/skos/core#inScheme';

        // Try with raw SPARQL (testing that it executes without HTTP errors)
        $rawQuery = "
            PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
            SELECT (COUNT(DISTINCT ?concept) as ?count)
            WHERE {
                ?concept <{$inSchemeRelationIri}> <{$schemeIri}> .
                ?concept rdf:type <{$conceptIri}> .
            }
        ";

        try {
            $results = DB::connection('sparql')
                ->graph('')
                ->namespace($namespace)
                ->select($rawQuery);

            $count = (int) ((string) $results[0]->count);
            // The count might be 0 if there's no data, but the query should execute successfully
            $this->assertIsInt($count, 'Raw query should return an integer count');
            $this->assertGreaterThanOrEqual(0, $count, 'Count should be non-negative');
        } catch (\Exception $e) {
            $this->fail('Raw count query failed: ' . $e->getMessage());
        }
    }
}
