# API Reference

Complete API reference for Laravel SPARQL v1.0.

## Table of Contents

- [Model API](#model-api)
- [Query Builder API](#query-builder-api)
- [Expression API](#expression-api)
- [Connection API](#connection-api)
- [SyncsToSparql Trait](#syncstos parql-trait)
- [GenericResource API](#genericresource-api)

## Model API

### LinkedData\SPARQL\Eloquent\Model

Base model class for SPARQL resources.

#### Properties

```php
// RDF class (maps to rdf:type)
protected $table = 'http://schema.org/Person';

// Blazegraph namespace (optional)
protected $namespace = null;

// Property URI mappings
protected $propertyUris = [
    'name' => 'http://schema.org/name',
    'email' => 'http://schema.org/email',
];

// Primary key (always 'id' for URIs)
protected $primaryKey = 'id';
public $incrementing = false;
protected $keyType = 'string';

// Disable timestamps by default
public $timestamps = false;

// Standard Eloquent properties work
protected $fillable = [];
protected $guarded = [];
protected $casts = [];
protected $hidden = [];
protected $visible = [];
protected $appends = [];
```

#### Methods

##### getAttribute($key)
Get an attribute value.

```php
public function getAttribute($key): mixed

// Usage
$name = $person->getAttribute('name');
$name = $person->name;  // Magic access
```

##### setAttribute($key, $value)
Set an attribute value.

```php
public function setAttribute($key, $value): static

// Usage
$person->setAttribute('name', 'John');
$person->name = 'John';  // Magic access
```

##### setAttributeWithLang($key, $value, $lang)
Set a language-tagged literal.

```php
public function setAttributeWithLang(string $key, $value, ?string $lang = null): static

// Usage
$book->setAttributeWithLang('title', 'Le Grand Gatsby', 'fr');
```

##### addPropertyValue($key, $value, $lang, $datatype)
Add a value to a multi-valued property.

```php
public function addPropertyValue(string $key, $value, ?string $lang = null, ?string $datatype = null): static

// Usage
$person->addPropertyValue('email', 'john@work.com');
$person->addPropertyValue('email', 'john@personal.com');
```

##### getUri()
Get the resource URI.

```php
public function getUri(): ?string

// Usage
$uri = $person->getUri();
```

##### setUri($uri)
Set the resource URI.

```php
public function setUri(string $uri): static

// Usage
$person->setUri('http://example.com/person/1');
```

##### setRdfClass($rdfClass)
Set the RDF class dynamically.

```php
public function setRdfClass(string $rdfClass): static

// Usage
$resource->setRdfClass('http://schema.org/Product');
```

##### setNamespace($namespace)
Set the Blazegraph namespace for this model.

```php
public function setNamespace(string $namespace): static

// Usage
$model->setNamespace('tenant_X_ds_Y');
```

##### getNamespace()
Get the Blazegraph namespace for this model.

```php
public function getNamespace(): ?string

// Usage
$namespace = $model->getNamespace();
```

##### save($options = [])
Save the model to the SPARQL store.

```php
public function save(array $options = []): bool

// Usage
$person->name = 'John';
$person->save();
```

##### delete()
Delete the model from the SPARQL store.

```php
public function delete(): bool

// Usage
$person->delete();
```

##### fill($attributes)
Mass assign attributes.

```php
public function fill(array $attributes): static

// Usage
$person->fill(['name' => 'John', 'age' => 30]);
```

#### Static Methods

##### find($id, $columns = ['*'])
Find a model by URI.

```php
public static function find($id, $columns = ['*']): ?static

// Usage
$person = Person::find('http://example.com/person/1');
```

##### findOrFail($id, $columns = ['*'])
Find a model by URI or throw exception.

```php
public static function findOrFail($id, $columns = ['*']): static

// Usage
$person = Person::findOrFail('http://example.com/person/1');
```

##### all($columns = ['*'])
Get all models.

```php
public static function all($columns = ['*']): Collection

// Usage
$people = Person::all();
```

##### create($attributes = [])
Create and save a new model.

```php
public static function create(array $attributes = []): static

// Usage
$person = Person::create(['id' => 'http://example.com/person/1', 'name' => 'John']);
```

##### insertBatch($models)
Batch insert multiple models.

```php
public static function insertBatch(array $models): bool

// Usage
Person::insertBatch($peopleArray);
```

##### deleteBatch($uris)
Batch delete by URIs.

```php
public static function deleteBatch(array $uris): int

// Usage
Person::deleteBatch(['http://example.com/person/1', 'http://example.com/person/2']);
```

##### where($column, $operator, $value)
Begin a query with where clause.

```php
public static function where($column, $operator = null, $value = null): Builder

// Usage
$adults = Person::where('age', '>', 18)->get();
```

## Query Builder API

### LinkedData\SPARQL\Eloquent\Builder

Query builder for SPARQL queries.

#### Namespace Methods

##### namespace($namespace)
Set the Blazegraph namespace for this query.

```php
public function namespace(string $namespace): static

// Usage
$query = DB::connection('sparql')
    ->namespace('tenant_X_ds_Y')
    ->table('http://schema.org/Person')
    ->where('age', '>', 18);
```

##### getNamespace()
Get the Blazegraph namespace for this query.

```php
public function getNamespace(): ?string

// Usage
$namespace = $query->getNamespace();
```

#### Query Constraints

##### where($column, $operator, $value)
```php
public function where($column, $operator = null, $value = null): static
```

##### orWhere($column, $operator, $value)
```php
public function orWhere($column, $operator = null, $value = null): static
```

##### whereIn($column, $values)
```php
public function whereIn($column, array $values): static
```

##### whereNotIn($column, $values)
```php
public function whereNotIn($column, array $values): static
```

##### whereBetween($column, $values)
```php
public function whereBetween($column, array $values): static
```

##### whereNull($column)
```php
public function whereNull($column): static
```

##### whereNotNull($column)
```php
public function whereNotNull($column): static
```

#### Ordering & Limiting

##### orderBy($column, $direction = 'asc')
```php
public function orderBy($column, $direction = 'asc'): static

// Usage
$people = Person::orderBy('age', 'desc')->get();
```

##### limit($value)
```php
public function limit($value): static
```

##### offset($value)
```php
public function offset($value): static
```

##### take($value)
Alias for limit.

```php
public function take($value): static
```

##### skip($value)
Alias for offset.

```php
public function skip($value): static
```

#### Retrieving Results

##### get($columns = ['*'])
```php
public function get($columns = ['*']): Collection
```

##### first($columns = ['*'])
```php
public function first($columns = ['*']): ?Model
```

##### paginate($perPage, $columns, $pageName, $page)
```php
public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
```

##### simplePaginate($perPage, $columns, $pageName, $page)
```php
public function simplePaginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): Paginator
```

#### Aggregates

##### count($columns = '*')
```php
public function count($columns = '*'): int
```

##### max($column)
```php
public function max($column): mixed
```

##### min($column)
```php
public function min($column): mixed
```

##### avg($column)
```php
public function avg($column): mixed
```

##### sum($column)
```php
public function sum($column): mixed
```

##### exists()
```php
public function exists(): bool
```

#### Batch Operations

##### insertBatch($records)
```php
public function insertBatch(array $records): bool

// Usage
Person::query()->insertBatch($peopleArray);
```

##### deleteBatch($uris)
```php
public function deleteBatch(array $uris): int

// Usage
Person::query()->deleteBatch($uriArray);
```

## Expression API

### LinkedData\SPARQL\Query\Expression

Utility class for handling SPARQL values.

#### Static Methods

##### raw($value)
Create a raw expression (no wrapping).

```php
public static function raw($value): Expression

// Usage
$expr = Expression::raw('dbr:Philadelphia');
```

##### iri($value)
Create an IRI/URI expression.

```php
public static function iri($value): Expression

// Usage
$expr = Expression::iri('http://example.com/person/1');
```

##### cls($value)
Create a class expression.

```php
public static function cls($value): Expression

// Usage
$expr = Expression::cls('foaf:Person');
```

##### literal($value, $lang, $datatype)
Create a literal expression.

```php
public static function literal($value, $lang = null, $datatype = null): Expression

// Usage
$expr = Expression::literal('Hello', 'en');
$expr = Expression::literal(42, null, 'xsd:integer');
```

## Connection API

### LinkedData\SPARQL\Connection

SPARQL connection for executing queries.

#### Methods

##### select($query, $bindings = [], $useReadPdo = true)
Execute a SELECT query.

```php
public function select($query, $bindings = [], $useReadPdo = true): array

// Usage
$results = DB::connection('sparql')->select('SELECT ?s ?p ?o WHERE { ?s ?p ?o } LIMIT 10');
```

##### insert($query, $bindings = [])
Execute an INSERT query.

```php
public function insert($query, $bindings = []): bool

// Usage
DB::connection('sparql')->insert('INSERT DATA { <http://example.com/person/1> <http://schema.org/name> "John" . }');
```

##### update($query, $bindings = [])
Execute an UPDATE query.

```php
public function update($query, $bindings = []): bool
```

##### delete($query, $bindings = [])
Execute a DELETE query.

```php
public function delete($query, $bindings = []): bool

// Usage
DB::connection('sparql')->delete('DELETE WHERE { <http://example.com/person/1> ?p ?o }');
```

##### namespace($namespace)
Set the Blazegraph namespace for subsequent queries.

```php
public function namespace(string $namespace): static

// Usage
DB::connection('sparql')->namespace('tenant_X_ds_Y');
```

##### getNamespace()
Get the current Blazegraph namespace.

```php
public function getNamespace(): ?string

// Usage
$namespace = DB::connection('sparql')->getNamespace();
```

##### withinNamespace($namespace, $callback)
Execute a query within a specific namespace scope.

```php
public function withinNamespace(string $namespace, \Closure $callback): mixed

// Usage
$results = DB::connection('sparql')->withinNamespace('tenant_X', function($query) {
    return $query->table('http://schema.org/Person')->count();
});
```

##### table($table)
Begin a query on a table (RDF class).

```php
public function table($table): Builder

// Usage
$people = DB::connection('sparql')->table('http://schema.org/Person')->get();
```

##### rdftype($table)
Alias for table() with semantic naming.

```php
public function rdftype($table): Builder

// Usage
$people = DB::connection('sparql')->rdftype('foaf:Person')->get();
```

##### getGraph()
Get the default graph for queries.

```php
public function getGraph(): ?string

// Usage
$graph = DB::connection('sparql')->getGraph();
```

## SyncsToSparql Trait

### LinkedData\SPARQL\Eloquent\Concerns\SyncsToSparql

Trait for syncing regular Eloquent models to SPARQL.

#### Required Methods

You must implement these abstract methods:

```php
// Return SPARQL connection name
abstract public function getSparqlConnection(): string;

// Return the URI for this resource
abstract public function getSparqlUri(): string;

// Return the RDF class for this resource
abstract public function getSparqlRdfClass(): string;

// Map model attributes to SPARQL predicates
abstract public function toSparqlAttributes(): array;
```

#### Provided Methods

##### syncToSparql()
Sync this model to SPARQL.

```php
public function syncToSparql(): bool

// Usage
$product->syncToSparql();
```

##### deleteFromSparql()
Delete this model from SPARQL.

```php
public function deleteFromSparql(): bool

// Usage
$product->deleteFromSparql();
```

##### buildSparqlResource()
Build a GenericResource from this model.

```php
protected function buildSparqlResource(): GenericResource
```

#### Static Methods

##### syncBatchToSparql($models)
Sync multiple models in batch.

```php
public static function syncBatchToSparql(iterable $models): int

// Usage
Product::syncBatchToSparql($products);
```

##### deleteBatchFromSparql($models)
Delete multiple models from SPARQL.

```php
public static function deleteBatchFromSparql(iterable $models): int

// Usage
Product::deleteBatchFromSparql($products);
```

## GenericResource API

### LinkedData\SPARQL\Eloquent\GenericResource

Generic model for dynamic RDF resources.

#### Static Methods

##### make($uri, $rdfClass)
Create a new GenericResource instance.

```php
public static function make(string $uri, ?string $rdfClass = null): static

// Usage
$resource = GenericResource::make(
    'http://example.com/resource/1',
    'http://schema.org/Thing'
);
```

#### Usage

```php
// Create
$product = GenericResource::make(
    'http://example.com/product/123',
    'http://schema.org/Product'
);
$product->setAttribute('http://schema.org/name', 'Widget');
$product->save();

// Query
$resources = GenericResource::on('sparql')
    ->where('http://schema.org/price', '<', 20)
    ->get();

// Update
$resource = GenericResource::on('sparql')->find('http://example.com/resource/1');
$resource->setAttribute('http://schema.org/name', 'Updated');
$resource->save();

// Delete
$resource->delete();
```

## SPARQL-Specific Operators

The query builder supports these SPARQL-specific operators:

| Operator | Description | Example |
|----------|-------------|---------|
| `regex` | Regex pattern matching | `where('name', 'regex', '^John')` |
| `iregex` | Case-insensitive regex | `where('name', 'iregex', 'john')` |
| `lang` | Language tag filter | `where('title', 'lang', 'en')` |
| `datatype` | Datatype filter | `where('value', 'datatype', 'xsd:integer')` |

## Configuration Options

### Connection Configuration

```php
'sparql' => [
    'driver' => 'sparql',

    // Required: SPARQL endpoint URL
    'endpoint' => 'http://localhost:3030/test/sparql',

    // Optional: Authentication
    'auth' => [
        'type' => 'digest',  // or 'basic'
        'username' => 'admin',
        'password' => 'admin',
    ],

    // Optional: Named graph
    'graph' => 'http://example.com/graph/default',

    // Optional: Request timeout (seconds)
    'timeout' => 30,

    // Recommended: Define namespaces
    'namespaces' => [
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'owl' => 'http://www.w3.org/2002/07/owl#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        // ... your namespaces
    ],
],
```

## Type Casting

### Supported Casts

```php
protected $casts = [
    // Primitives
    'age' => 'integer',
    'price' => 'float',
    'active' => 'boolean',
    'description' => 'string',

    // Objects
    'birth_date' => 'date',
    'created_at' => 'datetime',
    'data' => 'array',
    'metadata' => 'json',

    // Collections (for multi-valued properties)
    'emails' => 'array',
];
```

## Events

### Model Events

All standard Eloquent events are supported:

```php
// Before/after creating
static::creating(function ($model) { });
static::created(function ($model) { });

// Before/after updating
static::updating(function ($model) { });
static::updated(function ($model) { });

// Before/after saving (create or update)
static::saving(function ($model) { });
static::saved(function ($model) { });

// Before/after deleting
static::deleting(function ($model) { });
static::deleted(function ($model) { });
```

## Relationships

### Supported Relationship Types

#### BelongsTo
```php
public function author()
{
    return $this->belongsTo(Person::class, 'http://schema.org/author');
}
```

#### HasMany
```php
public function books()
{
    return $this->hasMany(Book::class, 'http://schema.org/author');
}
```

#### BelongsToMany
```php
public function genres()
{
    return $this->belongsToMany(Genre::class, 'http://schema.org/genre');
}
```

#### HasManyThrough
```php
public function posts()
{
    return $this->hasManyThrough(
        Post::class,
        User::class,
        'http://schema.org/organization',  // Foreign key on users table
        'http://schema.org/author',        // Foreign key on posts table
        'id',                              // Local key on organizations table
        'id'                               // Local key on users table
    );
}
```

## Error Handling

### Exceptions

- `Illuminate\Database\Eloquent\ModelNotFoundException`: Model not found
- `EasyRdf\Http\Exception`: HTTP request failed
- `InvalidArgumentException`: Invalid arguments to methods

### Debug Mode

Enable query logging:

```php
DB::connection('sparql')->enableQueryLog();

// Execute queries...

$queries = DB::connection('sparql')->getQueryLog();
dd($queries);
```

## Best Practices

1. **Always set URIs**: Ensure `id` property is a valid URI
2. **Use property mappings**: Map short names in `$propertyUris`
3. **Define casts**: Proper type handling with `$casts`
4. **Batch when possible**: Use batch operations for multiple records
5. **Eager load**: Use `with()` to prevent N+1 queries
6. **Handle errors**: Wrap queries in try-catch blocks
7. **Log queries**: Enable query logging during development

## See Also

- [Usage Guide](USAGE.md)
- [README](../README.md)
