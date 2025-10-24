<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Eloquent\Relations\HasManyThrough;
use LinkedData\SPARQL\Tests\TestCase;

class Country extends Model
{
    protected $table = 'geo:Country';

    protected $fillable = ['geo:name'];

    public function posts()
    {
        return $this->hasManyThrough(
            BlogPost::class,
            User::class,
            'geo:country',      // Foreign key on users table
            'schema:author',    // Foreign key on posts table
            'id',               // Local key on countries table
            'id'                // Local key on users table
        );
    }
}

class User extends Model
{
    protected $table = 'foaf:Person';

    protected $fillable = ['foaf:name', 'geo:country'];
}

class BlogPost extends Model
{
    protected $table = 'schema:BlogPosting';

    protected $fillable = ['schema:headline', 'schema:author'];
}

class HasManyThroughTest extends TestCase
{
    public function test_has_many_through_relation_can_be_defined(): void
    {

        // Test if first static call is slow
        Country::hydrate([]);

        $country = new Country;
        $relation = $country->posts();

        $this->assertInstanceOf(HasManyThrough::class, $relation);
    }

    public function test_has_many_through_sets_keys(): void
    {
        $country = new Country;
        $relation = $country->posts();

        $this->assertEquals('geo:country', $relation->getFirstKeyName());
        $this->assertEquals('schema:author', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getLocalKeyName());
        $this->assertEquals('id', $relation->getSecondLocalKeyName());
    }

    public function test_has_many_through_returns_related_model_type(): void
    {
        $country = new Country;
        $relation = $country->posts();

        // Test if creating BlogPost is slow
        $testPost = new BlogPost;

        // Test if hydrate is slow

        $result = BlogPost::hydrate([]);

        $this->assertInstanceOf(BlogPost::class, $relation->getRelated());
    }

    public function test_has_many_through_initializes_relation_with_empty_collection(): void
    {
        $country1 = new Country;
        $country1->id = 'urn:country:1';

        $country2 = new Country;
        $country2->id = 'urn:country:2';

        $models = [$country1, $country2];

        $relation = $country1->posts();
        $result = $relation->initRelation($models, 'posts');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Collection::class, $result[0]->posts);
        $this->assertInstanceOf(Collection::class, $result[1]->posts);
        $this->assertCount(0, $result[0]->posts);
        $this->assertCount(0, $result[1]->posts);
    }

    public function test_has_many_through_gets_results(): void
    {
        $country = new Country;
        $country->id = 'urn:country:1';

        $relation = $country->posts();
        $results = $relation->getResults();

        $this->assertInstanceOf(Collection::class, $results);
    }

    public function test_has_many_through_returns_empty_collection_when_parent_key_is_null(): void
    {
        $country = new Country;
        $country->id = null;

        $relation = $country->posts();
        $results = $relation->getResults();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_has_many_through_matches_eager_loaded_results(): void
    {
        $country1 = new Country;
        $country1->id = 'urn:country:1';

        $country2 = new Country;
        $country2->id = 'urn:country:2';

        $post1 = new BlogPost;
        $post1->id = 'urn:post:1';
        $post1->{'geo:country'} = collect(['urn:country:1']);

        $post2 = new BlogPost;
        $post2->id = 'urn:post:2';
        $post2->{'geo:country'} = collect(['urn:country:1']);

        $post3 = new BlogPost;
        $post3->id = 'urn:post:3';
        $post3->{'geo:country'} = collect(['urn:country:2']);

        $models = [$country1, $country2];
        $results = new Collection([$post1, $post2, $post3]);

        $relation = $country1->posts();
        $relation->initRelation($models, 'posts');
        $matched = $relation->match($models, $results, 'posts');

        $this->assertCount(2, $matched);
        $this->assertCount(2, $matched[0]->posts);
        $this->assertCount(1, $matched[1]->posts);
    }

    public function test_has_many_through_get_existence_compare_key(): void
    {
        $country = new Country;
        $relation = $country->posts();

        $key = $relation->getExistenceCompareKey();

        $this->assertIsString($key);
        $this->assertEquals('id', $key);
    }

    public function test_has_many_through_get_qualified_far_key_name(): void
    {
        $country = new Country;
        $relation = $country->posts();

        $key = $relation->getQualifiedFarKeyName();

        $this->assertEquals('id', $key);
    }

    public function test_has_many_through_get_qualified_first_key_name(): void
    {
        $country = new Country;
        $relation = $country->posts();

        $key = $relation->getQualifiedFirstKeyName();

        $this->assertEquals('geo:country', $key);
    }
}
