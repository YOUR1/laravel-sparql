<?php

namespace LinkedData\SPARQL\Tests\Fixtures;

use LinkedData\SPARQL\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = ['id', 'name'];

    /**
     * Get all of the posts that are assigned this tag (morphedByMany).
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    /**
     * Get all of the videos that are assigned this tag (morphedByMany).
     */
    public function videos()
    {
        return $this->morphedByMany(Video::class, 'taggable');
    }
}
