<!--
SPDX-FileCopyrightText:  2020, Roberto Guido
SPDX-License-Identifier: CC-BY-3.0
-->

Laravel SPARQL
==============

An Eloquent model and Query builder with support for SPARQL, using the original Laravel API.

Heavily based on the original MIT licensed Illuminate Database package, Copyright (c) Taylor Otwell.

Installation
------------

Installation using composer:

```
composer require solid-data-workers/laravel-sparql
```

The service provider will register a `sparql` database extension with the original database manager. There is no need to register additional facades or objects. When using `sparql` connections, Laravel will automatically provide you with the corresponding `sparql` objects.

For usage outside Laravel, check out the [Capsule manager](https://github.com/illuminate/database/blob/master/README.md) and add:

```php
$capsule->getDatabaseManager()->extend('sparql', function($config, $name)
{
    $config['name'] = $name;

    return new SolidDataWorkers\SPARQL\Connection($config);
});
```

Configuration
-------------

Change your default database connection name in `config/database.php`:

```php
'default' => env('DB_CONNECTION', 'sparql'),
```

And add a new sparql connection:

```php
'sparql' => [
    'driver'     => 'sparql',

    /*
        The SPARQL endpoint
    */
    'host'       => env('DB_HOST', 'https://dbpedia.org/sparql'),

    /*
        Optional.
        Authentication credentials for the SPARQL endpoint.
        Useful to deal, for example, with a own writable Virtuoso server
    */
    'auth'       => [
        'type' => 'digest',
        'username' => 'your_username',
        'password' => 'your_password',
    ],

    /*
        It is optional, but highly reccomended, to define your RDF namespaces.
        If you don't, a set of default namespaces is used (the one from EasyRDF) but this widely slows down the initialization of the internal Introspector (see below) at least at the first execution.
        Many features about attributes access will not work properly for undefined namespaces.
    */
    'namespaces' => [
        'owl'    => 'http://www.w3.org/2002/07/owl#',
        'rdf'    => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs'   => 'http://www.w3.org/2000/01/rdf-schema#',
        'dbo'    => 'http://dbpedia.org/ontology/',
        'schema' => 'http://schema.org/',
        'foaf'   => 'http://xmlns.com/foaf/0.1/',
    ],

    /*
        Optional.
        If you have other local ontology files to be loaded into the Introspector (e.g. for the full DBPedia ontology), use "ontologies" in the configuration file
    */
    'ontologies' => [
        '/path/to/my/file.owl',
    ],

    /*
        You can also define the default graph name on which run queries
    */
    'graph'      => 'urn:my:named:graph',
],
```

Expressions
-----------

`SolidDataWorkers\SPARQL\Query\Expression` is an utility class to identify and properly wrap and handle strings of different type: literal strings, URNs, classes and more.

When passing a value to any function of the query builder, any plain string is converted to an Expression wrapping a plain string. To specify an exact type for the string, instanciate your own Expression such as:

```php
// Doesn't wraps the value within quotes or other
$e = new Expression('dbr:Philadelphia', 'literal');
// Explodes the class name and wraps it within angular brackets
$e = new Expression('foaf:Person', 'class');
```

Query Builder
-------------

The database driver plugs right into the original query builder. When using sparql connections, you will be able to build fluent queries to perform database operations. For your convenience, there is a `rdftype` alias for `table` as well as some additional SPARQL specific operators/operations.

```php
$people = DB::rdftype('foaf:Person')->get();

$people = DB::rdftype('foaf:Person')->where('foaf:gender', 'male')->first();
```

If you did not change your default database connection, you will need to specify it when querying.

```php
$people = DB::connection('sparql')->rdftype('foaf:Person')->get();
```

Read more about the query builder on http://laravel.com/docs/queries

Introspector
---------

The `Introspector` is an internal component used to keep a reference graph used to guess relations, datatypes for objects attributes, and provide some basic and transparent reasoning feature.

For each involved namespace it fetches the related RDF definition from the full URL (which may require some time), and the loaded graph is cached using the [native Cache from Laravel](https://laravel.com/docs/cache). When the list of namespaces changes in the configuration, the cached graph is automatically invalidated and regenerated.

If you want to manually invalidate the internal graph, just call

```php
Cache::forget('sparql_introspector_graph');
```

Eloquent
--------

This package includes a SPARQL enabled Eloquent class that you can use to define models for corresponding RDF classes.

By default, for each basic class (`owl:Class`, or those defined by the `basic_classes` configuration) found by the Introspector, a new PHP Model class is created, named after his shortened name, and then used by the Builder to instantiate the results fetched from the SPARQL endpoint, such as:

```php
namespace SolidDataWorkers\SPARQL\Eloquent;
use SolidDataWorkers\SPARQL\Eloquent\Model;

class ModelFoafPerson extends Model {
    protected $table = "foaf:Person";
}
```

To retrieve the Model class associated to each RDF class, call:

```php
$model = DB::getIntrospector()->getModel('dbo:Person');
$new_model = new $model();
```

You can create your own classes extending `SolidDataWorkers\SPARQL\Eloquent\Model` and specifying the mapped class through the `$table` attribute. Those classes will not be created by the Introspector. Anyway, remember that relations are already implicitly guessed by the Introspector and - on the contrary of vanilla Laravel's Eloquent - you don't need to define them manually in your Model.

```php
use SolidDataWorkers\SPARQL\Eloquent\Model;

class Person extends Model {
    protected $table = "foaf:Person";
}

class Place extends Model {
    protected $table = "http://dbpedia.org/ontology/PopulatedPlace";
}
```

Everything else should work just like the original Eloquent model. Well: I'm working on it... Read more about the Eloquent on http://laravel.com/docs/eloquent

Examples
--------

### Basic Usage

**Retrieving All Models**

```php
$people = Person::all();
```

**Retrieving A Record By Primary Key**

```php
$person = Person::find('http://dbpedia.org/resource/Al_Pacino');
```

**Handling Properties**

All properties are always encapsulated into a `Illuminate\Support\Collection` even when there is only a single value, so their behaviour is consistent across different properties types.

```php
$name = $person->offsetGet('http://xmlns.com/foaf/0.1/name');
$name = $person->offsetGet('foaf:name');
$name = $person->foaf_name;
```

The last example showcase a convenient shortcut in which namespace and property name are separated with an underscore instead of a colon. If a named attribute is not actually present into the Model instance, it tries anyway to get it dynamically from the SPARQL endpoint and returns `NULL` when nothing is found.

```php
$person->offsetSet('http://xmlns.com/foaf/0.1/name', 'Al');
$person->offsetSet('foaf:name', 'Al');
$person->foaf_name = 'Al';
```

Same for attribute setting: multiple ways to name them and assign a value.

```php
$place_name = $person->dbo_birthPlace;
```

Relations are automatically resolved by the Introspector, so accessing an attribute referencing another object it is automagically loaded into a Model and accessible through his own attributes. Please note that those relations are themselves properties, so are always returned into a `Illuminate\Support\Collection`.

**Wheres**

```php
$places = Place::where('dbo:areaTotal', '>', 10000000000)->get();
```

**And Statements**

```php
$places = Place::where('dbo:areaTotal', '>', 10000000000)->where('dbo:country', new Expression('dbr:Italy', 'literal'))->get();
```

**Or Statements (TODO)**

```php
$places = Place::where('dbo:areaTotal', '>', 10000000000)->orWhere('dbo:country', new Expression('dbr:Italy', 'literal'))->get();
```

**Using Where In With An Array**

```php
$people = Person::whereIn('dbo:birthPlace', [new Expression('dbr:New_York_City', 'literal'), new Expression('dbr:Philadelphia', 'literal')])->get();
```

**Using Where Between**

```php
$places = Place::whereBetween('dbo:areaTotal', [10000000000, 20000000000])->get();
```

**Where null**

This actually select elements for which an attribute is not set at all.

```php
$places = Place::whereNull('dbo:areaTotal')->get();
```

**Like**

Acts like the "regex" operator, removing placeholder characters.

```php
$people = Person::where('rdfs:label', 'like', '%Pacino')->get();
```

**Order By**

```php
$places = Place::orderBy('dbo:areaTotal', 'desc')->get();
```

**Distinct**

Distinct requires a field for which to return the distinct values.

```php
$places = Place::distinct()->get('dbo:country');
```

Distinct can be combined with **where**:

```php
$people = Person::where('dbo:birthPlace', new Expression('dbr:New_York_City', 'literal'))->distinct()->get('dbo:deathPlace');
```

**Advanced Wheres**

```php
$places = Place::where('dbo:areaTotal', '>', 10000000000)->orWhere(function($query)
    {
        $query->where('dbo:country', new Expression('dbr:Italy', 'literal'))
              ->where('dbo:areaTotal', '>', 10000000);
    })
    ->get();
```

**Group By**

Selected columns that are not grouped will be aggregated with the $last function.

```php
$people = Person::groupBy('dbo:birthPlace')->get(['rdfs:label']);
```

**Aggregation**

```php
$total = Place::count();
$max = Place::max('dbo:areaTotal');
$min = Place::min('dbo:areaTotal');
$avg = Place::avg('dbo:areaTotal');
$sum = Place::sum('dbo:areaTotal');
```

Aggregations can be combined with **where**:

```php
$sum = Place::where('dbo:country', new Expression('dbr:Italy', 'literal'))->sum('dbo:areaTotal');
```

### SPARQL specific operators

**Regex**

```php
$people = Person::where('rdfs:label', 'regex', 'Pacino')->get();
```
