<?php

namespace LinkedData\SPARQL;

use Illuminate\Support\ServiceProvider;
use LinkedData\SPARQL\Eloquent\Model;

class SPARQLEndpointServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    public function register(): void
    {
        $this->app->resolving('db', function ($db) {
            $db->extend('sparql', function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });
        });
    }
}
