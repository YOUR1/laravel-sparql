<?php

namespace LinkedData\SPARQL\Tests\Fixtures;

use LinkedData\SPARQL\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = ['id', 'title', 'body'];

    /**
     * Get the post's image (morphOne).
     */
    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    /**
     * Get all of the post's comments (morphMany).
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get all of the tags for the post (morphToMany).
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
