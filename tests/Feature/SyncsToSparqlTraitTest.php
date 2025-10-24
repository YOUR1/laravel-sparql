<?php

namespace LinkedData\SPARQL\Tests\Feature;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LinkedData\SPARQL\Eloquent\Concerns\SyncsToSparql;
use LinkedData\SPARQL\Tests\IntegrationTestCase;

/**
 * Integration tests for the SyncsToSparql trait.
 *
 * These tests verify that regular Eloquent models can sync to SPARQL endpoints.
 */
class SyncsToSparqlTraitTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Parent already clears test graph, no additional clearing needed
    }

    protected function tearDown(): void
    {
        // Parent already clears test graph on tearDown

        parent::tearDown();
    }

    public function test_can_sync_eloquent_model_to_sparql()
    {
        // Create a mock Eloquent model with the trait
        $product = new TestProduct([
            'id' => 1,
            'name' => 'Widget',
            'price' => 19.99,
            'description' => 'A useful widget',
        ]);

        // Sync to SPARQL
        $result = $product->syncToSparql();

        // Assert sync was successful
        $this->assertTrue($result);

        // Verify triples exist in SPARQL store
        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/1>',
            '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>',
            '<http://schema.org/Product>'
        ));

        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/1>',
            '<http://schema.org/name>',
            '"Widget"'
        ));

        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/1>',
            '<http://schema.org/price>',
            '"19.99"^^<http://www.w3.org/2001/XMLSchema#decimal>'
        ));
    }

    public function test_can_sync_model_with_multiple_values()
    {
        $person = new TestPerson([
            'id' => 1,
            'name' => 'John Doe',
            'email' => ['john@example.com', 'doe@example.com'],
        ]);

        $result = $person->syncToSparql();

        $this->assertTrue($result);

        // Verify both emails exist
        $this->assertTrue($this->tripleExists(
            '<http://example.com/person/1>',
            '<http://schema.org/email>',
            '"john@example.com"'
        ));

        $this->assertTrue($this->tripleExists(
            '<http://example.com/person/1>',
            '<http://schema.org/email>',
            '"doe@example.com"'
        ));
    }

    public function test_can_delete_model_from_sparql()
    {
        // First, create a resource
        $product = new TestProduct([
            'id' => 2,
            'name' => 'Gadget',
            'price' => 29.99,
        ]);

        $product->syncToSparql();

        // Verify it exists
        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/2>',
            '<http://schema.org/name>',
            '"Gadget"'
        ));

        // Delete from SPARQL
        $result = $product->deleteFromSparql();

        $this->assertTrue($result);

        // Verify it's gone
        $this->assertFalse($this->tripleExists(
            '<http://example.com/product/2>',
            '<http://schema.org/name>',
            '"Gadget"'
        ));
    }

    public function test_can_batch_sync_multiple_models()
    {
        // Create multiple models
        $products = [
            new TestProduct(['id' => 10, 'name' => 'Product A', 'price' => 10.00]),
            new TestProduct(['id' => 11, 'name' => 'Product B', 'price' => 20.00]),
            new TestProduct(['id' => 12, 'name' => 'Product C', 'price' => 30.00]),
        ];

        // Batch sync
        $count = TestProduct::syncBatchToSparql($products);

        $this->assertEquals(3, $count);

        // Verify all products exist in SPARQL
        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/10>',
            '<http://schema.org/name>',
            '"Product A"'
        ));

        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/11>',
            '<http://schema.org/name>',
            '"Product B"'
        ));

        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/12>',
            '<http://schema.org/name>',
            '"Product C"'
        ));
    }

    public function test_can_batch_delete_multiple_models()
    {
        // First, create multiple models
        $products = [
            new TestProduct(['id' => 20, 'name' => 'Product X', 'price' => 10.00]),
            new TestProduct(['id' => 21, 'name' => 'Product Y', 'price' => 20.00]),
        ];

        TestProduct::syncBatchToSparql($products);

        // Verify they exist
        $this->assertTrue($this->tripleExists(
            '<http://example.com/product/20>',
            '<http://schema.org/name>',
            '"Product X"'
        ));

        // Batch delete
        $count = TestProduct::deleteBatchFromSparql($products);

        $this->assertEquals(2, $count);

        // Verify they're gone
        $this->assertFalse($this->tripleExists(
            '<http://example.com/product/20>',
            '<http://schema.org/name>',
            '"Product X"'
        ));

        $this->assertFalse($this->tripleExists(
            '<http://example.com/product/21>',
            '<http://schema.org/name>',
            '"Product Y"'
        ));
    }

    public function test_batch_sync_with_empty_collection_returns_zero()
    {
        $count = TestProduct::syncBatchToSparql([]);
        $this->assertEquals(0, $count);
    }

    public function test_batch_delete_with_empty_collection_returns_zero()
    {
        $count = TestProduct::deleteBatchFromSparql([]);
        $this->assertEquals(0, $count);
    }
}

/**
 * Mock Eloquent model for testing SyncsToSparql trait
 */
class TestProduct extends EloquentModel
{
    use SyncsToSparql;

    protected $connection = 'mysql'; // Regular Eloquent connection

    protected $table = 'products';

    protected $fillable = ['id', 'name', 'price', 'description'];

    public $timestamps = false;

    public function getSparqlConnection(): string
    {
        return 'sparql';
    }

    public function getSparqlUri(): string
    {
        return 'http://example.com/product/' . $this->id;
    }

    public function getSparqlRdfClass(): string
    {
        return 'http://schema.org/Product';
    }

    public function toSparqlAttributes(): array
    {
        return [
            'http://schema.org/name' => $this->name,
            'http://schema.org/price' => (float) $this->price,
            'http://schema.org/description' => $this->description,
        ];
    }
}

/**
 * Mock Eloquent model for testing multi-valued properties
 */
class TestPerson extends EloquentModel
{
    use SyncsToSparql;

    protected $connection = 'mysql';

    protected $table = 'people';

    protected $fillable = ['id', 'name', 'email'];

    public $timestamps = false;

    public function getSparqlConnection(): string
    {
        return 'sparql';
    }

    public function getSparqlUri(): string
    {
        return 'http://example.com/person/' . $this->id;
    }

    public function getSparqlRdfClass(): string
    {
        return 'http://schema.org/Person';
    }

    public function toSparqlAttributes(): array
    {
        $attrs = [
            'http://schema.org/name' => $this->name,
        ];

        // Email can be array or string
        if ($this->email) {
            $attrs['http://schema.org/email'] = $this->email;
        }

        return $attrs;
    }
}
