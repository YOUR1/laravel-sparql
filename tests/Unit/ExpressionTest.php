<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Query\Expression;
use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase
{
    public function test_expression_creates_raw_value(): void
    {
        $expr = Expression::raw('test_value');

        $this->assertEquals('test_value', $expr->getValue());
    }

    public function test_expression_creates_iri(): void
    {
        $expr = Expression::iri('http://example.org/resource');

        $this->assertEquals('<http://example.org/resource>', $expr->getValue());
    }

    public function test_expression_creates_iri_for_prefixed_name(): void
    {
        $expr = Expression::iri('foaf:Person');

        // IRI type expands prefixed names to full URIs
        $this->assertEquals('<http://xmlns.com/foaf/0.1/Person>', $expr->getValue());
    }

    public function test_expression_creates_class(): void
    {
        $expr = Expression::cls('foaf:Person');

        $this->assertEquals('foaf:Person', $expr->getValue());
    }

    public function test_expression_wraps_array_values(): void
    {
        $values = ['http://example.org/resource1', 'http://example.org/resource2'];
        $expr = Expression::iri($values);

        $this->assertIsArray($expr);
        $this->assertCount(2, $expr);
    }

    public function test_expression_detects_param_type(): void
    {
        $expr = Expression::par('?param');

        $this->assertTrue(Expression::is($expr, 'param'));
    }

    public function test_expression_same_compares_expressions(): void
    {
        $expr1 = Expression::raw('?param');
        $expr2 = Expression::raw('?param');

        $this->assertTrue(Expression::same($expr1, $expr2));
    }
}
