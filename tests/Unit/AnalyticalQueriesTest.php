<?php

namespace LinkedData\SPARQL\Tests\Unit;

use Illuminate\Support\Facades\DB;
use LinkedData\SPARQL\Query\Expression;
use LinkedData\SPARQL\Tests\TestCase;

class AnalyticalQueriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure for Blazegraph
        config(['database.connections.sparql' => [
            'driver' => 'sparql',
            'endpoint' => 'http://localhost:9090/bigdata/sparql',
            'host' => 'http://localhost:9090/bigdata/sparql',
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
    public function it_can_build_queries_with_select_expressions()
    {
        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression('?language')
            ->selectExpression('(COUNT(?label) as ?count)')
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri('http://example.com/scheme'))
            ->whereTriple('?concept', 'skos:prefLabel', '?label')
            ->groupBy('?language');

        $sparql = $query->toSql();

        // Should have SELECT with expressions
        $this->assertStringContainsString('select', strtolower($sparql));
        $this->assertStringContainsString('?language', $sparql);
        $this->assertStringContainsString('COUNT(?label)', $sparql);
        $this->assertStringContainsString('?count', $sparql);

        // Should have WHERE with triple patterns
        $this->assertStringContainsString('WHERE', $sparql);
        $this->assertStringContainsString('inScheme', $sparql);
        $this->assertStringContainsString('prefLabel', $sparql);

        // Should have GROUP BY
        $this->assertStringContainsString('group by', strtolower($sparql));
        $this->assertStringContainsString('?language', $sparql);
    }

    /** @test */
    public function it_can_build_queries_with_bind_expressions()
    {
        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression('?language')
            ->selectExpression('(COUNT(?label) as ?count)')
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri('http://example.com/scheme'))
            ->whereTriple('?concept', 'skos:prefLabel', '?label')
            ->bind('COALESCE(LANG(?label), "no-lang")', '?language')
            ->groupBy('?language');

        $sparql = $query->toSql();

        // Should have BIND expression
        $this->assertStringContainsString('BIND', $sparql);
        $this->assertStringContainsString('COALESCE', $sparql);
        $this->assertStringContainsString('LANG(?label)', $sparql);
        $this->assertStringContainsString('AS ?language', $sparql);
    }

    /** @test */
    public function it_can_build_language_statistics_query()
    {
        $schemeUri = 'http://example.com/scheme/1';

        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression('?language')
            ->selectExpression('(COUNT(?label) as ?count)')
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
            ->whereTriple('?concept', 'skos:prefLabel', '?label')
            ->bind('COALESCE(LANG(?label), "no-lang")', '?language')
            ->groupBy('?language');

        $sparql = $query->toSql();

        // Verify complete structure
        $this->assertStringContainsString('select', strtolower($sparql));
        $this->assertStringContainsString('?language', $sparql);
        $this->assertStringContainsString('COUNT(?label)', $sparql);
        $this->assertStringContainsString('WHERE', $sparql);
        $this->assertStringContainsString("<{$schemeUri}>", $sparql);
        $this->assertStringContainsString('BIND', $sparql);
        $this->assertStringContainsString('group by', strtolower($sparql));
    }

    /** @test */
    public function it_can_build_type_statistics_query()
    {
        $schemeUri = 'http://example.com/scheme/1';

        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression('?type')
            ->selectExpression('(COUNT(DISTINCT ?concept) as ?count)')
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
            ->whereTriple('?concept', 'rdf:type', '?type')
            ->groupBy('?type');

        $sparql = $query->toSql();

        // Verify structure
        $this->assertStringContainsString('?type', $sparql);
        $this->assertStringContainsString('COUNT(DISTINCT ?concept)', $sparql);
        $this->assertStringContainsString('type', $sparql);
        $this->assertStringContainsString('group by', strtolower($sparql));
    }

    /** @test */
    public function it_can_build_queries_with_multiple_binds()
    {
        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression('?language')
            ->selectExpression('?labelCount')
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri('http://example.com/scheme'))
            ->whereTriple('?concept', 'skos:prefLabel', '?label')
            ->bind('LANG(?label)', '?lang')
            ->bind('COALESCE(?lang, "no-lang")', '?language')
            ->bind('COUNT(?label)', '?labelCount')
            ->groupBy('?language');

        $sparql = $query->toSql();

        // Should have multiple BIND expressions
        $bindCount = substr_count($sparql, 'BIND');
        $this->assertEquals(3, $bindCount, 'Should have 3 BIND expressions');
    }

    /** @test */
    public function it_handles_bind_variable_with_and_without_question_mark()
    {
        // Test with question mark
        $query1 = DB::connection('sparql')
            ->query()
            ->graph('')
            ->whereTriple('?s', 'rdf:type', '?type')
            ->bind('LANG(?label)', '?language');

        $sparql1 = $query1->toSql();
        $this->assertStringContainsString('AS ?language', $sparql1);

        // Test without question mark
        $query2 = DB::connection('sparql')
            ->query()
            ->graph('')
            ->whereTriple('?s', 'rdf:type', '?type')
            ->bind('LANG(?label)', 'language');

        $sparql2 = $query2->toSql();
        $this->assertStringContainsString('AS ?language', $sparql2);
    }

    /** @test */
    public function it_can_build_complex_analytical_query()
    {
        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression('?language')
            ->selectExpression('(COUNT(?label) as ?labelCount)')
            ->selectExpression('(COUNT(DISTINCT ?concept) as ?conceptCount)')
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri('http://example.com/scheme'))
            ->whereTriple('?concept', 'skos:prefLabel', '?label')
            ->bind('COALESCE(LANG(?label), "no-lang")', '?language')
            ->groupBy('?language')
            ->having('?labelCount', '>', 10)
            ->orderBy('?labelCount', 'desc');

        $sparql = $query->toSql();

        // Verify all components
        $this->assertStringContainsString('select', strtolower($sparql));
        $this->assertStringContainsString('COUNT(?label)', $sparql);
        $this->assertStringContainsString('COUNT(DISTINCT ?concept)', $sparql);
        $this->assertStringContainsString('BIND', $sparql);
        $this->assertStringContainsString('group by', strtolower($sparql));
        $this->assertStringContainsString('having', strtolower($sparql));
        $this->assertStringContainsString('order by', strtolower($sparql));
    }

    /** @test */
    public function it_preserves_backward_compatibility_for_simple_queries()
    {
        $schemeIri = 'http://example.com/scheme/1';
        $conceptIri = 'http://www.w3.org/2004/02/skos/core#Concept';
        $inSchemeRelationIri = 'http://www.w3.org/2004/02/skos/core#inScheme';

        // Old-style query should still work
        $query = DB::connection('sparql')
            ->graph('')
            ->table($conceptIri)
            ->where($inSchemeRelationIri, Expression::iri($schemeIri));

        $sparql = $query->toSql();

        // Should generate valid SPARQL
        $this->assertStringContainsString('WHERE', $sparql);
        $this->assertStringContainsString("<{$inSchemeRelationIri}>", $sparql);
        $this->assertStringContainsString("<{$schemeIri}>", $sparql);
    }

    /** @test */
    public function it_can_use_db_raw_with_select_expression()
    {
        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression(DB::raw('(COALESCE(LANG(?label), "no-lang") as ?language)'))
            ->selectExpression(DB::raw('COUNT(?label) as ?count'))
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri('http://example.com/scheme'))
            ->whereTriple('?concept', 'skos:prefLabel', '?label')
            ->groupBy('?language');

        $sparql = $query->toSql();

        // Should handle DB::raw() expressions
        $this->assertStringContainsString('COALESCE', $sparql);
        $this->assertStringContainsString('LANG(?label)', $sparql);
        $this->assertStringContainsString('COUNT(?label)', $sparql);
    }

    /** @test */
    public function it_can_mix_select_expressions_with_triple_patterns()
    {
        $query = DB::connection('sparql')
            ->query()
            ->graph('')
            ->selectExpression('?concept')
            ->selectExpression('?label')
            ->selectExpression('(LANG(?label) as ?language)')
            ->whereTriple('?concept', 'skos:inScheme', Expression::iri('http://example.com/scheme'))
            ->whereTriple('?concept', 'skos:prefLabel', '?label');

        $sparql = $query->toSql();

        // Should have all SELECT variables
        $this->assertStringContainsString('?concept', $sparql);
        $this->assertStringContainsString('?label', $sparql);
        $this->assertStringContainsString('LANG(?label)', $sparql);

        // Should have triple patterns in WHERE
        $this->assertStringContainsString('WHERE', $sparql);
        $this->assertStringContainsString('inScheme', $sparql);
        $this->assertStringContainsString('prefLabel', $sparql);
    }
}
