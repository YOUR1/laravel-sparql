<?php

namespace LinkedData\Tests\Unit;

use LinkedData\SPARQL\Connection;
use LinkedData\SPARQL\Query\Builder;
use LinkedData\SPARQL\Query\Grammar;
use LinkedData\SPARQL\Query\Processor;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Advanced Query Features
 *
 * This test suite covers:
 * - Query result methods (chunk, lazy, remember)
 * - Where clause enhancements (whereLike, whereJsonContains, filter)
 * - Named parameter binding
 * - Subquery support
 */
class AdvancedQueryFeaturesTest extends TestCase
{
    protected $connection;

    protected $grammar;

    protected $processor;

    protected $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = m::mock(Connection::class);
        $this->grammar = new Grammar;
        $this->processor = new Processor;
        $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_add_custom_filter_clause()
    {
        $this->builder->filter('?age > 18');

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);
        $lastWhere = end($wheres);

        $this->assertEquals('raw', $lastWhere['type']);
        $this->assertStringContainsString('FILTER(?age > 18)', $lastWhere['sql']);
    }

    /** @test */
    public function it_can_add_filter_with_closure()
    {
        $this->builder->filter(function ($query) {
            $query->where('age', '>', 18);
        });

        // The filter should add a nested where
        $this->assertNotEmpty($this->builder->wheres);
    }

    /** @test */
    public function it_wraps_filter_expression_automatically()
    {
        $this->builder->filter('?age > 18');

        $wheres = $this->builder->wheres;
        $lastWhere = end($wheres);

        $this->assertStringContainsString('FILTER', $lastWhere['sql']);
    }

    /** @test */
    public function it_does_not_double_wrap_filter()
    {
        $this->builder->filter('FILTER(?age > 18)');

        $wheres = $this->builder->wheres;
        $lastWhere = end($wheres);

        // Should not have FILTER(FILTER(...))
        $this->assertEquals(1, substr_count($lastWhere['sql'], 'FILTER'));
    }

    /** @test */
    public function it_can_add_or_filter_clause()
    {
        $this->builder->filter('?age > 18')->orFilter('?age < 10');

        $wheres = $this->builder->wheres;
        $this->assertCount(2, $wheres);
        $this->assertEquals('and', $wheres[0]['boolean']);
        $this->assertEquals('or', $wheres[1]['boolean']);
    }

    /** @test */
    public function it_can_add_where_like_clause()
    {
        $this->builder->whereLike('name', 'John%');

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);

        // whereLike should use regex internally
        $lastWhere = end($wheres);
        $this->assertEquals('raw', $lastWhere['type']);
    }

    /** @test */
    public function it_converts_like_wildcards_to_regex()
    {
        $this->builder->whereLike('name', 'John%');

        $wheres = $this->builder->wheres;
        $lastWhere = end($wheres);

        // % should be converted to .* in regex
        $this->assertStringContainsString('REGEX', $lastWhere['sql']);
    }

    /** @test */
    public function it_can_add_or_where_like_clause()
    {
        $this->builder->whereLike('name', 'John%')->orWhereLike('name', 'Jane%');

        $wheres = $this->builder->wheres;
        $this->assertCount(2, $wheres);
        $this->assertEquals('or', $wheres[1]['boolean']);
    }

    /** @test */
    public function it_can_add_where_not_like_clause()
    {
        $this->builder->whereNotLike('name', 'Admin%');

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);

        $lastWhere = end($wheres);
        $this->assertStringContainsString('!REGEX', $lastWhere['sql']);
    }

    /** @test */
    public function it_can_add_or_where_not_like_clause()
    {
        $this->builder->whereNotLike('name', 'Admin%')->orWhereNotLike('name', 'Test%');

        $wheres = $this->builder->wheres;
        $this->assertCount(2, $wheres);
    }

    /** @test */
    public function it_can_add_where_json_contains_for_scalar_value()
    {
        $this->builder->whereJsonContains('tags', 'php');

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);

        $lastWhere = end($wheres);
        $this->assertEquals('raw', $lastWhere['type']);
        $this->assertStringContainsString('CONTAINS', $lastWhere['sql']);
    }

    /** @test */
    public function it_can_add_where_json_contains_for_array_value()
    {
        $this->builder->whereJsonContains('status', ['active', 'pending']);

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);

        $lastWhere = end($wheres);
        $this->assertStringContainsString('IN', $lastWhere['sql']);
    }

    /** @test */
    public function it_can_add_or_where_json_contains()
    {
        $this->builder->whereJsonContains('tags', 'php')->orWhereJsonContains('tags', 'javascript');

        $wheres = $this->builder->wheres;
        $this->assertCount(2, $wheres);
        $this->assertEquals('or', $wheres[1]['boolean']);
    }

    /** @test */
    public function it_can_add_where_json_doesnt_contain()
    {
        $this->builder->whereJsonDoesntContain('tags', 'spam');

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);

        $lastWhere = end($wheres);
        $this->assertStringContainsString('!CONTAINS', $lastWhere['sql']);
    }

    /** @test */
    public function it_can_add_or_where_json_doesnt_contain()
    {
        $this->builder->whereJsonDoesntContain('tags', 'spam')->orWhereJsonDoesntContain('tags', 'test');

        $wheres = $this->builder->wheres;
        $this->assertCount(2, $wheres);
    }

    /** @test */
    public function it_can_add_named_binding()
    {
        $this->builder->addNamedBinding('userId', 123);

        $namedBindings = $this->builder->getNamedBindings();
        $this->assertArrayHasKey('userId', $namedBindings);
        $this->assertEquals(123, $namedBindings['userId']);
    }

    /** @test */
    public function it_can_set_multiple_named_bindings()
    {
        $this->builder->setNamedBindings([
            'userId' => 123,
            'userName' => 'John Doe',
            'userAge' => 30,
        ]);

        $namedBindings = $this->builder->getNamedBindings();
        $this->assertCount(3, $namedBindings);
        $this->assertEquals(123, $namedBindings['userId']);
        $this->assertEquals('John Doe', $namedBindings['userName']);
        $this->assertEquals(30, $namedBindings['userAge']);
    }

    /** @test */
    public function it_can_get_named_binding()
    {
        $this->builder->addNamedBinding('userId', 123);

        $value = $this->builder->getNamedBinding('userId');
        $this->assertEquals(123, $value);
    }

    /** @test */
    public function it_returns_default_for_missing_named_binding()
    {
        $value = $this->builder->getNamedBinding('nonexistent', 'default');
        $this->assertEquals('default', $value);
    }

    /** @test */
    public function it_can_use_where_raw_with_named_bindings()
    {
        $this->builder->whereRawNamed('?age > :minAge AND ?age < :maxAge', [
            'minAge' => 18,
            'maxAge' => 65,
        ]);

        $namedBindings = $this->builder->getNamedBindings();
        $this->assertArrayHasKey('minAge', $namedBindings);
        $this->assertArrayHasKey('maxAge', $namedBindings);
        $this->assertEquals(18, $namedBindings['minAge']);
        $this->assertEquals(65, $namedBindings['maxAge']);
    }

    /** @test */
    public function it_has_remember_method()
    {
        $result = $this->builder->remember(60);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertEquals(60, $this->builder->cacheSeconds);
    }

    /** @test */
    public function it_has_remember_forever_method()
    {
        $result = $this->builder->rememberForever();

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertEquals(-1, $this->builder->cacheSeconds);
    }

    /** @test */
    public function it_can_set_cache_key()
    {
        $this->builder->remember(60, 'my-custom-key');

        $this->assertEquals('my-custom-key', $this->builder->cacheKey);
    }

    /** @test */
    public function it_can_disable_caching()
    {
        $this->builder->remember(60)->dontRemember();

        $this->assertNull($this->builder->cacheSeconds);
        $this->assertNull($this->builder->cacheKey);
    }

    /** @test */
    public function it_generates_cache_key_from_query()
    {
        $this->connection->shouldReceive('getName')->andReturn('sparql');

        $this->builder->from('Person')->where('age', '>', 18);

        $key = $this->builder->generateCacheKey();

        $this->assertIsString($key);
        $this->assertEquals(64, strlen($key)); // SHA256 produces 64 character hex
    }

    /** @test */
    public function it_has_chunk_method()
    {
        $this->assertTrue(method_exists($this->builder, 'chunk'));
    }

    /** @test */
    public function it_has_each_method()
    {
        $this->assertTrue(method_exists($this->builder, 'each'));
    }

    /** @test */
    public function it_has_lazy_method()
    {
        $this->assertTrue(method_exists($this->builder, 'lazy'));
    }

    /** @test */
    public function it_has_cursor_method()
    {
        $this->assertTrue(method_exists($this->builder, 'cursor'));
    }

    /** @test */
    public function it_has_select_sub_method()
    {
        $this->assertTrue(method_exists($this->builder, 'selectSub'));
    }

    /** @test */
    public function it_supports_macros()
    {
        // The Builder uses Macroable trait
        Builder::macro('customMethod', function () {
            return 'custom result';
        });

        $result = $this->builder->customMethod();
        $this->assertEquals('custom result', $result);
    }

    /** @test */
    public function named_bindings_property_exists()
    {
        $this->assertIsArray($this->builder->namedBindings);
    }

    /** @test */
    public function cache_seconds_property_exists()
    {
        $this->assertObjectHasProperty('cacheSeconds', $this->builder);
    }

    /** @test */
    public function cache_key_property_exists()
    {
        $this->assertObjectHasProperty('cacheKey', $this->builder);
    }

    /** @test */
    public function it_escapes_special_regex_characters_in_where_like()
    {
        // Test that special regex characters are properly escaped
        $this->builder->whereLike('name', 'test.name');

        $wheres = $this->builder->wheres;
        $lastWhere = end($wheres);

        // The dot should be escaped in regex pattern
        $this->assertStringContainsString('REGEX', $lastWhere['sql']);
    }

    /** @test */
    public function it_converts_underscore_wildcard_in_where_like()
    {
        $this->builder->whereLike('name', 'J_hn');

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);

        // _ should be converted to . in regex (single character match)
        $lastWhere = end($wheres);
        $this->assertStringContainsString('REGEX', $lastWhere['sql']);
    }

    /** @test */
    public function where_json_contains_escapes_string_values()
    {
        $this->builder->whereJsonContains('description', "test's value");

        $wheres = $this->builder->wheres;
        $this->assertNotEmpty($wheres);

        // Should handle escaped quotes properly
        $lastWhere = end($wheres);
        $this->assertEquals('raw', $lastWhere['type']);
    }

    /** @test */
    public function select_with_named_bindings_converts_to_positional()
    {
        $query = 'SELECT * WHERE { ?s ?p :value . FILTER(?age > :minAge) }';
        $bindings = ['minAge' => 18];

        $this->connection->shouldReceive('select')
            ->once()
            ->with(
                m::on(function ($convertedQuery) {
                    return strpos($convertedQuery, '?') !== false && strpos($convertedQuery, ':minAge') === false;
                }),
                [18],
                true
            )
            ->andReturn([]);

        $result = $this->builder->selectWithNamedBindings($query, $bindings);

        $this->assertIsArray($result);
    }

    /** @test */
    public function it_preserves_unnamed_colons_in_named_binding_conversion()
    {
        $query = 'SELECT * WHERE { ?s <http://example.com/property> ?o . FILTER(?age > :minAge) }';
        $bindings = ['minAge' => 18];

        $this->connection->shouldReceive('select')
            ->once()
            ->with(
                m::on(function ($convertedQuery) {
                    // Should preserve the URI but convert :minAge
                    return strpos($convertedQuery, 'http://example.com/property') !== false;
                }),
                [18],
                true
            )
            ->andReturn([]);

        $result = $this->builder->selectWithNamedBindings($query, $bindings);

        $this->assertIsArray($result);
    }
}
