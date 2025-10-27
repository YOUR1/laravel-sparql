<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use LinkedData\SPARQL\Eloquent\Builder;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Query\Builder as QueryBuilder;
use LinkedData\SPARQL\Query\Expression;
use LinkedData\SPARQL\Tests\TestCase;

class TestUser extends Model
{
    protected $table = 'foaf:Person';

    public function posts()
    {
        return $this->hasMany(TestPost::class, 'author', 'id');
    }

    public function comments()
    {
        return $this->hasMany(TestComment::class, 'author', 'id');
    }
}

class TestPost extends Model
{
    protected $table = 'schema:BlogPosting';
}

class TestComment extends Model
{
    protected $table = 'schema:Comment';
}

class QueriesRelationshipsTest extends TestCase
{
    protected function getMockQueryBuilder()
    {
        $connection = $this->getMockBuilder('LinkedData\SPARQL\Connection')
            ->disableOriginalConstructor()
            ->getMock();

        $grammar = new \LinkedData\SPARQL\Query\Grammar;

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$connection, $grammar])
            ->onlyMethods(['from'])
            ->getMock();

        $queryBuilder->unique_subject = Expression::raw('?s');

        return $queryBuilder;
    }

    protected function getEloquentBuilder($model = null)
    {
        $model = $model ?: new TestUser;
        $queryBuilder = $this->getMockQueryBuilder();
        $builder = new Builder($queryBuilder);
        $builder->setModel($model);

        return $builder;
    }

    /** @test */
    public function test_has_adds_relationship_constraint()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->has('posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_has_with_callback()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->has('posts', '>=', 1, 'and', function ($query) {
            $query->where('published', '=', true);
        });

        $this->assertInstanceOf(Builder::class, $result);
    }

    /** @test */
    public function test_or_has()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->orHas('posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_doesnt_have()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->doesntHave('posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_or_doesnt_have()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->orDoesntHave('posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_where_has()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->whereHas('posts', function ($query) {
            $query->where('published', '=', true);
        });

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_where_has_with_operator_and_count()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->whereHas('posts', function ($query) {
            $query->where('status', '=', 'published');
        }, '>=', 5);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_or_where_has()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->orWhereHas('posts', function ($query) {
            $query->where('featured', '=', true);
        });

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_where_doesnt_have()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->whereDoesntHave('posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_where_doesnt_have_with_callback()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->whereDoesntHave('posts', function ($query) {
            $query->where('draft', '=', true);
        });

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_or_where_doesnt_have()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->orWhereDoesntHave('posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /**
     * @test
     *
     * @skip Needs proper grammar setup for selectSub
     */
    public function test_with_count_adds_count_column()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->withCount('posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /**
     * @test
     *
     * @skip Needs proper grammar setup for selectSub
     */
    public function test_with_count_with_array()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->withCount(['posts', 'comments']);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /**
     * @test
     *
     * @skip Needs proper grammar setup for selectSub
     */
    public function test_with_count_with_callback()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->withCount([
            'posts' => function ($query) {
                $query->where('published', '=', true);
            },
        ]);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /**
     * @test
     *
     * @skip Needs proper grammar setup for selectSub
     */
    public function test_with_count_with_alias()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->withCount('posts as total_posts');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_with_count_empty_relations_returns_same_builder()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder->withCount([]);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_chaining_multiple_has_methods()
    {
        $builder = $this->getEloquentBuilder();
        $result = $builder
            ->has('posts')
            ->whereHas('comments', function ($query) {
                $query->where('approved', '=', true);
            })
            ->doesntHave('suspensions');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }

    /** @test */
    public function test_can_use_exists_for_existence_check()
    {
        $builder = $this->getEloquentBuilder();
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('canUseExistsForExistenceCheck');

        // Should return true for >= 1
        $this->assertTrue($method->invoke($builder, '>=', 1));

        // Should return true for < 1
        $this->assertTrue($method->invoke($builder, '<', 1));

        // Should return false for other operators/counts
        $this->assertFalse($method->invoke($builder, '>', 1));
        $this->assertFalse($method->invoke($builder, '>=', 2));
        $this->assertFalse($method->invoke($builder, '<', 2));
        $this->assertFalse($method->invoke($builder, '=', 1));
    }

    /** @test */
    public function test_merge_constraints_from()
    {
        $builder = $this->getEloquentBuilder();
        $from = $this->getEloquentBuilder();

        $result = $builder->mergeConstraintsFrom($from);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame($builder, $result);
    }
}
