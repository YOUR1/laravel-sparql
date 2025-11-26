<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Eloquent\Relations\HasMany;
use LinkedData\SPARQL\Query\Expression;
use LinkedData\SPARQL\Tests\TestCase;

class AuthorModel extends Model
{
    protected $table = 'foaf:Person';

    protected $fillable = ['foaf:name'];

    public function posts()
    {
        return $this->hasMany(PostModel::class, 'foaf:author', 'id');
    }
}

class PostModel extends Model
{
    protected $table = 'foaf:Post';

    protected $fillable = ['foaf:title', 'foaf:content', 'foaf:author'];
}

class HasManyTest extends TestCase
{
    public function test_has_many_relation_can_be_defined(): void
    {
        $author = new AuthorModel;
        $relation = $author->posts();

        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_has_many_sets_foreign_key(): void
    {
        $author = new AuthorModel;
        $relation = $author->posts();

        $this->assertEquals('foaf:author', $relation->getForeignKeyName());
    }

    public function test_has_many_sets_local_key(): void
    {
        $author = new AuthorModel;
        $relation = $author->posts();

        $this->assertEquals('id', $relation->getLocalKeyName());
    }

    public function test_has_many_returns_related_model_type(): void
    {
        $author = new AuthorModel;
        $relation = $author->posts();

        $this->assertInstanceOf(PostModel::class, $relation->getRelated());
    }

    public function test_has_many_initializes_relation_with_empty_collection(): void
    {
        $models = [new AuthorModel, new AuthorModel];
        $relation = (new AuthorModel)->posts();

        $result = $relation->initRelation($models, 'posts');

        $this->assertInstanceOf(Collection::class, $result[0]->getRelation('posts'));
        $this->assertInstanceOf(Collection::class, $result[1]->getRelation('posts'));
        $this->assertCount(0, $result[0]->getRelation('posts'));
        $this->assertCount(0, $result[1]->getRelation('posts'));
    }

    public function test_has_many_match_uses_match_many(): void
    {
        // HasMany.match() should call matchMany
        $author = new AuthorModel;
        $relation = $author->posts();

        $this->assertInstanceOf(HasMany::class, $relation);

        // The match method returns the models array
        $models = [new AuthorModel, new AuthorModel];
        $results = new Collection;
        $matched = $relation->match($models, $results, 'posts');

        $this->assertIsArray($matched);
        $this->assertCount(2, $matched);
    }

    public function test_has_many_creates_new_related_instance(): void
    {
        $author = new AuthorModel;
        $author->id = 'http://example.org/author1';

        $relation = $author->posts();
        $post = $relation->make(['foaf:title' => 'Test Post']);

        $this->assertInstanceOf(PostModel::class, $post);
        $foreignKey = $post->getAttribute('foaf:author');
        $this->assertInstanceOf(Expression::class, $foreignKey);
        $this->assertEquals('<http://example.org/author1>', (string) $foreignKey);
    }

    public function test_has_many_gets_qualified_foreign_key_name(): void
    {
        $author = new AuthorModel;
        $relation = $author->posts();

        // In SPARQL, the qualified foreign key is just the foreign key name
        $this->assertEquals('foaf:author', $relation->getQualifiedForeignKeyName());
    }

    public function test_has_many_gets_qualified_parent_key_name(): void
    {
        $author = new AuthorModel;
        $relation = $author->posts();

        // SPARQL doesn't add table prefix
        $this->assertEquals('id', $relation->getQualifiedParentKeyName());
    }

    public function test_has_many_returns_empty_collection_when_parent_key_is_null(): void
    {
        $author = new AuthorModel;
        // Author has no ID set
        $relation = $author->posts();

        $result = $relation->getResults();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_has_many_saves_related_model(): void
    {
        $author = new AuthorModel;
        $author->id = 'http://example.org/author1';

        $post = new PostModel;
        $post->id = 'http://example.org/post1';
        $post->setAttribute('foaf:title', 'Test Post');

        $relation = $author->posts();

        // Since we can't actually save without a SPARQL endpoint,
        // we just verify the foreign key is set correctly
        $relation->setForeignAttributesForCreate($post);

        $foreignKey = $post->getAttribute('foaf:author');
        $this->assertInstanceOf(Expression::class, $foreignKey);
        $this->assertEquals('<http://example.org/author1>', (string) $foreignKey);
    }

    public function test_has_many_creates_related_model(): void
    {
        $author = new AuthorModel;
        $author->id = 'http://example.org/author1';

        $relation = $author->posts();

        // We can't actually create without a SPARQL endpoint,
        // but we can verify the make method works
        $post = $relation->make(['foaf:title' => 'New Post']);

        $this->assertInstanceOf(PostModel::class, $post);
        $this->assertEquals('New Post', $post->getAttribute('foaf:title'));
        $foreignKey = $post->getAttribute('foaf:author');
        $this->assertInstanceOf(Expression::class, $foreignKey);
        $this->assertEquals('<http://example.org/author1>', (string) $foreignKey);
    }

    public function test_has_many_gets_parent_key(): void
    {
        $author = new AuthorModel;
        $author->id = 'http://example.org/author1';

        $relation = $author->posts();

        $this->assertEquals('http://example.org/author1', $relation->getParentKey());
    }

    public function test_has_many_matches_eager_loaded_results(): void
    {
        // Create author models
        $author1 = new AuthorModel;
        $author1->id = 'http://example.org/author1';
        $author1->setAttribute('foaf:name', 'Author 1');

        $author2 = new AuthorModel;
        $author2->id = 'http://example.org/author2';
        $author2->setAttribute('foaf:name', 'Author 2');

        // Create post models with proper foreign key structure
        // In SPARQL, the buildDictionary expects posts to have author as an attribute (Collection)
        $post1 = new PostModel;
        $post1->id = 'http://example.org/post1';
        $post1->setAttribute('foaf:title', 'Post 1');
        // Simulate the processor's mapping: set author as an attribute Collection
        $post1->setAttribute('foaf:author', collect([$author1]));

        $post2 = new PostModel;
        $post2->id = 'http://example.org/post2';
        $post2->setAttribute('foaf:title', 'Post 2');
        $post2->setAttribute('foaf:author', collect([$author2]));

        $post3 = new PostModel;
        $post3->id = 'http://example.org/post3';
        $post3->setAttribute('foaf:title', 'Post 3');
        $post3->setAttribute('foaf:author', collect([$author1]));

        // Match results
        $models = [$author1, $author2];
        $results = new Collection([$post1, $post2, $post3]);
        $relation = (new AuthorModel)->posts();

        $matched = $relation->match($models, $results, 'posts');

        // Author 1 should have posts 1 and 3
        $this->assertInstanceOf(Collection::class, $matched[0]->getRelation('posts'));
        $this->assertCount(2, $matched[0]->getRelation('posts'));

        // Author 2 should have post 2
        $this->assertInstanceOf(Collection::class, $matched[1]->getRelation('posts'));
        $this->assertCount(1, $matched[1]->getRelation('posts'));
    }

    public function test_has_many_find_or_new_creates_instance_with_foreign_key(): void
    {
        $author = new AuthorModel;
        $author->id = 'http://example.org/author1';

        $relation = $author->posts();

        // We can't test the full flow without a SPARQL endpoint,
        // but we can test that make creates properly structured instances
        $instance = $relation->make(['foaf:title' => 'Test']);

        $this->assertInstanceOf(PostModel::class, $instance);
        $foreignKey = $instance->getAttribute('foaf:author');
        $this->assertInstanceOf(Expression::class, $foreignKey);
        $this->assertEquals('<http://example.org/author1>', (string) $foreignKey);
    }

    public function test_has_many_save_many_sets_foreign_keys(): void
    {
        $author = new AuthorModel;
        $author->id = 'http://example.org/author1';

        $post1 = new PostModel;
        $post1->setAttribute('foaf:title', 'Post 1');

        $post2 = new PostModel;
        $post2->setAttribute('foaf:title', 'Post 2');

        $relation = $author->posts();

        // Set foreign keys on both posts
        $relation->setForeignAttributesForCreate($post1);
        $relation->setForeignAttributesForCreate($post2);

        $foreignKey1 = $post1->getAttribute('foaf:author');
        $foreignKey2 = $post2->getAttribute('foaf:author');
        $this->assertInstanceOf(Expression::class, $foreignKey1);
        $this->assertInstanceOf(Expression::class, $foreignKey2);
        $this->assertEquals('<http://example.org/author1>', (string) $foreignKey1);
        $this->assertEquals('<http://example.org/author1>', (string) $foreignKey2);
    }

    public function test_has_many_builds_dictionary_correctly(): void
    {
        // Create author models
        $author1 = new AuthorModel;
        $author1->id = 'http://example.org/author1';

        $author2 = new AuthorModel;
        $author2->id = 'http://example.org/author2';

        // Create posts with proper attribute structure
        $post1 = new PostModel;
        $post1->id = 'http://example.org/post1';
        $post1->setAttribute('foaf:author', collect([$author1]));

        $post2 = new PostModel;
        $post2->id = 'http://example.org/post2';
        $post2->setAttribute('foaf:author', collect([$author1]));

        $post3 = new PostModel;
        $post3->id = 'http://example.org/post3';
        $post3->setAttribute('foaf:author', collect([$author2]));

        $results = new Collection([$post1, $post2, $post3]);

        // Use reflection to test the protected buildDictionary method
        $relation = $author1->posts();
        $reflection = new \ReflectionClass($relation);
        $method = $reflection->getMethod('buildDictionary');
        $method->setAccessible(true);

        $dictionary = $method->invoke($relation, $results);

        // Dictionary should be keyed by author ID
        $this->assertArrayHasKey('http://example.org/author1', $dictionary);
        $this->assertArrayHasKey('http://example.org/author2', $dictionary);

        // Author 1 should have 2 posts
        $this->assertCount(2, $dictionary['http://example.org/author1']);

        // Author 2 should have 1 post
        $this->assertCount(1, $dictionary['http://example.org/author2']);

        // Verify the posts had the foreign key attribute unset
        // After buildDictionary, the attribute should be completely removed
        $attributes = $post1->getAttributes();
        $this->assertArrayNotHasKey('foaf:author', $attributes);
    }

    public function test_has_many_get_existence_compare_key(): void
    {
        $author = new AuthorModel;
        $relation = $author->posts();

        // For HasMany, the existence compare key is the qualified foreign key
        $this->assertEquals('foaf:author', $relation->getExistenceCompareKey());
    }

    public function test_has_many_match_one_returns_single_model(): void
    {
        // Create author model
        $author = new AuthorModel;
        $author->id = 'http://example.org/author1';

        // Create post with proper attribute structure
        $post = new PostModel;
        $post->id = 'http://example.org/post1';
        $post->setAttribute('foaf:author', collect([$author]));

        $models = [$author];
        $results = new Collection([$post]);
        $relation = $author->posts();

        // Test matchOne specifically
        $matched = $relation->matchOne($models, $results, 'post');

        // Should return a single model, not a collection
        $this->assertInstanceOf(PostModel::class, $matched[0]->getRelation('post'));
    }
}
