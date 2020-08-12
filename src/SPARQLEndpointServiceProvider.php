<?php

/*
SPDX-FileCopyrightText: 2020, Roberto Guido
SPDX-License-Identifier: 
*/

namespace SolidDataWorkers\SPARQL;

use Illuminate\Support\ServiceProvider;
use SolidDataWorkers\SPARQL\Eloquent\Model;

class SPARQLEndpointServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    public function register()
    {
        $this->app->resolving('db', function ($db) {
            $db->extend('sparql', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });
    }
}
