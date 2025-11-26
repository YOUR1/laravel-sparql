<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Eloquent\Relations\BelongsTo;
use LinkedData\SPARQL\Query\Expression;
use LinkedData\SPARQL\Tests\TestCase;

class ParentModel extends Model
{
    protected $table = 'foaf:Organization';

    protected $fillable = ['foaf:name'];
}

class ChildModel extends Model
{
    protected $table = 'foaf:Person';

    protected $fillable = ['foaf:name', 'foaf:organization'];

    public function organization()
    {
        return $this->belongsTo(ParentModel::class, 'foaf:organization', 'id');
    }
}

class BelongsToTest extends TestCase
{
    public function test_belongs_to_relation_can_be_defined(): void
    {
        $child = new ChildModel;
        $relation = $child->organization();

        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_belongs_to_sets_foreign_key(): void
    {
        $child = new ChildModel;
        $relation = $child->organization();

        $this->assertEquals('foaf:organization', $relation->getForeignKeyName());
    }

    public function test_belongs_to_sets_owner_key(): void
    {
        $child = new ChildModel;
        $relation = $child->organization();

        $this->assertEquals('id', $relation->getOwnerKeyName());
    }

    public function test_belongs_to_associates_model(): void
    {
        $parent = new ParentModel;
        $parent->id = 'http://example.org/org1';
        $parent->setAttribute('foaf:name', 'Test Org');

        $child = new ChildModel;
        $child->organization()->associate($parent);

        $foreignKey = $child->getAttribute('foaf:organization');
        $this->assertInstanceOf(Expression::class, $foreignKey);
        $this->assertEquals('<http://example.org/org1>', (string) $foreignKey);
        $this->assertSame($parent, $child->getRelation('organization'));
    }

    public function test_belongs_to_associates_by_id(): void
    {
        $child = new ChildModel;
        $child->organization()->associate('http://example.org/org1');

        // Foreign key is now stored as Expression::iri() for proper SPARQL serialization
        $foreignKey = $child->getAttribute('foaf:organization');
        $this->assertInstanceOf(Expression::class, $foreignKey);
        $this->assertEquals('<http://example.org/org1>', (string) $foreignKey);
    }

    public function test_belongs_to_dissociates_model(): void
    {
        $parent = new ParentModel;
        $parent->id = 'http://example.org/org1';

        $child = new ChildModel;
        $child->organization()->associate($parent);
        $this->assertNotNull($child->getAttribute('foaf:organization'));

        $child->organization()->dissociate();

        // The relation should be set to null after dissociation
        $this->assertNull($child->getRelation('organization'));
    }

    public function test_belongs_to_returns_related_model(): void
    {
        $parent = new ParentModel;
        $parent->id = 'http://example.org/org1';

        $child = new ChildModel;
        $relation = $child->organization();

        // Mock the parent model being set
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(ParentModel::class, get_class($relation->getRelated()));
    }

    public function test_belongs_to_initializes_relation_with_null(): void
    {
        $models = [new ChildModel, new ChildModel];
        $relation = (new ChildModel)->organization();

        $result = $relation->initRelation($models, 'organization');

        $this->assertNull($result[0]->getRelation('organization'));
        $this->assertNull($result[1]->getRelation('organization'));
    }

    public function test_belongs_to_matches_eager_loaded_results(): void
    {
        // Create parent models
        $parent1 = new ParentModel;
        $parent1->id = 'http://example.org/org1';
        $parent1->setAttribute('foaf:name', 'Organization 1');

        $parent2 = new ParentModel;
        $parent2->id = 'http://example.org/org2';
        $parent2->setAttribute('foaf:name', 'Organization 2');

        // Create child models
        $child1 = new ChildModel;
        $child1->id = 'http://example.org/person1';
        $child1->setAttribute('foaf:organization', 'http://example.org/org1');

        $child2 = new ChildModel;
        $child2->id = 'http://example.org/person2';
        $child2->setAttribute('foaf:organization', 'http://example.org/org2');

        $child3 = new ChildModel;
        $child3->id = 'http://example.org/person3';
        $child3->setAttribute('foaf:organization', 'http://example.org/org1');

        // Match results
        $models = [$child1, $child2, $child3];
        $results = new Collection([$parent1, $parent2]);
        $relation = (new ChildModel)->organization();

        $matched = $relation->match($models, $results, 'organization');

        $this->assertSame($parent1, $matched[0]->getRelation('organization'));
        $this->assertSame($parent2, $matched[1]->getRelation('organization'));
        $this->assertSame($parent1, $matched[2]->getRelation('organization'));
    }

    public function test_belongs_to_gets_relation_name(): void
    {
        $child = new ChildModel;
        $relation = $child->organization();

        $this->assertEquals('organization', $relation->getRelationName());
    }

    public function test_belongs_to_gets_qualified_foreign_key_name(): void
    {
        $child = new ChildModel;
        $relation = $child->organization();

        // SPARQL models don't add table prefix in qualifyColumn
        $this->assertEquals('foaf:organization', $relation->getQualifiedForeignKeyName());
    }

    public function test_belongs_to_gets_qualified_owner_key_name(): void
    {
        $child = new ChildModel;
        $relation = $child->organization();

        // SPARQL models don't add table prefix in qualifyColumn
        $this->assertEquals('id', $relation->getQualifiedOwnerKeyName());
    }

    public function test_belongs_to_checks_foreign_key_attribute(): void
    {
        $child = new ChildModel;
        $child->setAttribute('foaf:organization', 'http://example.org/org1');

        $value = $child->getAttribute('foaf:organization');

        // After refactor: getAttribute returns scalars/arrays, not Collections
        $this->assertIsString($value);
        $this->assertEquals('http://example.org/org1', $value);
    }

    public function test_belongs_to_gathers_eager_model_keys(): void
    {
        $child1 = new ChildModel;
        $child1->setAttribute('foaf:organization', 'http://example.org/org1');

        $child2 = new ChildModel;
        $child2->setAttribute('foaf:organization', 'http://example.org/org2');

        $models = [$child1, $child2];

        // Use reflection to test the protected getEagerModelKeys method
        $relation = (new ChildModel)->organization();
        $reflection = new \ReflectionClass($relation);
        $method = $reflection->getMethod('getEagerModelKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($relation, $models);

        // Should have 2 unique keys
        $this->assertCount(2, $keys);
    }
}
