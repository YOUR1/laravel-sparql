<?php

namespace LinkedData\SPARQL\Tests\Fixtures;

use LinkedData\SPARQL\Eloquent\Model;

class Comment extends Model
{
    protected $table = 'comments';

    protected $fillable = ['id', 'body', 'commentable_type', 'commentable_id'];

    /**
     * Get the parent commentable model (post or video).
     */
    public function commentable()
    {
        return $this->morphTo();
    }
}
