<?php

namespace LinkedData\SPARQL\Tests\Fixtures;

use LinkedData\SPARQL\Eloquent\Model;

class Image extends Model
{
    protected $table = 'images';

    protected $fillable = ['id', 'url', 'imageable_type', 'imageable_id'];

    /**
     * Get the parent imageable model (post or video).
     */
    public function imageable()
    {
        return $this->morphTo();
    }
}
