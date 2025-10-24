<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Query\Builder;
use LinkedData\SPARQL\Query\Grammar;
use LinkedData\SPARQL\Tests\TestCase;

class Sparql11FeaturesTest extends TestCase
{
    protected Grammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grammar = new Grammar;
    }

    protected function getBuilder(): Builder
    {
        $connection = $this->app['db']->connection('sparql');

        return new Builder($connection, $this->grammar, $connection->getPostProcessor());
    }

    // ========================================
    // BIND Expression Tests
    // ========================================

    public function test_bind_expression_basic(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->where('foaf:firstName', '=', 'John')
            ->bind('?firstName', 'name');

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND', $sql);
        $this->assertStringContainsString('?firstName AS ?name', $sql);
    }

    public function test_bind_expression_with_function(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->bind('CONCAT(?firstName, " ", ?lastName)', 'fullName');

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND', $sql);
        $this->assertStringContainsString('CONCAT(?firstName, " ", ?lastName) AS ?fullName', $sql);
    }

    public function test_bind_expression_adds_question_mark_to_variable(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->bind('?value + 10', 'result');

        $sql = $query->toSparql();

        $this->assertStringContainsString('?result', $sql);
    }

    public function test_multiple_bind_expressions(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->bind('?x + ?y', 'sum')
            ->bind('?x * ?y', 'product');

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND(?x + ?y AS ?sum)', $sql);
        $this->assertStringContainsString('BIND(?x * ?y AS ?product)', $sql);
    }

    // ========================================
    // VALUES Data Block Tests
    // ========================================

    public function test_values_with_single_variable(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->values('name', ['Alice', 'Bob', 'Charlie']);

        $sql = $query->toSparql();

        $this->assertStringContainsString('VALUES', $sql);
        $this->assertStringContainsString('(?name)', $sql);
        $this->assertStringContainsString('"Alice"', $sql);
        $this->assertStringContainsString('"Bob"', $sql);
        $this->assertStringContainsString('"Charlie"', $sql);
    }

    public function test_values_with_multiple_variables(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->values(['name', 'age'], [
                ['Alice', 30],
                ['Bob', 25],
                ['Charlie', 35],
            ]);

        $sql = $query->toSparql();

        $this->assertStringContainsString('VALUES', $sql);
        $this->assertStringContainsString('(?name ?age)', $sql);
        $this->assertStringContainsString('"Alice"', $sql);
        $this->assertStringContainsString('30', $sql);
    }

    public function test_values_adds_question_mark_to_variables(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->values('name', ['Alice']);

        $sql = $query->toSparql();

        $this->assertStringContainsString('?name', $sql);
    }

    // ========================================
    // MINUS Graph Pattern Tests
    // ========================================

    public function test_minus_graph_pattern_basic(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->where('foaf:name', '=', 'John')
            ->minus(function ($q) {
                $q->where('foaf:email', '=', 'john@spam.com');
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('MINUS', $sql);
        $this->assertStringContainsString('foaf:email', $sql);
    }

    public function test_minus_graph_pattern_with_multiple_conditions(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->where('foaf:name', '=', 'John')
            ->minus(function ($q) {
                $q->where('foaf:email', '=', 'john@spam.com')
                    ->where('foaf:age', '<', 18);
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('MINUS', $sql);
        $this->assertStringContainsString('foaf:email', $sql);
        $this->assertStringContainsString('foaf:age', $sql);
    }

    public function test_multiple_minus_patterns(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->minus(function ($q) {
                $q->where('foaf:status', '=', 'banned');
            })
            ->minus(function ($q) {
                $q->where('foaf:status', '=', 'deleted');
            });

        $sql = $query->toSparql();

        // Count occurrences of MINUS
        $minusCount = substr_count($sql, 'MINUS');
        $this->assertEquals(2, $minusCount);
    }

    // ========================================
    // SERVICE (Federated Query) Tests
    // ========================================

    public function test_service_basic(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->where('foaf:name', '=', 'Alice')
            ->service('http://dbpedia.org/sparql', function ($q) {
                $q->where('dbo:birthPlace', '=', 'dbr:London');
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('SERVICE', $sql);
        $this->assertStringContainsString('<http://dbpedia.org/sparql>', $sql);
        $this->assertStringContainsString('dbo:birthPlace', $sql);
    }

    public function test_service_with_multiple_conditions(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->service('http://example.org/sparql', function ($q) {
                $q->where('foaf:knows', '=', '?friend')
                    ->where('foaf:age', '>', 18);
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('SERVICE', $sql);
        $this->assertStringContainsString('foaf:knows', $sql);
        $this->assertStringContainsString('foaf:age', $sql);
    }

    // ========================================
    // Property Path Tests
    // ========================================

    public function test_property_path_basic(): void
    {
        $query = $this->getBuilder();
        $query->propertyPath('foaf:knows/foaf:name', '?friendName');

        $sql = $query->toSparql();

        $this->assertStringContainsString('foaf:knows/foaf:name', $sql);
    }

    public function test_property_path_with_star_operator(): void
    {
        $query = $this->getBuilder();
        $query->propertyPath('foaf:knows*', '?person');

        $sql = $query->toSparql();

        $this->assertStringContainsString('foaf:knows*', $sql);
    }

    public function test_property_path_with_plus_operator(): void
    {
        $query = $this->getBuilder();
        $query->propertyPath('foaf:knows+', '?person');

        $sql = $query->toSparql();

        $this->assertStringContainsString('foaf:knows+', $sql);
    }

    public function test_property_path_with_inverse_operator(): void
    {
        $query = $this->getBuilder();
        $query->propertyPath('^foaf:knows', '?knownBy');

        $sql = $query->toSparql();

        $this->assertStringContainsString('^foaf:knows', $sql);
    }

    public function test_property_path_with_alternative_operator(): void
    {
        $query = $this->getBuilder();
        $query->propertyPath('foaf:name|rdfs:label', '?nameOrLabel');

        $sql = $query->toSparql();

        $this->assertStringContainsString('foaf:name|rdfs:label', $sql);
    }

    // ========================================
    // Aggregate Function Tests
    // ========================================

    public function test_group_concat_aggregate(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->select(['?person', '?name'])
            ->groupBy('?person');

        // Test that groupConcat method exists and can be called
        $this->assertTrue(method_exists($query, 'groupConcat'));
    }

    public function test_group_concat_with_separator(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person');

        // Use reflection to test the protected compileAggregate method
        $reflection = new \ReflectionClass($this->grammar);
        $method = $reflection->getMethod('compileAggregate');
        $method->setAccessible(true);

        $aggregate = [
            'function' => 'group_concat_separator_,',
            'columns' => ['?name'],
        ];

        $sql = $method->invoke($this->grammar, $query, $aggregate);

        $this->assertStringContainsString('group_concat', $sql);
        $this->assertStringContainsString('separator=","', $sql);
    }

    public function test_sample_aggregate(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person');

        // Test that sample method exists and can be called
        $this->assertTrue(method_exists($query, 'sample'));
    }

    // ========================================
    // SPARQL Function Tests - String Functions
    // ========================================

    public function test_str_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->str('?uri');

        $this->assertEquals('STR(?uri)', $result);
    }

    public function test_strlen_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->strlen('?name');

        $this->assertEquals('STRLEN(?name)', $result);
    }

    public function test_substr_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->substr('?name', 1, 5);

        $this->assertEquals('SUBSTR(?name, 1, 5)', $result);
    }

    public function test_substr_function_without_length(): void
    {
        $query = $this->getBuilder();
        $result = $query->substr('?name', 5);

        $this->assertEquals('SUBSTR(?name, 5)', $result);
    }

    public function test_ucase_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->ucase('?name');

        $this->assertEquals('UCASE(?name)', $result);
    }

    public function test_lcase_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->lcase('?name');

        $this->assertEquals('LCASE(?name)', $result);
    }

    public function test_concat_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->concat('?firstName', '" "', '?lastName');

        $this->assertEquals('CONCAT(?firstName, " ", ?lastName)', $result);
    }

    public function test_strstarts_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->strStarts('?name', '"John"');

        $this->assertEquals('STRSTARTS(?name, "John")', $result);
    }

    public function test_strends_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->strEnds('?email', '"@example.com"');

        $this->assertEquals('STRENDS(?email, "@example.com")', $result);
    }

    public function test_contains_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->contains('?text', '"important"');

        $this->assertEquals('CONTAINS(?text, "important")', $result);
    }

    public function test_strbefore_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->strBefore('?email', '"@"');

        $this->assertEquals('STRBEFORE(?email, "@")', $result);
    }

    public function test_strafter_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->strAfter('?email', '"@"');

        $this->assertEquals('STRAFTER(?email, "@")', $result);
    }

    public function test_replace_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->replace('?text', '"old"', '"new"');

        $this->assertEquals('REPLACE(?text, "old", "new")', $result);
    }

    public function test_replace_function_with_flags(): void
    {
        $query = $this->getBuilder();
        $result = $query->replace('?text', '"old"', '"new"', '"i"');

        $this->assertEquals('REPLACE(?text, "old", "new", "i")', $result);
    }

    // ========================================
    // SPARQL Function Tests - Numeric Functions
    // ========================================

    public function test_abs_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->abs('?value');

        $this->assertEquals('ABS(?value)', $result);
    }

    public function test_ceil_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->ceil('?value');

        $this->assertEquals('CEIL(?value)', $result);
    }

    public function test_floor_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->floor('?value');

        $this->assertEquals('FLOOR(?value)', $result);
    }

    public function test_round_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->round('?value');

        $this->assertEquals('ROUND(?value)', $result);
    }

    public function test_rand_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->rand();

        $this->assertEquals('RAND()', $result);
    }

    // ========================================
    // SPARQL Function Tests - Date/Time Functions
    // ========================================

    public function test_now_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->now();

        $this->assertEquals('NOW()', $result);
    }

    public function test_year_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->year('?date');

        $this->assertEquals('YEAR(?date)', $result);
    }

    public function test_month_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->month('?date');

        $this->assertEquals('MONTH(?date)', $result);
    }

    public function test_day_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->day('?date');

        $this->assertEquals('DAY(?date)', $result);
    }

    public function test_hours_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->hours('?time');

        $this->assertEquals('HOURS(?time)', $result);
    }

    public function test_minutes_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->minutes('?time');

        $this->assertEquals('MINUTES(?time)', $result);
    }

    public function test_seconds_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->seconds('?time');

        $this->assertEquals('SECONDS(?time)', $result);
    }

    public function test_timezone_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->timezone('?datetime');

        $this->assertEquals('TIMEZONE(?datetime)', $result);
    }

    public function test_tz_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->tz('?datetime');

        $this->assertEquals('TZ(?datetime)', $result);
    }

    // ========================================
    // SPARQL Function Tests - Hash Functions
    // ========================================

    public function test_md5_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->md5('?value');

        $this->assertEquals('MD5(?value)', $result);
    }

    public function test_sha1_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->sha1('?value');

        $this->assertEquals('SHA1(?value)', $result);
    }

    public function test_sha256_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->sha256('?value');

        $this->assertEquals('SHA256(?value)', $result);
    }

    public function test_sha384_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->sha384('?value');

        $this->assertEquals('SHA384(?value)', $result);
    }

    public function test_sha512_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->sha512('?value');

        $this->assertEquals('SHA512(?value)', $result);
    }

    // ========================================
    // SPARQL Function Tests - Conditional Functions
    // ========================================

    public function test_coalesce_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->coalesce('?email', '?alternateEmail', '"no-email"');

        $this->assertEquals('COALESCE(?email, ?alternateEmail, "no-email")', $result);
    }

    public function test_if_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->if('?age > 18', '"adult"', '"minor"');

        $this->assertEquals('IF(?age > 18, "adult", "minor")', $result);
    }

    // ========================================
    // SPARQL Function Tests - Other Functions
    // ========================================

    public function test_regex_function_without_flags(): void
    {
        $query = $this->getBuilder();
        $result = $query->regex('?name', '"^John"');

        $this->assertEquals('REGEX(?name, "^John")', $result);
    }

    public function test_regex_function_with_flags(): void
    {
        $query = $this->getBuilder();
        $result = $query->regex('?name', '"^john"', '"i"');

        $this->assertEquals('REGEX(?name, "^john", "i")', $result);
    }

    public function test_lang_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->lang('?label');

        $this->assertEquals('LANG(?label)', $result);
    }

    public function test_langmatches_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->langMatches('?lang', '"en"');

        $this->assertEquals('LANGMATCHES(?lang, "en")', $result);
    }

    public function test_datatype_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->datatype('?value');

        $this->assertEquals('DATATYPE(?value)', $result);
    }

    public function test_iri_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->iri('?path');

        $this->assertEquals('IRI(?path)', $result);
    }

    public function test_bnode_function_without_arg(): void
    {
        $query = $this->getBuilder();
        $result = $query->bnode();

        $this->assertEquals('BNODE()', $result);
    }

    public function test_bnode_function_with_arg(): void
    {
        $query = $this->getBuilder();
        $result = $query->bnode('?value');

        $this->assertEquals('BNODE(?value)', $result);
    }

    public function test_isiri_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->isIri('?value');

        $this->assertEquals('ISIRI(?value)', $result);
    }

    public function test_isblank_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->isBlank('?value');

        $this->assertEquals('ISBLANK(?value)', $result);
    }

    public function test_isliteral_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->isLiteral('?value');

        $this->assertEquals('ISLITERAL(?value)', $result);
    }

    public function test_isnumeric_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->isNumeric('?value');

        $this->assertEquals('ISNUMERIC(?value)', $result);
    }

    public function test_bound_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->bound('value');

        $this->assertEquals('BOUND(?value)', $result);
    }

    public function test_bound_function_with_question_mark(): void
    {
        $query = $this->getBuilder();
        $result = $query->bound('?value');

        $this->assertEquals('BOUND(?value)', $result);
    }

    public function test_sameterm_function(): void
    {
        $query = $this->getBuilder();
        $result = $query->sameTerm('?value1', '?value2');

        $this->assertEquals('SAMETERM(?value1, ?value2)', $result);
    }

    // ========================================
    // Integration Tests - Combining Features
    // ========================================

    public function test_bind_with_string_function(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->bind($query->ucase('?name'), 'upperName');

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND', $sql);
        $this->assertStringContainsString('UCASE(?name)', $sql);
    }

    public function test_bind_with_concat_function(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->bind($query->concat('?firstName', '" "', '?lastName'), 'fullName');

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND', $sql);
        $this->assertStringContainsString('CONCAT', $sql);
    }

    public function test_complex_query_with_multiple_features(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->where('foaf:firstName', '=', 'John')
            ->bind($query->concat('?firstName', '" "', '?lastName'), 'fullName')
            ->values('age', [25, 30, 35])
            ->optional(function ($q) {
                $q->where('foaf:email', '=', '?email');
            })
            ->minus(function ($q) {
                $q->where('foaf:status', '=', 'deleted');
            });

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND', $sql);
        $this->assertStringContainsString('CONCAT', $sql);
        $this->assertStringContainsString('VALUES', $sql);
        $this->assertStringContainsString('OPTIONAL', $sql);
        $this->assertStringContainsString('MINUS', $sql);
    }

    public function test_bind_with_hash_function(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->bind($query->md5('?email'), 'emailHash');

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND', $sql);
        $this->assertStringContainsString('MD5(?email)', $sql);
    }

    public function test_bind_with_date_function(): void
    {
        $query = $this->getBuilder();
        $query->from('foaf:Person')
            ->bind($query->year('?birthDate'), 'birthYear');

        $sql = $query->toSparql();

        $this->assertStringContainsString('BIND', $sql);
        $this->assertStringContainsString('YEAR(?birthDate)', $sql);
    }

    public function test_nested_function_calls(): void
    {
        $query = $this->getBuilder();
        $upperSubstr = $query->ucase($query->substr('?name', 1, 5));

        $this->assertStringContainsString('UCASE', $upperSubstr);
        $this->assertStringContainsString('SUBSTR', $upperSubstr);
    }
}
