<?php

namespace LinkedData\SPARQL\Tests\Unit;

use Illuminate\Support\Facades\DB;
use LinkedData\SPARQL\Query\Expression;
use LinkedData\SPARQL\Tests\TestCase;

class BlazegraphQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure for Blazegraph
        config(['database.connections.sparql' => [
            'driver' => 'sparql',
            'endpoint' => 'http://localhost:9999/bigdata/sparql',
            'host' => 'http://localhost:9999/bigdata/sparql',
            'implementation' => 'blazegraph',
            'auth' => ['type' => 'none'],
            'namespaces' => [
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                'skos' => 'http://www.w3.org/2004/02/skos/core#',
            ],
        ]]);
    }

    /** @test */
    public function it_generates_correct_sparql_for_count_with_namespace_and_no_graph()
    {
        $namespace = 'test_namespace';
        $schemeIri = 'http://example.com/scheme/1';
        $conceptIri = 'http://www.w3.org/2004/02/skos/core#Concept';
        $inSchemeRelationIri = 'http://www.w3.org/2004/02/skos/core#inScheme';

        // Build the query but don't execute it
        $query = DB::connection('sparql')
            ->graph('')  // Disable graph usage
            ->namespace($namespace)
            ->table($conceptIri)
            ->where($inSchemeRelationIri, Expression::iri($schemeIri));

        $generatedSql = $query->toSql();

        // The query should NOT contain a FROM clause when graph is empty
        $this->assertStringNotContainsString('FROM <', $generatedSql, 'Query should not have FROM clause when graph is empty');
        $this->assertStringNotContainsString('FROM', $generatedSql, 'Query should not have any FROM clause');

        // The query should have WHERE clause
        $this->assertStringContainsString('WHERE', $generatedSql, 'Query should have WHERE clause');

        // Should contain the proper triple patterns
        $this->assertStringContainsString("<{$inSchemeRelationIri}>", $generatedSql, 'Query should contain inScheme relation');
        $this->assertStringContainsString("<{$schemeIri}>", $generatedSql, 'Query should contain scheme IRI');
        $this->assertStringContainsString('<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>', $generatedSql, 'Query should contain rdf:type');
    }

    /** @test */
    public function it_generates_count_aggregate_with_proper_parentheses()
    {
        $conceptIri = 'http://www.w3.org/2004/02/skos/core#Concept';

        // Build a count query
        $query = DB::connection('sparql')
            ->graph('')
            ->table($conceptIri);

        // Clone the query to get aggregate SQL
        $cloned = clone $query;
        $cloned->aggregate = ['function' => 'count', 'columns' => [$query->unique_subject]];

        $generatedSql = $cloned->toSql();

        // The aggregate should be properly wrapped in parentheses
        // SPARQL requires: SELECT (COUNT(?var) AS ?aggregate)
        // Not: SELECT COUNT(?var) AS ?aggregate
        $this->assertMatchesRegularExpression(
            '/select\s+\(count\([^\)]+\)\s+as\s+\?aggregate\)/i',
            $generatedSql,
            'COUNT aggregate must be wrapped in parentheses like: SELECT (COUNT(?var) AS ?aggregate)'
        );

        // Should NOT have the old broken format
        $this->assertDoesNotMatchRegularExpression(
            '/select\s+count\([^\)]+\)\s+as\s+\?aggregate\s+WHERE/i',
            $generatedSql,
            'Should not have broken format: SELECT COUNT(?var) AS ?aggregate WHERE'
        );
    }

    /** @test */
    public function it_generates_correct_sparql_for_other_aggregates()
    {
        $propertyIri = 'http://example.com/property';

        // Test various aggregates
        $aggregates = [
            'sum' => 'sum',
            'avg' => 'avg',
            'min' => 'min',
            'max' => 'max',
        ];

        foreach ($aggregates as $function) {
            $query = DB::connection('sparql')
                ->graph('')
                ->table($propertyIri);

            $query->aggregate = ['function' => $function, 'columns' => ['?value']];

            $generatedSql = $query->toSql();

            // All aggregates should be wrapped in parentheses
            $this->assertMatchesRegularExpression(
                "/select\s+\({$function}\([^\)]+\)\s+as\s+\?aggregate\)/i",
                $generatedSql,
                "{$function} aggregate must be wrapped in parentheses"
            );
        }
    }

    /** @test */
    public function it_generates_correct_sparql_for_group_concat_with_separator()
    {
        $propertyIri = 'http://example.com/property';

        $query = DB::connection('sparql')
            ->graph('')
            ->table($propertyIri);

        $query->aggregate = ['function' => 'group_concat_separator_,', 'columns' => ['?value']];

        $generatedSql = $query->toSql();

        // GROUP_CONCAT with separator should also be wrapped
        $this->assertMatchesRegularExpression(
            '/select\s+\(group_concat\([^\)]+;\s*separator="[^"]+"\)\s+as\s+\?aggregate\)/i',
            $generatedSql,
            'GROUP_CONCAT with separator must be wrapped in parentheses'
        );
    }

    /** @test */
    public function it_generates_correct_sparql_for_count_with_where_and_expression_iri()
    {
        $namespace = 'test_namespace';
        $schemeIri = 'http://example.com/scheme/1';
        $conceptIri = 'http://www.w3.org/2004/02/skos/core#Concept';
        $inSchemeRelationIri = 'http://www.w3.org/2004/02/skos/core#inScheme';

        // Build a count query with where clause using Expression::iri
        $query = DB::connection('sparql')
            ->graph('')
            ->namespace($namespace)
            ->table($conceptIri)
            ->where($inSchemeRelationIri, Expression::iri($schemeIri));

        $generatedSql = $query->toSql();

        // Should have the inScheme triple pattern with IRI (not literal)
        $this->assertStringContainsString("<{$inSchemeRelationIri}>", $generatedSql);
        $this->assertStringContainsString("<{$schemeIri}>", $generatedSql, 'Scheme IRI should be wrapped in angle brackets');

        // Should NOT have the IRI as a string literal
        $this->assertStringNotContainsString("\"{$schemeIri}\"", $generatedSql, 'IRI should not be quoted as literal');

        // The query should have proper WHERE clause structure
        $this->assertStringContainsString('WHERE', $generatedSql);
        $this->assertStringContainsString('<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>', $generatedSql);
    }

    /**
     * @test
     * This test validates that count() generates proper SPARQL query
     * with table() + where() + Expression::iri() without executing it
     */
    public function it_generates_correct_sparql_for_count_aggregate_with_expression_iri()
    {
        $namespace = 'test_namespace';
        $schemeIri = 'http://example.com/scheme/1';
        $conceptIri = 'http://www.w3.org/2004/02/skos/core#Concept';
        $inSchemeRelationIri = 'http://www.w3.org/2004/02/skos/core#inScheme';

        // Build a count query without executing it
        $query = DB::connection('sparql')
            ->graph('')
            ->namespace($namespace)
            ->table($conceptIri)
            ->where($inSchemeRelationIri, Expression::iri($schemeIri));

        // Clone and set aggregate to get the count query SQL
        $cloned = clone $query;
        $cloned->aggregate = ['function' => 'count', 'columns' => [$query->unique_subject]];

        $generatedSql = $cloned->toSql();

        // The aggregate should be properly wrapped in parentheses
        // SPARQL requires: SELECT (COUNT(?var) AS ?aggregate)
        $this->assertMatchesRegularExpression(
            '/select\s+\(count\([^\)]+\)\s+as\s+\?aggregate\)/i',
            $generatedSql,
            'COUNT aggregate must be wrapped in parentheses'
        );

        // Should have WHERE clause with both triple patterns
        $this->assertStringContainsString('WHERE', $generatedSql);
        $this->assertStringContainsString('<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>', $generatedSql);
        $this->assertStringContainsString("<{$inSchemeRelationIri}>", $generatedSql);

        // The IRI should be properly wrapped in angle brackets
        $this->assertStringContainsString("<{$schemeIri}>", $generatedSql, 'Scheme IRI should be wrapped in angle brackets');

        // Should NOT have the IRI as a string literal
        $this->assertStringNotContainsString("\"{$schemeIri}\"", $generatedSql, 'IRI should not be quoted as literal');

        // Should NOT have FROM clause when graph is empty
        $this->assertStringNotContainsString('FROM <', $generatedSql, 'Query should not have FROM clause when graph is empty');
    }
}
