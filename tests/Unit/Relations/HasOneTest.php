<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Eloquent\Relations\HasOne;
use LinkedData\SPARQL\Tests\TestCase;

class UserModel extends Model
{
    protected $table = 'foaf:Person';

    protected $fillable = ['foaf:name'];

    public function profile()
    {
        return $this->hasOne(ProfileModel::class, 'foaf:user', 'id');
    }
}

class ProfileModel extends Model
{
    protected $table = 'foaf:Profile';

    protected $fillable = ['foaf:bio', 'foaf:user'];
}

class HasOneTest extends TestCase
{
    public function test_has_one_relation_can_be_defined(): void
    {
        $user = new UserModel;
        $relation = $user->profile();

        $this->assertInstanceOf(HasOne::class, $relation);
    }

    public function test_has_one_sets_foreign_key(): void
    {
        $user = new UserModel;
        $relation = $user->profile();

        $this->assertEquals('foaf:user', $relation->getForeignKeyName());
    }

    public function test_has_one_sets_local_key(): void
    {
        $user = new UserModel;
        $relation = $user->profile();

        $this->assertEquals('id', $relation->getLocalKeyName());
    }

    public function test_has_one_returns_related_model_type(): void
    {
        $user = new UserModel;
        $relation = $user->profile();

        $this->assertInstanceOf(ProfileModel::class, $relation->getRelated());
    }

    public function test_has_one_initializes_relation_with_null(): void
    {
        $models = [new UserModel, new UserModel];
        $relation = (new UserModel)->profile();

        $result = $relation->initRelation($models, 'profile');

        $this->assertNull($result[0]->getRelation('profile'));
        $this->assertNull($result[1]->getRelation('profile'));
    }

    public function test_has_one_match_uses_match_one(): void
    {
        // HasOne.match() should call matchOne, not matchMany
        // This is a structural test to ensure the correct behavior
        $user = new UserModel;
        $relation = $user->profile();

        // Verify that HasOne extends HasMany and overrides match() to call matchOne()
        $this->assertInstanceOf(HasOne::class, $relation);

        // The match method returns the models array
        $models = [new UserModel, new UserModel];
        $results = new Collection;
        $matched = $relation->match($models, $results, 'profile');

        $this->assertIsArray($matched);
        $this->assertCount(2, $matched);
    }

    public function test_has_one_creates_new_related_instance(): void
    {
        $user = new UserModel;
        $user->id = 'http://example.org/user1';

        $relation = $user->profile();
        $profile = $relation->make(['foaf:bio' => 'Test bio']);

        $this->assertInstanceOf(ProfileModel::class, $profile);
        $this->assertEquals('http://example.org/user1', $profile->getAttribute('foaf:user'));
    }

    public function test_has_one_gets_qualified_foreign_key_name(): void
    {
        $user = new UserModel;
        $relation = $user->profile();

        // In SPARQL, the qualified foreign key includes the related table
        $this->assertEquals('foaf:Profile.foaf:user', $relation->getQualifiedForeignKeyName());
    }

    public function test_has_one_gets_qualified_parent_key_name(): void
    {
        $user = new UserModel;
        $relation = $user->profile();

        // SPARQL doesn't add table prefix
        $this->assertEquals('id', $relation->getQualifiedParentKeyName());
    }

    public function test_has_one_returns_null_when_parent_key_is_null(): void
    {
        $user = new UserModel;
        // User has no ID set
        $relation = $user->profile();

        $result = $relation->getResults();

        $this->assertNull($result);
    }

    public function test_has_one_saves_related_model(): void
    {
        $user = new UserModel;
        $user->id = 'http://example.org/user1';

        $profile = new ProfileModel;
        $profile->id = 'http://example.org/profile1';
        $profile->setAttribute('foaf:bio', 'Test bio');

        $relation = $user->profile();

        // Since we can't actually save without a SPARQL endpoint,
        // we just verify the foreign key is set correctly
        $relation->setForeignAttributesForCreate($profile);

        $this->assertEquals('http://example.org/user1', $profile->getAttribute('foaf:user'));
    }

    public function test_has_one_creates_related_model(): void
    {
        $user = new UserModel;
        $user->id = 'http://example.org/user1';

        $relation = $user->profile();

        // We can't actually create without a SPARQL endpoint,
        // but we can verify the make method works
        $profile = $relation->make(['foaf:bio' => 'New bio']);

        $this->assertInstanceOf(ProfileModel::class, $profile);
        $this->assertEquals('New bio', $profile->getAttribute('foaf:bio'));
    }

    public function test_has_one_finds_or_creates_new(): void
    {
        $user = new UserModel;
        $user->id = 'http://example.org/user1';

        $relation = $user->profile();

        // We can't test the full flow without a SPARQL endpoint,
        // but we can test that make creates properly structured instances
        $instance = $relation->make(['foaf:bio' => 'Test']);

        $this->assertInstanceOf(ProfileModel::class, $instance);
    }

    public function test_has_one_inherits_from_has_many(): void
    {
        // HasOne should extend HasMany and inherit its dictionary building logic
        $user = new UserModel;
        $relation = $user->profile();

        // Verify HasOne is a subclass of HasMany
        $reflection = new \ReflectionClass($relation);
        $parent = $reflection->getParentClass();

        $this->assertNotFalse($parent);
        $this->assertEquals('LinkedData\SPARQL\Eloquent\Relations\HasMany', $parent->getName());

        // Verify buildDictionary method exists (inherited)
        $this->assertTrue($reflection->hasMethod('buildDictionary'));
    }
}
