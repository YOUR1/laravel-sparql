<?php

namespace LinkedData\SPARQL\Tests\Unit;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use LinkedData\SPARQL\Connection;
use LinkedData\SPARQL\Eloquent\Builder;
use LinkedData\SPARQL\Query\Builder as QueryBuilder;
use LinkedData\SPARQL\Tests\Fixtures\User;
use LinkedData\SPARQL\Tests\TestCase;
use Mockery as m;

class EloquentBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_builder_can_be_constructed_with_query_builder(): void
    {
        $connection = $this->createConnection();
        $queryBuilder = new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
        $builder = new Builder($queryBuilder);

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertSame($queryBuilder, $builder->getQuery());
    }

    public function test_set_model_sets_model_and_table(): void
    {
        $model = new User;
        $builder = $this->getBuilder();
        $builder->setModel($model);

        $this->assertSame($model, $builder->getModel());
        $this->assertEquals('users', $builder->getQuery()->from);
    }

    public function test_make_creates_new_model_instance(): void
    {
        $builder = $this->getUserBuilder();
        $model = $builder->make(['name' => 'John Doe']);

        $this->assertInstanceOf(User::class, $model);
        $this->assertEquals('John Doe', $model->name);
        $this->assertFalse($model->exists);
    }

    public function test_where_key_adds_where_clause_for_single_id(): void
    {
        $builder = $this->getUserBuilder();
        $builder->whereKey('http://example.com/user/1');

        $wheres = $builder->getQuery()->wheres;
        $this->assertCount(1, $wheres);
    }

    public function test_where_key_adds_where_in_clause_for_array_ids(): void
    {
        $builder = $this->getUserBuilder();
        $builder->whereKey(['http://example.com/user/1', 'http://example.com/user/2']);

        $wheres = $builder->getQuery()->wheres;
        $this->assertCount(1, $wheres);
    }

    public function test_where_key_not_adds_not_equal_clause(): void
    {
        $builder = $this->getUserBuilder();
        $builder->whereKeyNot('http://example.com/user/1');

        $wheres = $builder->getQuery()->wheres;
        $this->assertNotEmpty($wheres);
    }

    public function test_find_or_fail_throws_exception_when_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $builder = $this->getUserBuilder();
        $this->mockConnection($builder);

        $builder->findOrFail('http://example.com/nonexistent');
    }

    public function test_with_sets_eager_load_relationships(): void
    {
        $builder = $this->getUserBuilder();
        $builder->with('posts');

        $eagerLoads = $builder->getEagerLoads();
        $this->assertArrayHasKey('posts', $eagerLoads);
    }

    public function test_with_accepts_array_of_relationships(): void
    {
        $builder = $this->getUserBuilder();
        $builder->with(['posts', 'comments']);

        $eagerLoads = $builder->getEagerLoads();
        $this->assertArrayHasKey('posts', $eagerLoads);
        $this->assertArrayHasKey('comments', $eagerLoads);
    }

    public function test_without_removes_eager_load_relationships(): void
    {
        $builder = $this->getUserBuilder();
        $builder->with(['posts', 'comments']);
        $builder->without('posts');

        $eagerLoads = $builder->getEagerLoads();
        $this->assertArrayNotHasKey('posts', $eagerLoads);
        $this->assertArrayHasKey('comments', $eagerLoads);
    }

    public function test_set_eager_loads_replaces_all_relationships(): void
    {
        $builder = $this->getUserBuilder();
        $builder->with('posts');

        $newEagerLoads = ['comments' => function () {}];
        $builder->setEagerLoads($newEagerLoads);

        $this->assertEquals($newEagerLoads, $builder->getEagerLoads());
    }

    public function test_with_global_scope_registers_scope(): void
    {
        $builder = $this->getUserBuilder();
        $scope = function ($builder) {
            $builder->where('active', true);
        };

        $builder->withGlobalScope('active', $scope);

        $this->assertNotEmpty($builder->toBase()->wheres);
    }

    public function test_without_global_scope_removes_scope(): void
    {
        $builder = $this->getUserBuilder();
        $scope = function ($builder) {
            $builder->where('active', true);
        };

        $builder->withGlobalScope('active', $scope);
        $builder->withoutGlobalScope('active');

        $removedScopes = $builder->removedScopes();
        $this->assertContains('active', $removedScopes);
    }

    public function test_without_global_scopes_removes_all_scopes(): void
    {
        $builder = $this->getUserBuilder();

        $builder->withGlobalScope('active', function ($builder) {
            $builder->where('active', true);
        });
        $builder->withGlobalScope('verified', function ($builder) {
            $builder->where('verified', true);
        });

        $builder->withoutGlobalScopes(['active', 'verified']);

        $removedScopes = $builder->removedScopes();
        $this->assertContains('active', $removedScopes);
        $this->assertContains('verified', $removedScopes);
    }

    public function test_qualify_column_uses_model_qualification(): void
    {
        $builder = $this->getUserBuilder();

        $qualified = $builder->qualifyColumn('name');

        $this->assertStringContainsString('name', $qualified);
    }

    public function test_new_model_instance_creates_model_with_attributes(): void
    {
        $builder = $this->getUserBuilder();

        $model = $builder->newModelInstance(['name' => 'Jane']);

        $this->assertInstanceOf(User::class, $model);
        $this->assertEquals('Jane', $model->name);
    }

    public function test_to_base_returns_query_builder(): void
    {
        $builder = $this->getUserBuilder();

        $queryBuilder = $builder->toBase();

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    public function test_get_query_returns_underlying_query(): void
    {
        $connection = $this->createConnection();
        $queryBuilder = new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
        $builder = new Builder($queryBuilder);

        $this->assertSame($queryBuilder, $builder->getQuery());
    }

    public function test_set_query_replaces_query_builder(): void
    {
        $builder = $this->getUserBuilder();
        $connection = $this->createConnection();
        $newQuery = new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());

        $builder->setQuery($newQuery);

        $this->assertSame($newQuery, $builder->getQuery());
    }

    public function test_hydrate_creates_collection_from_arrays(): void
    {
        $builder = $this->getUserBuilder();

        $items = [
            ['id' => 'http://example.com/user/1', 'name' => 'John'],
            ['id' => 'http://example.com/user/2', 'name' => 'Jane'],
        ];

        $collection = $builder->hydrate($items);

        $this->assertCount(2, $collection);
        $this->assertInstanceOf(User::class, $collection->first());
    }

    public function test_clone_clones_query_builder(): void
    {
        $builder = $this->getUserBuilder();
        $cloned = clone $builder;

        $this->assertNotSame($builder->getQuery(), $cloned->getQuery());
    }

    public function test_builder_forwards_calls_to_query_builder(): void
    {
        $builder = $this->getUserBuilder();

        $result = $builder->where('name', 'John');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertNotEmpty($builder->getQuery()->wheres);
    }

    protected function createConnection(): Connection
    {
        $config = [
            'driver' => 'sparql',
            'host' => 'http://localhost:3030/test/sparql',
            'update_endpoint' => 'http://localhost:3030/test/update',
        ];

        return new Connection($config);
    }

    protected function getBuilder(): Builder
    {
        $connection = $this->createConnection();
        $queryBuilder = new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());

        return new Builder($queryBuilder);
    }

    protected function getUserBuilder(): Builder
    {
        $builder = $this->getBuilder();
        $model = new User;
        $builder->setModel($model);

        return $builder;
    }

    protected function mockConnection(Builder $builder): void
    {
        // Mock the query builder to return empty results
        $queryBuilder = m::mock(QueryBuilder::class)->makePartial();
        $queryBuilder->shouldReceive('get')->andReturn(collect([]));
        $builder->setQuery($queryBuilder);
    }
}
