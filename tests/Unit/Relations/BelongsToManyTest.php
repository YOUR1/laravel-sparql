<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Eloquent\Relations\BelongsToMany;
use LinkedData\SPARQL\Query\Expression;
use LinkedData\SPARQL\Tests\TestCase;

class BelongsToManyUserModel extends Model
{
    protected $table = 'foaf:Person';

    protected $fillable = ['foaf:name'];

    public function roles()
    {
        return $this->belongsToMany(
            BelongsToManyRoleModel::class,
            'ex:UserRole',
            'ex:hasUser',
            'ex:hasRole',
            'id',
            'id'
        );
    }

    public function tags()
    {
        return $this->belongsToMany(
            BelongsToManyTagModel::class,
            'ex:UserTag',
            'ex:user',
            'ex:tag'
        );
    }
}

class BelongsToManyRoleModel extends Model
{
    protected $table = 'ex:Role';

    protected $fillable = ['ex:name'];
}

class BelongsToManyTagModel extends Model
{
    protected $table = 'ex:Tag';

    protected $fillable = ['ex:name'];
}

class BelongsToManyTest extends TestCase
{
    public function test_belongs_to_many_relation_can_be_defined(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_belongs_to_many_sets_table(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('ex:UserRole', $relation->getTable());
    }

    public function test_belongs_to_many_sets_foreign_pivot_key(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('ex:hasUser', $relation->getForeignPivotKeyName());
    }

    public function test_belongs_to_many_sets_related_pivot_key(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('ex:hasRole', $relation->getRelatedPivotKeyName());
    }

    public function test_belongs_to_many_sets_parent_key(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('id', $relation->getParentKeyName());
    }

    public function test_belongs_to_many_sets_related_key(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('id', $relation->getRelatedKeyName());
    }

    public function test_belongs_to_many_returns_related_model_type(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertInstanceOf(BelongsToManyRoleModel::class, $relation->getRelated());
    }

    public function test_belongs_to_many_initializes_relation_with_empty_collection(): void
    {
        $models = [new BelongsToManyUserModel, new BelongsToManyUserModel];
        $relation = (new BelongsToManyUserModel)->roles();

        $result = $relation->initRelation($models, 'roles');

        $this->assertInstanceOf(Collection::class, $result[0]->getRelation('roles'));
        $this->assertInstanceOf(Collection::class, $result[1]->getRelation('roles'));
        $this->assertCount(0, $result[0]->getRelation('roles'));
        $this->assertCount(0, $result[1]->getRelation('roles'));
    }

    public function test_belongs_to_many_with_pivot_adds_columns(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles()->withPivot('ex:priority', 'ex:status');

        $pivotColumns = $relation->getPivotColumns();

        $this->assertContains('ex:priority', $pivotColumns);
        $this->assertContains('ex:status', $pivotColumns);
    }

    public function test_belongs_to_many_with_pivot_accepts_array(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles()->withPivot(['ex:priority', 'ex:status']);

        $pivotColumns = $relation->getPivotColumns();

        $this->assertContains('ex:priority', $pivotColumns);
        $this->assertContains('ex:status', $pivotColumns);
    }

    public function test_belongs_to_many_with_timestamps_adds_timestamp_columns(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles()->withTimestamps();

        $pivotColumns = $relation->getPivotColumns();

        $this->assertContains('created_at', $pivotColumns);
        $this->assertContains('updated_at', $pivotColumns);
    }

    public function test_belongs_to_many_with_custom_timestamps(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles()->withTimestamps('ex:createdAt', 'ex:updatedAt');

        $pivotColumns = $relation->getPivotColumns();

        $this->assertContains('ex:createdAt', $pivotColumns);
        $this->assertContains('ex:updatedAt', $pivotColumns);
    }

    public function test_belongs_to_many_as_customizes_pivot_accessor(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles()->as('role_assignment');

        $this->assertEquals('role_assignment', $relation->getPivotAccessor());
    }

    public function test_belongs_to_many_default_pivot_accessor(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('pivot', $relation->getPivotAccessor());
    }

    public function test_belongs_to_many_match_with_pivot_data(): void
    {
        // Create users
        $user1 = new BelongsToManyUserModel;
        $user1->id = 'http://example.org/user1';

        $user2 = new BelongsToManyUserModel;
        $user2->id = 'http://example.org/user2';

        // Create roles with pivot data - first hydrate pivot, then migrate it
        $role1 = new BelongsToManyRoleModel;
        $role1->id = 'http://example.org/role1';
        $role1->setAttribute('ex:name', 'Admin');

        $role2 = new BelongsToManyRoleModel;
        $role2->id = 'http://example.org/role2';
        $role2->setAttribute('ex:name', 'Editor');

        $role3 = new BelongsToManyRoleModel;
        $role3->id = 'http://example.org/role3';
        $role3->setAttribute('ex:name', 'Viewer');

        // Create pivot models manually (simulating what would come from the database)
        $pivot1 = new BelongsToManyRoleModel;
        $pivot1->setAttribute('ex:hasUser', collect(['http://example.org/user1']));
        $pivot1->setAttribute('ex:hasRole', collect(['http://example.org/role1']));
        $role1->setRelation('pivot', $pivot1);

        $pivot2 = new BelongsToManyRoleModel;
        $pivot2->setAttribute('ex:hasUser', collect(['http://example.org/user1']));
        $pivot2->setAttribute('ex:hasRole', collect(['http://example.org/role2']));
        $role2->setRelation('pivot', $pivot2);

        $pivot3 = new BelongsToManyRoleModel;
        $pivot3->setAttribute('ex:hasUser', collect(['http://example.org/user2']));
        $pivot3->setAttribute('ex:hasRole', collect(['http://example.org/role3']));
        $role3->setRelation('pivot', $pivot3);

        // Match results
        $models = [$user1, $user2];
        $results = new Collection([$role1, $role2, $role3]);
        $relation = (new BelongsToManyUserModel)->roles();

        $matched = $relation->match($models, $results, 'roles');

        // User 1 should have 2 roles
        $this->assertCount(2, $matched[0]->getRelation('roles'));
        $this->assertEquals('http://example.org/role1', $matched[0]->getRelation('roles')[0]->id);
        $this->assertEquals('http://example.org/role2', $matched[0]->getRelation('roles')[1]->id);

        // User 2 should have 1 role
        $this->assertCount(1, $matched[1]->getRelation('roles'));
        $this->assertEquals('http://example.org/role3', $matched[1]->getRelation('roles')[0]->id);
    }

    public function test_belongs_to_many_build_dictionary(): void
    {
        $user1 = new BelongsToManyUserModel;
        $user1->id = 'http://example.org/user1';

        $user2 = new BelongsToManyUserModel;
        $user2->id = 'http://example.org/user2';

        // Create roles with pivot data
        $role1 = new BelongsToManyRoleModel;
        $role1->id = 'http://example.org/role1';

        $role2 = new BelongsToManyRoleModel;
        $role2->id = 'http://example.org/role2';

        $role3 = new BelongsToManyRoleModel;
        $role3->id = 'http://example.org/role3';

        // Create pivot models manually (simulating what would come from the database)
        $pivot1 = new BelongsToManyRoleModel;
        $pivot1->setAttribute('ex:hasUser', collect(['http://example.org/user1']));
        $pivot1->setAttribute('ex:hasRole', collect(['http://example.org/role1']));
        $role1->setRelation('pivot', $pivot1);

        $pivot2 = new BelongsToManyRoleModel;
        $pivot2->setAttribute('ex:hasUser', collect(['http://example.org/user1']));
        $pivot2->setAttribute('ex:hasRole', collect(['http://example.org/role2']));
        $role2->setRelation('pivot', $pivot2);

        $pivot3 = new BelongsToManyRoleModel;
        $pivot3->setAttribute('ex:hasUser', collect(['http://example.org/user2']));
        $pivot3->setAttribute('ex:hasRole', collect(['http://example.org/role3']));
        $role3->setRelation('pivot', $pivot3);

        $results = new Collection([$role1, $role2, $role3]);
        $relation = (new BelongsToManyUserModel)->roles();

        // Use reflection to access protected method
        $buildDictionary = new \ReflectionMethod($relation, 'buildDictionary');
        $buildDictionary->setAccessible(true);
        $dictionary = $buildDictionary->invoke($relation, $results);

        // User 1 should have 2 roles in dictionary
        $this->assertCount(2, $dictionary['http://example.org/user1']);

        // User 2 should have 1 role in dictionary
        $this->assertCount(1, $dictionary['http://example.org/user2']);
    }

    public function test_belongs_to_many_parse_ids_with_model(): void
    {
        $role = new BelongsToManyRoleModel;
        $role->id = 'http://example.org/role1';

        $relation = (new BelongsToManyUserModel)->roles();

        // Use reflection to access protected method
        $parseIds = new \ReflectionMethod($relation, 'parseIds');
        $parseIds->setAccessible(true);
        $result = $parseIds->invoke($relation, $role);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('http://example.org/role1', $result);
    }

    public function test_belongs_to_many_parse_ids_with_collection(): void
    {
        $role1 = new BelongsToManyRoleModel;
        $role1->id = 'http://example.org/role1';

        $role2 = new BelongsToManyRoleModel;
        $role2->id = 'http://example.org/role2';

        $collection = new Collection([$role1, $role2]);
        $relation = (new BelongsToManyUserModel)->roles();

        // Use reflection to access protected method
        $parseIds = new \ReflectionMethod($relation, 'parseIds');
        $parseIds->setAccessible(true);
        $result = $parseIds->invoke($relation, $collection);

        $this->assertIsArray($result);
        $this->assertContains('http://example.org/role1', $result);
        $this->assertContains('http://example.org/role2', $result);
    }

    public function test_belongs_to_many_parse_ids_with_array(): void
    {
        $ids = ['http://example.org/role1', 'http://example.org/role2'];
        $relation = (new BelongsToManyUserModel)->roles();

        // Use reflection to access protected method
        $parseIds = new \ReflectionMethod($relation, 'parseIds');
        $parseIds->setAccessible(true);
        $result = $parseIds->invoke($relation, $ids);

        $this->assertIsArray($result);
        $this->assertContains('http://example.org/role1', $result);
        $this->assertContains('http://example.org/role2', $result);
    }

    public function test_belongs_to_many_parse_ids_with_single_id(): void
    {
        $relation = (new BelongsToManyUserModel)->roles();

        // Use reflection to access protected method
        $parseIds = new \ReflectionMethod($relation, 'parseIds');
        $parseIds->setAccessible(true);
        $result = $parseIds->invoke($relation, 'http://example.org/role1');

        $this->assertIsArray($result);
        $this->assertContains('http://example.org/role1', $result);
    }

    public function test_belongs_to_many_cast_keys(): void
    {
        $relation = (new BelongsToManyUserModel)->roles();

        // Use reflection to access protected method
        $castKeys = new \ReflectionMethod($relation, 'castKeys');
        $castKeys->setAccessible(true);
        $result = $castKeys->invoke($relation, ['1', 'http://example.org/role1', '123']);

        $this->assertIsArray($result);
        $this->assertSame(1, $result[0]); // Numeric string becomes int
        $this->assertSame('http://example.org/role1', $result[1]); // Non-numeric stays string
        $this->assertSame(123, $result[2]); // Numeric string becomes int
    }

    public function test_belongs_to_many_get_qualified_foreign_pivot_key_name(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('ex:UserRole.ex:hasUser', $relation->getQualifiedForeignPivotKeyName());
    }

    public function test_belongs_to_many_get_qualified_related_pivot_key_name(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('ex:UserRole.ex:hasRole', $relation->getQualifiedRelatedPivotKeyName());
    }

    public function test_belongs_to_many_qualify_pivot_column(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        // Use reflection to access protected method
        $qualifyPivotColumn = new \ReflectionMethod($relation, 'qualifyPivotColumn');
        $qualifyPivotColumn->setAccessible(true);
        $result = $qualifyPivotColumn->invoke($relation, 'ex:priority');

        $this->assertEquals('ex:UserRole.ex:priority', $result);
    }

    public function test_belongs_to_many_base_attach_record(): void
    {
        $user = new BelongsToManyUserModel;
        $user->id = 'http://example.org/user1';

        $relation = $user->roles();

        // Use reflection to access protected method
        $baseAttachRecord = new \ReflectionMethod($relation, 'baseAttachRecord');
        $baseAttachRecord->setAccessible(true);
        $result = $baseAttachRecord->invoke($relation, 'http://example.org/role1', false);

        $this->assertIsArray($result);
        $this->assertInstanceOf(Expression::class, $result['ex:hasUser']);
        $this->assertInstanceOf(Expression::class, $result['ex:hasRole']);
        $this->assertEquals('<http://example.org/user1>', (string) $result['ex:hasUser']);
        $this->assertEquals('<http://example.org/role1>', (string) $result['ex:hasRole']);
    }

    public function test_belongs_to_many_get_existence_compare_key(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        $this->assertEquals('ex:UserRole.ex:hasUser', $relation->getExistenceCompareKey());
    }

    public function test_belongs_to_many_get_relation_name(): void
    {
        $user = new BelongsToManyUserModel;
        $relation = $user->roles();

        // The relation name is set by the HasRelationships trait when calling belongsToMany
        // It uses the guessBelongsToManyRelation() method to determine it
        $this->assertEquals('roles', $relation->getRelationName());
    }
}
