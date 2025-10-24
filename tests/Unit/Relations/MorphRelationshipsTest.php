<?php

namespace LinkedData\SPARQL\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Tests\TestCase;

// Test fixture models defined inline
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = ['id', 'title', 'body'];

    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}

class Video extends Model
{
    protected $table = 'videos';

    protected $fillable = ['id', 'title', 'url'];

    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}

class Image extends Model
{
    protected $table = 'images';

    protected $fillable = ['id', 'url', 'imageable_type', 'imageable_id'];

    public function imageable()
    {
        return $this->morphTo();
    }
}

class Comment extends Model
{
    protected $table = 'comments';

    protected $fillable = ['id', 'body', 'commentable_type', 'commentable_id'];

    public function commentable()
    {
        return $this->morphTo();
    }
}

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = ['id', 'name'];

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function videos()
    {
        return $this->morphedByMany(Video::class, 'taggable');
    }
}

class MorphRelationshipsTest extends TestCase
{
    /**
     * Test morphOne relationship can be defined
     *
     * @test
     */
    public function it_can_define_morph_one_relationship()
    {
        $post = new Post;
        $relation = $post->image();

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Relations\MorphOne::class, $relation);
        $this->assertEquals('imageable_type', $relation->getMorphType());
        $this->assertEquals('imageable_id', $relation->getForeignKeyName());
    }

    /**
     * Test morphMany relationship can be defined
     *
     * @test
     */
    public function it_can_define_morph_many_relationship()
    {
        $post = new Post;
        $relation = $post->comments();

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Relations\MorphMany::class, $relation);
        $this->assertEquals('commentable_type', $relation->getMorphType());
        $this->assertEquals('commentable_id', $relation->getForeignKeyName());
    }

    /**
     * Test morphTo relationship can be defined
     *
     * @test
     */
    public function it_can_define_morph_to_relationship()
    {
        $comment = new Comment(['commentable_type' => Post::class, 'commentable_id' => 'post-1']);
        $relation = $comment->commentable();

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Relations\MorphTo::class, $relation);
        $this->assertEquals('commentable_type', $relation->getMorphType());
        $this->assertEquals('commentable_id', $relation->getForeignKey());
    }

    /**
     * Test morphToMany relationship can be defined
     *
     * @test
     */
    public function it_can_define_morph_to_many_relationship()
    {
        $post = new Post;
        $relation = $post->tags();

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Relations\MorphToMany::class, $relation);
        $this->assertEquals('taggable_type', $relation->getMorphType());
        $this->assertFalse($relation->getInverse());
    }

    /**
     * Test morphedByMany (inverse morphToMany) relationship can be defined
     *
     * @test
     */
    public function it_can_define_morphed_by_many_relationship()
    {
        $tag = new Tag;
        $relation = $tag->posts();

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Relations\MorphToMany::class, $relation);
        $this->assertEquals('taggable_type', $relation->getMorphType());
        $this->assertTrue($relation->getInverse());
    }

    /**
     * Test morphOne returns correct morph class
     *
     * @test
     */
    public function morph_one_returns_correct_morph_class()
    {
        $post = new Post;
        $relation = $post->image();

        $this->assertEquals(Post::class, $relation->getMorphClass());
    }

    /**
     * Test morphMany returns correct morph class
     *
     * @test
     */
    public function morph_many_returns_correct_morph_class()
    {
        $video = new Video;
        $relation = $video->comments();

        $this->assertEquals(Video::class, $relation->getMorphClass());
    }

    /**
     * Test morphTo can associate models
     *
     * @test
     */
    public function morph_to_can_associate_models()
    {
        $comment = new Comment;
        $post = new Post(['id' => 'post-1']);

        $comment->commentable()->associate($post);

        $this->assertEquals('post-1', $comment->commentable_id);
        $this->assertEquals(Post::class, $comment->commentable_type);
        $this->assertSame($post, $comment->commentable);
    }

    /**
     * Test morphTo can dissociate models
     *
     * @test
     */
    public function morph_to_can_dissociate_models()
    {
        $comment = new Comment([
            'commentable_id' => 'post-1',
            'commentable_type' => Post::class,
        ]);
        $post = new Post(['id' => 'post-1']);
        $comment->setRelation('commentable', $post);

        $comment->commentable()->dissociate();

        $this->assertNull($comment->commentable_id);
        $this->assertNull($comment->commentable_type);
        $this->assertNull($comment->commentable);
    }

    /**
     * Test morphToMany with custom table name
     *
     * @test
     */
    public function morph_to_many_accepts_custom_table_name()
    {
        $post = new Post;
        $relation = $post->morphToMany(Tag::class, 'taggable', 'custom_taggables');

        $this->assertEquals('custom_taggables', $relation->getTable());
    }

    /**
     * Test morphToMany with custom pivot keys
     *
     * @test
     */
    public function morph_to_many_accepts_custom_pivot_keys()
    {
        $post = new Post;
        $relation = $post->morphToMany(
            Tag::class,
            'taggable',
            'taggables',
            'custom_foreign_key',
            'custom_related_key'
        );

        $this->assertEquals('custom_foreign_key', $relation->getForeignPivotKeyName());
        $this->assertEquals('custom_related_key', $relation->getRelatedPivotKeyName());
    }

    /**
     * Test morphOne initializes relation correctly
     *
     * @test
     */
    public function morph_one_initializes_relation_to_null()
    {
        $post1 = new Post(['id' => 'post-1']);
        $post2 = new Post(['id' => 'post-2']);

        $relation = $post1->image();
        $models = $relation->initRelation([$post1, $post2], 'image');

        $this->assertNull($models[0]->image);
        $this->assertNull($models[1]->image);
    }

    /**
     * Test morphMany initializes relation correctly
     *
     * @test
     */
    public function morph_many_initializes_relation_to_empty_collection()
    {
        $post1 = new Post(['id' => 'post-1']);
        $post2 = new Post(['id' => 'post-2']);

        $relation = $post1->comments();
        $models = $relation->initRelation([$post1, $post2], 'comments');

        $this->assertInstanceOf(Collection::class, $models[0]->comments);
        $this->assertInstanceOf(Collection::class, $models[1]->comments);
        $this->assertCount(0, $models[0]->comments);
        $this->assertCount(0, $models[1]->comments);
    }

    /**
     * Test morphTo initializes relation correctly
     *
     * @test
     */
    public function morph_to_initializes_relation_to_null()
    {
        $comment1 = new Comment(['id' => 'comment-1']);
        $comment2 = new Comment(['id' => 'comment-2']);

        $relation = $comment1->commentable();
        $models = $relation->initRelation([$comment1, $comment2], 'commentable');

        $this->assertNull($models[0]->commentable);
        $this->assertNull($models[1]->commentable);
    }
}
