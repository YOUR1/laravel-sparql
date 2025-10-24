<?php

namespace LinkedData\SPARQL\Tests\Fixtures;

use LinkedData\SPARQL\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = ['id', 'name', 'email'];
}
