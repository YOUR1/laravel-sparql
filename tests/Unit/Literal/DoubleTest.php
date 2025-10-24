<?php

namespace LinkedData\SPARQL\Tests\Unit\Literal;

use LinkedData\SPARQL\Query\Literal\Double;
use LinkedData\SPARQL\Tests\UnitTestCase;

class DoubleTest extends UnitTestCase
{
    public function test_double_can_be_instantiated_with_numeric_value(): void
    {
        $double = new Double(42.5);

        $this->assertInstanceOf(Double::class, $double);
    }

    public function test_double_get_value_returns_float(): void
    {
        $double = new Double(42.5);

        $this->assertIsFloat($double->getValue());
        $this->assertEquals(42.5, $double->getValue());
    }

    public function test_double_casts_string_to_float(): void
    {
        $double = new Double('99.99');

        $this->assertIsFloat($double->getValue());
        $this->assertEquals(99.99, $double->getValue());
    }

    public function test_double_casts_integer_to_float(): void
    {
        $double = new Double(100);

        $this->assertIsFloat($double->getValue());
        $this->assertEquals(100.0, $double->getValue());
    }

    public function test_double_ignores_language_parameter(): void
    {
        $double = new Double(42.5, 'en');

        $this->assertEquals(42.5, $double->getValue());
    }

    public function test_double_accepts_datatype_parameter(): void
    {
        $double = new Double(42.5, null, 'http://www.w3.org/2001/XMLSchema#double');

        $this->assertEquals(42.5, $double->getValue());
        $this->assertEquals('xsd:double', $double->getDatatype());
    }

    public function test_double_handles_negative_values(): void
    {
        $double = new Double(-123.45);

        $this->assertEquals(-123.45, $double->getValue());
    }

    public function test_double_handles_zero(): void
    {
        $double = new Double(0);

        $this->assertIsFloat($double->getValue());
        $this->assertEquals(0.0, $double->getValue());
    }

    public function test_double_handles_scientific_notation(): void
    {
        $double = new Double(1.23e10);

        $this->assertIsFloat($double->getValue());
        $this->assertEquals(1.23e10, $double->getValue());
    }
}
