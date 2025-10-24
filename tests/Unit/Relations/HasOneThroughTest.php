<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Eloquent\Relations\HasOneThrough;
use LinkedData\SPARQL\Tests\TestCase;

class Supplier extends Model
{
    protected $table = 'org:Supplier';

    protected $fillable = ['org:name'];

    public function carOwner()
    {
        return $this->hasOneThrough(
            CarOwner::class,
            Car::class,
            'org:supplier',     // Foreign key on cars table
            'schema:ownsCar',   // Foreign key on car_owners table
            'id',               // Local key on suppliers table
            'id'                // Local key on cars table
        );
    }
}

class Car extends Model
{
    protected $table = 'schema:Car';

    protected $fillable = ['schema:name', 'org:supplier'];
}

class CarOwner extends Model
{
    protected $table = 'foaf:Person';

    protected $fillable = ['foaf:name', 'schema:ownsCar'];
}

class HasOneThroughTest extends TestCase
{
    public function test_has_one_through_relation_can_be_defined(): void
    {
        $supplier = new Supplier;
        $relation = $supplier->carOwner();

        $this->assertInstanceOf(HasOneThrough::class, $relation);
    }

    public function test_has_one_through_sets_keys(): void
    {
        $supplier = new Supplier;
        $relation = $supplier->carOwner();

        $this->assertEquals('org:supplier', $relation->getFirstKeyName());
        $this->assertEquals('schema:ownsCar', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getLocalKeyName());
        $this->assertEquals('id', $relation->getSecondLocalKeyName());
    }

    public function test_has_one_through_returns_related_model_type(): void
    {
        $supplier = new Supplier;
        $relation = $supplier->carOwner();

        $this->assertInstanceOf(CarOwner::class, $relation->getRelated());
    }

    public function test_has_one_through_initializes_relation_with_null(): void
    {
        $supplier1 = new Supplier;
        $supplier1->id = 'urn:supplier:1';

        $supplier2 = new Supplier;
        $supplier2->id = 'urn:supplier:2';

        $models = [$supplier1, $supplier2];

        $relation = $supplier1->carOwner();
        $result = $relation->initRelation($models, 'carOwner');

        $this->assertCount(2, $result);
        $this->assertNull($result[0]->carOwner);
        $this->assertNull($result[1]->carOwner);
    }

    public function test_has_one_through_gets_results(): void
    {
        $supplier = new Supplier;
        $supplier->id = 'urn:supplier:1';

        $relation = $supplier->carOwner();
        $results = $relation->getResults();

        // Should return null when no results
        $this->assertNull($results);
    }

    public function test_has_one_through_returns_null_when_parent_key_is_null(): void
    {
        $supplier = new Supplier;
        $supplier->id = null;

        $relation = $supplier->carOwner();
        $results = $relation->getResults();

        $this->assertNull($results);
    }

    public function test_has_one_through_matches_eager_loaded_results(): void
    {
        $supplier1 = new Supplier;
        $supplier1->id = 'urn:supplier:1';

        $supplier2 = new Supplier;
        $supplier2->id = 'urn:supplier:2';

        $owner1 = new CarOwner;
        $owner1->id = 'urn:owner:1';
        $owner1->{'org:supplier'} = collect(['urn:supplier:1']);

        $owner2 = new CarOwner;
        $owner2->id = 'urn:owner:2';
        $owner2->{'org:supplier'} = collect(['urn:supplier:2']);

        $models = [$supplier1, $supplier2];
        $results = new Collection([$owner1, $owner2]);

        $relation = $supplier1->carOwner();
        $relation->initRelation($models, 'carOwner');
        $matched = $relation->match($models, $results, 'carOwner');

        $this->assertCount(2, $matched);
        $this->assertInstanceOf(CarOwner::class, $matched[0]->carOwner);
        $this->assertInstanceOf(CarOwner::class, $matched[1]->carOwner);
        $this->assertEquals('urn:owner:1', $matched[0]->carOwner->id);
        $this->assertEquals('urn:owner:2', $matched[1]->carOwner->id);
    }

    public function test_has_one_through_inherits_from_has_many_through(): void
    {
        $supplier = new Supplier;
        $relation = $supplier->carOwner();

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Relations\HasManyThrough::class, $relation);
    }

    public function test_has_one_through_match_returns_first_result(): void
    {
        $supplier1 = new Supplier;
        $supplier1->id = 'urn:supplier:1';

        $owner1 = new CarOwner;
        $owner1->id = 'urn:owner:1';
        $owner1->{'org:supplier'} = collect(['urn:supplier:1']);

        $owner2 = new CarOwner;
        $owner2->id = 'urn:owner:2';
        $owner2->{'org:supplier'} = collect(['urn:supplier:1']);

        $models = [$supplier1];
        $results = new Collection([$owner1, $owner2]);

        $relation = $supplier1->carOwner();
        $relation->initRelation($models, 'carOwner');
        $matched = $relation->match($models, $results, 'carOwner');

        $this->assertCount(1, $matched);
        // Should only return the first result
        $this->assertInstanceOf(CarOwner::class, $matched[0]->carOwner);
        $this->assertEquals('urn:owner:1', $matched[0]->carOwner->id);
    }
}
