<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Tests\TestCase;

class TestModel extends Model
{
    protected $table = 'foaf:Person';

    protected $fillable = ['foaf:name', 'foaf:email'];
}

class ModelTest extends TestCase
{
    public function test_model_can_be_instantiated(): void
    {
        $model = new TestModel;

        $this->assertInstanceOf(Model::class, $model);
        $this->assertEquals('foaf:Person', $model->getTable());
    }

    public function test_model_attributes_are_scalars_or_arrays(): void
    {
        $model = new TestModel;

        // Single-valued attribute should be a scalar (string)
        $model->setAttribute('foaf:name', 'John Doe');
        $attribute = $model->getAttribute('foaf:name');
        $this->assertIsString($attribute);
        $this->assertEquals('John Doe', $attribute);

        // Multi-valued attribute should be an array
        $model->setAttribute('foaf:knows', ['http://example.com/person1', 'http://example.com/person2']);
        $knowsAttribute = $model->getAttribute('foaf:knows');
        $this->assertIsArray($knowsAttribute);
        $this->assertCount(2, $knowsAttribute);
    }

    public function test_model_has_non_incrementing_id(): void
    {
        $model = new TestModel;

        $this->assertFalse($model->incrementing);
        $this->assertEquals('string', $model->getKeyType());
    }

    public function test_model_can_set_and_get_id(): void
    {
        $model = new TestModel;
        $model->id = 'http://example.org/person1';

        $this->assertEquals('http://example.org/person1', $model->id);
        $this->assertEquals('http://example.org/person1', $model->getKey());
    }

    public function test_model_fills_attributes(): void
    {
        $model = new TestModel;
        $model->fill([
            'foaf:name' => 'John Doe',
            'foaf:email' => 'john@example.org',
        ]);

        // After refactor: attributes are scalars/arrays, not Collections
        $this->assertIsString($model->getAttribute('foaf:name'));
        $this->assertEquals('John Doe', $model->getAttribute('foaf:name'));
        $this->assertIsString($model->getAttribute('foaf:email'));
        $this->assertEquals('john@example.org', $model->getAttribute('foaf:email'));
    }

    public function test_model_creates_new_query_builder(): void
    {
        $model = new TestModel;
        $query = $model->newQuery();

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Builder::class, $query);
    }

    public function test_model_gets_connection(): void
    {
        $model = new TestModel;
        $connection = $model->getConnection();

        $this->assertInstanceOf(\LinkedData\SPARQL\Connection::class, $connection);
        $this->assertEquals('sparql', $connection->getDriverName());
    }

    public function test_model_underscore_attribute_access(): void
    {
        $model = new TestModel;
        $model->setAttribute('foaf:name', 'Jane Doe');

        // Access via underscore notation
        $name = $model->foaf_name;

        // After refactor: attributes are scalars/arrays, not Collections
        $this->assertIsString($name);
        $this->assertEquals('Jane Doe', $name);
    }
}
