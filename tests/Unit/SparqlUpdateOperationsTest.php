<?php

namespace Tests\Unit;

use LinkedData\SPARQL\Query\Builder;
use LinkedData\SPARQL\Query\Grammar;
use LinkedData\SPARQL\Query\Processor;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * Test SPARQL 1.1 Update Operations
 *
 * Tests for INSERT DATA, DELETE DATA, INSERT WHERE, DELETE WHERE,
 * DELETE/INSERT, LOAD, CLEAR, DROP, CREATE, COPY, MOVE, and ADD operations.
 *
 * @see https://www.w3.org/TR/sparql11-update/
 */
class SparqlUpdateOperationsTest extends TestCase
{
    protected $connection;

    protected $grammar;

    protected $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = m::mock('Illuminate\Database\ConnectionInterface');
        $this->grammar = new Grammar;
        $this->processor = new Processor;
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function getBuilder()
    {
        return new Builder($this->connection, $this->grammar, $this->processor);
    }

    /** @test */
    public function it_compiles_insert_data_operation()
    {
        $builder = $this->getBuilder();
        $builder->insertData([
            ['<http://example.org/book1>', 'dc:title', '"A new book"'],
            ['<http://example.org/book1>', 'dc:creator', '"A.N.Author"'],
        ]);

        $sql = $this->grammar->compileInsertData($builder);

        $expected = 'INSERT DATA { <http://example.org/book1> dc:title "A new book" . <http://example.org/book1> dc:creator "A.N.Author" .  }';

        $this->assertEquals($expected, $sql);
    }

    /** @test */
    public function it_compiles_insert_data_with_graph()
    {
        $builder = $this->getBuilder();
        $builder->graph('http://example.org/bookStore');
        $builder->insertData([
            ['<http://example.org/book1>', 'dc:title', '"SPARQL Tutorial"'],
        ]);

        $sql = $this->grammar->compileInsertData($builder);

        $expected = 'INSERT DATA { GRAPH <http://example.org/bookStore> { <http://example.org/book1> dc:title "SPARQL Tutorial" .  } }';

        $this->assertEquals($expected, $sql);
    }

    /** @test */
    public function it_compiles_delete_data_operation()
    {
        $builder = $this->getBuilder();
        $builder->deleteData([
            ['<http://example.org/book1>', 'dc:title', '"A new book"'],
        ]);

        $sql = $this->grammar->compileDeleteData($builder);

        $expected = 'DELETE DATA { <http://example.org/book1> dc:title "A new book" .  }';

        $this->assertEquals($expected, $sql);
    }

    /** @test */
    public function it_compiles_delete_data_with_graph()
    {
        $builder = $this->getBuilder();
        $builder->graph('http://example.org/bookStore');
        $builder->deleteData([
            ['<http://example.org/book2>', 'dc:title', '"SPARQL 1.1"'],
        ]);

        $sql = $this->grammar->compileDeleteData($builder);

        $expected = 'DELETE DATA { GRAPH <http://example.org/bookStore> { <http://example.org/book2> dc:title "SPARQL 1.1" .  } }';

        $this->assertEquals($expected, $sql);
    }

    /** @test */
    public function it_compiles_insert_where_operation()
    {
        $builder = $this->getBuilder();
        $builder->from('http://example.org/Book');
        $builder->insertWhere([
            ['?book', 'dc:hasReview', '?review'],
        ]);
        $builder->where('?book', 'dc:title', '?title');

        $sql = $this->grammar->compileInsertWhere($builder);

        $this->assertStringContainsString('INSERT {', $sql);
        $this->assertStringContainsString('?book dc:hasReview ?review', $sql);
        $this->assertMatchesRegularExpression('/where/i', $sql);
    }

    /** @test */
    public function it_compiles_insert_where_with_string_template()
    {
        $builder = $this->getBuilder();
        $builder->from('http://example.org/Book');
        $builder->insertWhere('?book dc:publisher ?publisher');
        $builder->where('?book', 'dc:creator', '"A.N.Author"');

        $sql = $this->grammar->compileInsertWhere($builder);

        $this->assertStringContainsString('INSERT {', $sql);
        $this->assertStringContainsString('?book dc:publisher ?publisher', $sql);
        $this->assertMatchesRegularExpression('/where/i', $sql);
    }

    /** @test */
    public function it_compiles_delete_where_operation()
    {
        $builder = $this->getBuilder();
        $builder->from('http://example.org/Book');
        $builder->deleteWhere([
            ['?book', 'dc:title', '?title'],
        ]);
        $builder->where('?book', 'dc:title', '"SPARQL Tutorial"');

        $sql = $this->grammar->compileDeleteWhere($builder);

        $this->assertStringContainsString('DELETE {', $sql);
        $this->assertStringContainsString('?book dc:title ?title', $sql);
        $this->assertMatchesRegularExpression('/where/i', $sql);
    }

    /** @test */
    public function it_compiles_delete_insert_combined_operation()
    {
        $builder = $this->getBuilder();
        $builder->from('http://example.org/Book');
        $builder->deleteInsert(
            [['?book', 'dc:title', '?oldTitle']],
            [['?book', 'dc:title', '"New Title"']]
        );
        $builder->where('?book', 'dc:title', '?oldTitle');

        $sql = $this->grammar->compileDeleteInsert($builder);

        $this->assertStringContainsString('DELETE {', $sql);
        $this->assertStringContainsString('?book dc:title ?oldTitle', $sql);
        $this->assertStringContainsString('INSERT {', $sql);
        $this->assertStringContainsString('?book dc:title "New Title"', $sql);
        $this->assertMatchesRegularExpression('/where/i', $sql);
    }

    /** @test */
    public function it_compiles_delete_insert_with_string_templates()
    {
        $builder = $this->getBuilder();
        $builder->from('http://example.org/Book');
        $builder->deleteInsert(
            '?person foaf:givenName ?name',
            '?person foaf:givenName "William"'
        );
        $builder->where('?person', 'foaf:givenName', '"Bill"');

        $sql = $this->grammar->compileDeleteInsert($builder);

        $this->assertStringContainsString('DELETE {', $sql);
        $this->assertStringContainsString('?person foaf:givenName ?name', $sql);
        $this->assertStringContainsString('INSERT {', $sql);
        $this->assertStringContainsString('?person foaf:givenName "William"', $sql);
        $this->assertMatchesRegularExpression('/where/i', $sql);
    }

    /** @test */
    public function it_compiles_load_operation()
    {
        $builder = $this->getBuilder();
        $builder->loadUrl = 'http://example.org/data.rdf';
        $builder->silent = false;

        $sql = $this->grammar->compileLoad($builder);

        $expected = 'LOAD <http://example.org/data.rdf>';

        $this->assertEquals($expected, $sql);
    }

    /** @test */
    public function it_compiles_load_operation_with_graph()
    {
        $builder = $this->getBuilder();
        $builder->loadUrl = 'http://example.org/data.rdf';
        $builder->targetGraph = 'http://example.org/graph1';
        $builder->silent = false;

        $sql = $this->grammar->compileLoad($builder);

        $expected = 'LOAD <http://example.org/data.rdf> INTO GRAPH <http://example.org/graph1>';

        $this->assertEquals($expected, $sql);
    }

    /** @test */
    public function it_compiles_load_operation_with_silent()
    {
        $builder = $this->getBuilder();
        $builder->loadUrl = 'http://example.org/data.rdf';
        $builder->silent = true;

        $sql = $this->grammar->compileLoad($builder);

        $expected = 'LOAD SILENT <http://example.org/data.rdf>';

        $this->assertEquals($expected, $sql);
    }

    /** @test */
    public function it_compiles_clear_default_graph()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = null;
        $builder->silent = false;

        $sql = $this->grammar->compileClear($builder);

        $this->assertEquals('CLEAR DEFAULT', $sql);
    }

    /** @test */
    public function it_compiles_clear_named_graph()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'http://example.org/graph1';
        $builder->silent = false;

        $sql = $this->grammar->compileClear($builder);

        $this->assertEquals('CLEAR GRAPH <http://example.org/graph1>', $sql);
    }

    /** @test */
    public function it_compiles_clear_all_named_graphs()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'NAMED';
        $builder->silent = false;

        $sql = $this->grammar->compileClear($builder);

        $this->assertEquals('CLEAR NAMED', $sql);
    }

    /** @test */
    public function it_compiles_clear_all_graphs()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'ALL';
        $builder->silent = false;

        $sql = $this->grammar->compileClear($builder);

        $this->assertEquals('CLEAR ALL', $sql);
    }

    /** @test */
    public function it_compiles_clear_with_silent()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'http://example.org/graph1';
        $builder->silent = true;

        $sql = $this->grammar->compileClear($builder);

        $this->assertEquals('CLEAR SILENT GRAPH <http://example.org/graph1>', $sql);
    }

    /** @test */
    public function it_compiles_drop_default_graph()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = null;
        $builder->silent = false;

        $sql = $this->grammar->compileDrop($builder);

        $this->assertEquals('DROP DEFAULT', $sql);
    }

    /** @test */
    public function it_compiles_drop_named_graph()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'http://example.org/graph1';
        $builder->silent = false;

        $sql = $this->grammar->compileDrop($builder);

        $this->assertEquals('DROP GRAPH <http://example.org/graph1>', $sql);
    }

    /** @test */
    public function it_compiles_drop_all_graphs()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'ALL';
        $builder->silent = true;

        $sql = $this->grammar->compileDrop($builder);

        $this->assertEquals('DROP SILENT ALL', $sql);
    }

    /** @test */
    public function it_compiles_create_graph()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'http://example.org/newGraph';
        $builder->silent = false;

        $sql = $this->grammar->compileCreate($builder);

        $this->assertEquals('CREATE GRAPH <http://example.org/newGraph>', $sql);
    }

    /** @test */
    public function it_compiles_create_graph_with_silent()
    {
        $builder = $this->getBuilder();
        $builder->targetGraph = 'http://example.org/newGraph';
        $builder->silent = true;

        $sql = $this->grammar->compileCreate($builder);

        $this->assertEquals('CREATE SILENT GRAPH <http://example.org/newGraph>', $sql);
    }

    /** @test */
    public function it_compiles_copy_between_graphs()
    {
        $builder = $this->getBuilder();
        $builder->sourceGraph = 'http://example.org/graph1';
        $builder->targetGraph = 'http://example.org/graph2';
        $builder->silent = false;

        $sql = $this->grammar->compileCopy($builder);

        $this->assertEquals('COPY GRAPH <http://example.org/graph1> TO GRAPH <http://example.org/graph2>', $sql);
    }

    /** @test */
    public function it_compiles_copy_from_default_graph()
    {
        $builder = $this->getBuilder();
        $builder->sourceGraph = null;
        $builder->targetGraph = 'http://example.org/graph1';
        $builder->silent = false;

        $sql = $this->grammar->compileCopy($builder);

        $this->assertEquals('COPY DEFAULT TO GRAPH <http://example.org/graph1>', $sql);
    }

    /** @test */
    public function it_compiles_copy_with_silent()
    {
        $builder = $this->getBuilder();
        $builder->sourceGraph = 'http://example.org/graph1';
        $builder->targetGraph = 'http://example.org/graph2';
        $builder->silent = true;

        $sql = $this->grammar->compileCopy($builder);

        $this->assertEquals('COPY SILENT GRAPH <http://example.org/graph1> TO GRAPH <http://example.org/graph2>', $sql);
    }

    /** @test */
    public function it_compiles_move_between_graphs()
    {
        $builder = $this->getBuilder();
        $builder->sourceGraph = 'http://example.org/graph1';
        $builder->targetGraph = 'http://example.org/graph2';
        $builder->silent = false;

        $sql = $this->grammar->compileMove($builder);

        $this->assertEquals('MOVE GRAPH <http://example.org/graph1> TO GRAPH <http://example.org/graph2>', $sql);
    }

    /** @test */
    public function it_compiles_move_to_default_graph()
    {
        $builder = $this->getBuilder();
        $builder->sourceGraph = 'http://example.org/graph1';
        $builder->targetGraph = null;
        $builder->silent = false;

        $sql = $this->grammar->compileMove($builder);

        $this->assertEquals('MOVE GRAPH <http://example.org/graph1> TO DEFAULT', $sql);
    }

    /** @test */
    public function it_compiles_add_between_graphs()
    {
        $builder = $this->getBuilder();
        $builder->sourceGraph = 'http://example.org/graph1';
        $builder->targetGraph = 'http://example.org/graph2';
        $builder->silent = false;

        $sql = $this->grammar->compileAdd($builder);

        $this->assertEquals('ADD GRAPH <http://example.org/graph1> TO GRAPH <http://example.org/graph2>', $sql);
    }

    /** @test */
    public function it_compiles_add_with_silent()
    {
        $builder = $this->getBuilder();
        $builder->sourceGraph = 'http://example.org/graph1';
        $builder->targetGraph = 'http://example.org/graph2';
        $builder->silent = true;

        $sql = $this->grammar->compileAdd($builder);

        $this->assertEquals('ADD SILENT GRAPH <http://example.org/graph1> TO GRAPH <http://example.org/graph2>', $sql);
    }

    /** @test */
    public function it_compiles_triples_helper()
    {
        $triples = [
            ['<http://example.org/book1>', 'dc:title', '"Book Title"'],
            ['<http://example.org/book1>', 'dc:creator', '"Author Name"'],
            ['<http://example.org/book1>', 'dc:date', '"2024"'],
        ];

        $result = $this->grammar->compileTriples($triples);

        $this->assertStringContainsString('<http://example.org/book1> dc:title "Book Title" .', $result);
        $this->assertStringContainsString('<http://example.org/book1> dc:creator "Author Name" .', $result);
        $this->assertStringContainsString('<http://example.org/book1> dc:date "2024" .', $result);
    }

    /** @test */
    public function it_compiles_graph_ref_for_default_graph()
    {
        $result = $this->grammar->compileGraphRef(null);

        $this->assertEquals(' DEFAULT', $result);
    }

    /** @test */
    public function it_compiles_graph_ref_for_named_graph()
    {
        $result = $this->grammar->compileGraphRef('http://example.org/graph1');

        $this->assertEquals(' GRAPH <http://example.org/graph1>', $result);
    }
}
