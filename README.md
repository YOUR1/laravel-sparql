Laravel SPARQL
==============

An Eloquent model and Query builder with support for SPARQL, using the original Laravel API. *This library extends the original Laravel classes, so it uses exactly the same methods.*

Table of contents
-----------------
* [Installation](#installation)
* [Configuration](#configuration)
* [Eloquent](#eloquent)
* [Query Builder](#query-builder)
* [Examples](#examples)

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
    'host'       => env('DB_HOST', 'https://dbpedia.org/sparql'),
],
```

Optionally you can define custom RDF namespaces, which will be used in addiction to those defined by default. This is useful to use shortened URI (e.g. "dbr:New_York_City" instead of "http://dbpedia.org/resource/New_York_City"):

```php
'sparql' => [
    'driver'     => 'sparql',
    'host'       => env('DB_HOST', 'https://dbpedia.org/sparql'),
    'namespaces' => [
        'dbr' => 'http://dbpedia.org/resource/'
    ]
],
```

You can also define the default graph name on which run queries:

```php
'sparql' => [
    'driver'     => 'sparql',
    'host'       => env('DB_HOST', 'https://dbpedia.org/sparql'),
    'graph'      => 'urn:my:named:graph',
],
```

Eloquent
--------

This package includes a SPARQL enabled Eloquent class that you can use to define models for corresponding RDF classes.

```php
use SolidDataWorkers\SPARQL\Eloquent\Model as Eloquent;

class Person extends Eloquent {
    protected $table = "foaf:Person";
}

class Place extends Eloquent {
    protected $table = "http://dbpedia.org/ontology/PopulatedPlace";
}
```

Everything else (should) work just like the original Eloquent model. Read more about the Eloquent on http://laravel.com/docs/eloquent

You may also register an alias for the SPARQL model by adding the following to the alias array in `config/app.php`:

```php
'RDFModel'       => SolidDataWorkers\SPARQL\Eloquent\Model::class,
```

This will allow you to use the registered alias like:

```php
class MyModel extends RDFModel {}
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

```php
$name = $person->offsetGet('http://xmlns.com/foaf/0.1/name');
$name = $person->offsetGet('foaf:name');
$name = $person->foaf_name;
```

The last example showcase a shortcut in which namespace and property name are separated with an underscore instead of a colon.

```php
$person->offsetSet('http://xmlns.com/foaf/0.1/name', 'Al');
$person->offsetSet('foaf:name', 'Al');
$person->foaf_name = 'Al';
```

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

**Where null (TODO)**

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
