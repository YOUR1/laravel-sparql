<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Query\Builder;
use LinkedData\SPARQL\Query\Grammar;
use LinkedData\SPARQL\Tests\TestCase;

class GrammarTest extends TestCase
{
    protected Grammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grammar = new Grammar;
    }

    public function test_grammar_wraps_uri(): void
    {
        $wrapped = $this->grammar->wrapUri('http://example.org/resource');

        $this->assertEquals('<http://example.org/resource>', $wrapped);
    }

    public function test_grammar_wraps_prefixed_name(): void
    {
        $wrapped = $this->grammar->wrapUri('foaf:Person');

        // wrapUri expands prefixed names to full URIs
        $this->assertEquals('<http://xmlns.com/foaf/0.1/Person>', $wrapped);
    }

    public function test_grammar_compiles_select_query(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person');

        $sql = $this->grammar->compileSelect($query);

        $this->assertStringContainsString('select', strtolower($sql));
    }

    public function test_grammar_compiles_insert_query(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person');

        $values = [
            'id' => 'http://example.org/person1',
            'foaf:name' => 'John Doe',
        ];

        $sql = $this->grammar->compileInsert($query, $values);

        $this->assertStringContainsString('INSERT', $sql);
        // After refactor: we use full URIs instead of prefixes
        $this->assertStringContainsString('http://xmlns.com/foaf/0.1/name', $sql);
    }

    public function test_grammar_compiles_delete_query(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person');

        $sql = $this->grammar->compileDelete($query);

        $this->assertStringContainsString('DELETE', strtoupper($sql));
    }

    public function test_grammar_compiles_where_clause(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')->where('foaf:name', '=', 'John Doe');

        $sql = $this->grammar->compileSelect($query);

        $this->assertStringContainsString('where', strtolower($sql));
    }

    public function test_grammar_compiles_limit_and_offset(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')->limit(10)->offset(5);

        $sql = $this->grammar->compileSelect($query);

        $this->assertStringContainsString('limit 10', strtolower($sql));
        $this->assertStringContainsString('offset 5', strtolower($sql));
    }

    // OPTIONAL graph pattern tests
    public function test_optional_graph_pattern_basic(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->where('foaf:name', '=', 'John')
            ->optional(function ($q) {
                $q->where('foaf:email', '=', 'john@example.com');
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('OPTIONAL', $sql);
        $this->assertStringContainsString('foaf:email', $sql);
    }

    public function test_optional_graph_pattern_with_multiple_conditions(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->where('foaf:name', '=', 'John')
            ->optional(function ($q) {
                $q->where('foaf:email', '=', 'john@example.com')
                    ->where('foaf:phone', '=', '555-1234');
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('OPTIONAL', $sql);
        $this->assertStringContainsString('foaf:email', $sql);
        $this->assertStringContainsString('foaf:phone', $sql);
    }

    // CONSTRUCT query tests
    public function test_construct_query_with_raw_template(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->construct('?s foaf:name ?name')
            ->where('foaf:name', '=', 'John');

        $sql = $query->toSparql();

        $this->assertStringContainsString('construct', strtolower($sql));
        $this->assertStringContainsString('?s foaf:name ?name', $sql);
        $this->assertStringContainsString('where', strtolower($sql));
    }

    public function test_construct_query_with_array_template(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->construct([
                ['?s', 'foaf:name', '?name'],
                ['?s', 'foaf:email', '?email'],
            ])
            ->where('foaf:name', '=', 'John');

        $sql = $query->toSparql();

        $this->assertStringContainsString('construct', strtolower($sql));
        $this->assertStringContainsString('?s foaf:name ?name', $sql);
        $this->assertStringContainsString('?s foaf:email ?email', $sql);
    }

    public function test_construct_query_with_limit_and_offset(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->construct('?s foaf:name ?name')
            ->where('foaf:name', '=', 'John')
            ->limit(10)
            ->offset(5);

        $sql = $query->toSparql();

        $this->assertStringContainsString('construct', strtolower($sql));
        $this->assertStringContainsString('limit 10', strtolower($sql));
        $this->assertStringContainsString('offset 5', strtolower($sql));
    }

    // ASK query tests
    public function test_ask_query_basic(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->ask()
            ->where('foaf:name', '=', 'John');

        $sql = $query->toSparql();

        $this->assertStringContainsString('ask', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringNotContainsString('select', strtolower($sql));
    }

    public function test_ask_query_with_multiple_conditions(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->ask()
            ->where('foaf:name', '=', 'John')
            ->where('foaf:email', '=', 'john@example.com');

        $sql = $query->toSparql();

        $this->assertStringContainsString('ask', strtolower($sql));
        $this->assertStringContainsString('foaf:name', $sql);
        $this->assertStringContainsString('foaf:email', $sql);
    }

    public function test_ask_query_with_optional(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->ask()
            ->where('foaf:name', '=', 'John')
            ->optional(function ($q) {
                $q->where('foaf:email', '=', 'john@example.com');
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('ask', strtolower($sql));
        $this->assertStringContainsString('OPTIONAL', $sql);
    }

    // DESCRIBE query tests
    public function test_describe_query_without_resources(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->describe();

        $sql = $query->toSparql();

        $this->assertStringContainsString('describe', strtolower($sql));
        $this->assertStringNotContainsString('select', strtolower($sql));
    }

    public function test_describe_query_with_single_resource(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->describe('http://example.org/person/1');

        $sql = $query->toSparql();

        $this->assertStringContainsString('describe', strtolower($sql));
        $this->assertStringContainsString('http://example.org/person/1', $sql);
    }

    public function test_describe_query_with_multiple_resources(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->describe([
            'http://example.org/person/1',
            'http://example.org/person/2',
        ]);

        $sql = $query->toSparql();

        $this->assertStringContainsString('describe', strtolower($sql));
        $this->assertStringContainsString('http://example.org/person/1', $sql);
        $this->assertStringContainsString('http://example.org/person/2', $sql);
    }

    public function test_describe_query_with_where_clause(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->describe()
            ->where('foaf:name', '=', 'John');

        $sql = $query->toSparql();

        $this->assertStringContainsString('describe', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringContainsString('foaf:name', $sql);
    }

    // Combined features tests
    public function test_construct_with_optional(): void
    {
        $connection = $this->app['db']->connection('sparql');
        $query = new Builder($connection, $this->grammar, $connection->getPostProcessor());
        $query->from('foaf:Person')
            ->construct('?s foaf:name ?name')
            ->where('foaf:name', '=', 'John')
            ->optional(function ($q) {
                $q->where('foaf:email', '=', 'john@example.com');
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('construct', strtolower($sql));
        $this->assertStringContainsString('OPTIONAL', $sql);
        $this->assertStringContainsString('foaf:email', $sql);
    }
}
