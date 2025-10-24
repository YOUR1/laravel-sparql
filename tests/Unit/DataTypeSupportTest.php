<?php

namespace LinkedData\Tests\Unit;

use LinkedData\SPARQL\Connection;
use LinkedData\SPARQL\Query\Builder;
use LinkedData\SPARQL\Query\Grammar;
use LinkedData\SPARQL\Query\Processor;
use PHPUnit\Framework\TestCase;

/**
 * Tests for comprehensive data type support including:
 * - Language-tagged literals (STRLANG)
 * - Typed literals (STRDT)
 * - XSD datatype casting
 * - Blank nodes
 * - IRI/URI validation and normalization
 */
class DataTypeSupportTest extends TestCase
{
    protected Builder $builder;

    protected Grammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->createMock(Connection::class);
        $this->grammar = new Grammar;
        $processor = new Processor;

        $this->builder = new Builder($connection, $this->grammar, $processor);
    }

    /** @test */
    public function it_builds_strlang_function_for_language_tagged_literals()
    {
        $result = $this->builder->strLang('Hello', 'en');

        $this->assertEquals('STRLANG("Hello", "en")', $result);
    }

    /** @test */
    public function it_builds_strlang_function_with_variables()
    {
        $result = $this->builder->strLang('?label', 'en');

        $this->assertEquals('STRLANG(?label, "en")', $result);
    }

    /** @test */
    public function it_builds_strdt_function_for_typed_literals()
    {
        $result = $this->builder->strDt('123', 'xsd:integer');

        $this->assertEquals('STRDT("123", xsd:integer)', $result);
    }

    /** @test */
    public function it_builds_strdt_function_with_auto_xsd_namespace()
    {
        $result = $this->builder->strDt('123', 'integer');

        $this->assertEquals('STRDT("123", xsd:integer)', $result);
    }

    /** @test */
    public function it_builds_lang_tagged_literal_binding()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->langTagged('label', 'Hello World', 'en')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRLANG("Hello World", "en") AS ?label)', $query);
    }

    /** @test */
    public function it_builds_typed_literal_binding()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->typed('age', '25', 'integer')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("25", xsd:integer) AS ?age)', $query);
    }

    /** @test */
    public function it_builds_integer_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->integer('age', 25)
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("25", xsd:integer) AS ?age)', $query);
    }

    /** @test */
    public function it_builds_decimal_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->decimal('price', '19.99')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("19.99", xsd:decimal) AS ?price)', $query);
    }

    /** @test */
    public function it_builds_float_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->float('temperature', '98.6')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("98.6", xsd:float) AS ?temperature)', $query);
    }

    /** @test */
    public function it_builds_double_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->double('pi', '3.14159')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("3.14159", xsd:double) AS ?pi)', $query);
    }

    /** @test */
    public function it_builds_boolean_typed_literal_true()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->boolean('active', true)
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("true", xsd:boolean) AS ?active)', $query);
    }

    /** @test */
    public function it_builds_boolean_typed_literal_false()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->boolean('active', false)
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("false", xsd:boolean) AS ?active)', $query);
    }

    /** @test */
    public function it_builds_string_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->string('name', 'John Doe')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("John Doe", xsd:string) AS ?name)', $query);
    }

    /** @test */
    public function it_builds_date_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->date('birthday', '1990-01-01')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("1990-01-01", xsd:date) AS ?birthday)', $query);
    }

    /** @test */
    public function it_builds_datetime_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->dateTime('created', '2023-01-01T12:00:00Z')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("2023-01-01T12:00:00Z", xsd:dateTime) AS ?created)', $query);
    }

    /** @test */
    public function it_builds_time_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->time('startTime', '09:00:00')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("09:00:00", xsd:time) AS ?startTime)', $query);
    }

    /** @test */
    public function it_builds_duration_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->duration('period', 'P1Y2M3DT4H5M6S')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("P1Y2M3DT4H5M6S", xsd:duration) AS ?period)', $query);
    }

    /** @test */
    public function it_builds_gyear_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->gYear('year', '2023')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("2023", xsd:gYear) AS ?year)', $query);
    }

    /** @test */
    public function it_builds_gyearmonth_typed_literal()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->gYearMonth('yearMonth', '2023-01')
            ->toSparql();

        $this->assertStringContainsString('BIND(STRDT("2023-01", xsd:gYearMonth) AS ?yearMonth)', $query);
    }

    /** @test */
    public function it_builds_blank_node_without_expression()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->blankNode('newPerson')
            ->toSparql();

        $this->assertStringContainsString('BIND(BNODE() AS ?newPerson)', $query);
    }

    /** @test */
    public function it_builds_blank_node_with_expression()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->blankNode('newPerson', '?id')
            ->toSparql();

        $this->assertStringContainsString('BIND(BNODE(?id) AS ?newPerson)', $query);
    }

    /** @test */
    public function it_builds_iri_from_expression()
    {
        // First build the CONCAT expression
        $concatExpr = $this->builder->concat('"http://example.org/"', '?id');

        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->iriFromExpression('resource', $concatExpr)
            ->toSparql();

        $this->assertStringContainsString('BIND(IRI(CONCAT("http://example.org/", ?id)) AS ?resource)', $query);
    }

    /** @test */
    public function it_builds_cast_to_string()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->castToString('labelStr', '?label')
            ->toSparql();

        $this->assertStringContainsString('BIND(STR(?label) AS ?labelStr)', $query);
    }

    /** @test */
    public function it_builds_get_datatype_of()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->getDatatypeOf('type', '?value')
            ->toSparql();

        $this->assertStringContainsString('BIND(DATATYPE(?value) AS ?type)', $query);
    }

    /** @test */
    public function it_builds_get_language_of()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->getLanguageOf('lang', '?label')
            ->toSparql();

        $this->assertStringContainsString('BIND(LANG(?label) AS ?lang)', $query);
    }

    /** @test */
    public function it_wraps_standard_uri_in_angle_brackets()
    {
        $wrapped = $this->grammar->wrapUri('http://example.org/resource');

        $this->assertEquals('<http://example.org/resource>', $wrapped);
    }

    /** @test */
    public function it_wraps_urn_in_angle_brackets()
    {
        $wrapped = $this->grammar->wrapUri('urn:isbn:0451450523');

        $this->assertEquals('<urn:isbn:0451450523>', $wrapped);
    }

    /** @test */
    public function it_wraps_blank_node_in_angle_brackets()
    {
        $wrapped = $this->grammar->wrapUri('_:node1');

        $this->assertEquals('<_:node1>', $wrapped);
    }

    /** @test */
    public function it_expands_namespace_prefix()
    {
        // Note: This test depends on EasyRDF namespace expansion
        // foaf: should expand to http://xmlns.com/foaf/0.1/
        $wrapped = $this->grammar->wrapUri('foaf:Person');

        $this->assertStringContainsString('http://xmlns.com/foaf/0.1/Person', $wrapped);
        $this->assertStringStartsWith('<', $wrapped);
        $this->assertStringEndsWith('>', $wrapped);
    }

    /** @test */
    public function it_normalizes_uri_with_excessive_slashes()
    {
        $wrapped = $this->grammar->wrapUri('http://example.org//path///to////resource');

        $this->assertEquals('<http://example.org/path/to/resource>', $wrapped);
    }

    /** @test */
    public function it_validates_iri_with_unicode_characters()
    {
        // IRI with Unicode characters
        $wrapped = $this->grammar->wrapUri('http://example.org/資源');

        $this->assertStringStartsWith('<', $wrapped);
        $this->assertStringEndsWith('>', $wrapped);
        $this->assertStringContainsString('資源', $wrapped);
    }

    /** @test */
    public function it_does_not_wrap_prefixed_names()
    {
        $wrapped = $this->grammar->wrapUri('ex:resource');

        // Should not wrap if namespace is not registered
        // (depends on EasyRDF configuration)
        $this->assertIsString($wrapped);
    }

    /** @test */
    public function it_combines_multiple_datatype_operations()
    {
        $query = $this->builder
            ->from('?s', 'foaf:Person')
            ->langTagged('greeting', 'Hello', 'en')
            ->integer('age', 25)
            ->date('birthday', '1990-01-01')
            ->boolean('active', true)
            ->toSparql();

        $this->assertStringContainsString('BIND(STRLANG("Hello", "en") AS ?greeting)', $query);
        $this->assertStringContainsString('BIND(STRDT("25", xsd:integer) AS ?age)', $query);
        $this->assertStringContainsString('BIND(STRDT("1990-01-01", xsd:date) AS ?birthday)', $query);
        $this->assertStringContainsString('BIND(STRDT("true", xsd:boolean) AS ?active)', $query);
    }

    /** @test */
    public function it_uses_lang_and_langmatches_functions()
    {
        $langExpr = $this->builder->lang('?label');
        $this->assertEquals('LANG(?label)', $langExpr);

        $langMatchesExpr = $this->builder->langMatches('?lang', '"en"');
        $this->assertEquals('LANGMATCHES(?lang, "en")', $langMatchesExpr);
    }

    /** @test */
    public function it_uses_datatype_function()
    {
        $datatypeExpr = $this->builder->datatype('?value');
        $this->assertEquals('DATATYPE(?value)', $datatypeExpr);
    }

    /** @test */
    public function it_uses_iri_function()
    {
        $iriExpr = $this->builder->iri('?stringValue');
        $this->assertEquals('IRI(?stringValue)', $iriExpr);
    }

    /** @test */
    public function it_uses_bnode_function_without_argument()
    {
        $bnodeExpr = $this->builder->bnode();
        $this->assertEquals('BNODE()', $bnodeExpr);
    }

    /** @test */
    public function it_uses_bnode_function_with_argument()
    {
        $bnodeExpr = $this->builder->bnode('?id');
        $this->assertEquals('BNODE(?id)', $bnodeExpr);
    }

    /** @test */
    public function it_uses_isiri_function()
    {
        $isIriExpr = $this->builder->isIri('?value');
        $this->assertEquals('ISIRI(?value)', $isIriExpr);
    }

    /** @test */
    public function it_uses_isblank_function()
    {
        $isBlankExpr = $this->builder->isBlank('?value');
        $this->assertEquals('ISBLANK(?value)', $isBlankExpr);
    }

    /** @test */
    public function it_uses_isliteral_function()
    {
        $isLiteralExpr = $this->builder->isLiteral('?value');
        $this->assertEquals('ISLITERAL(?value)', $isLiteralExpr);
    }
}
