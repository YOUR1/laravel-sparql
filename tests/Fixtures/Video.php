<?php

namespace LinkedData\SPARQL\Tests\Fixtures;

use LinkedData\SPARQL\Eloquent\Model;

class Video extends Model
{
    protected $table = 'videos';

    protected $fillable = ['id', 'title', 'url'];

    /**
     * Get the video's image (morphOne).
     */
    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    /**
     * Get all of the video's comments (morphMany).
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get all of the tags for the video (morphToMany).
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
