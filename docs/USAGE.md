# Usage Guide

Comprehensive guide to using Laravel SPARQL for working with RDF triple stores.

## Table of Contents

- [Getting Started](#getting-started)
- [Defining Models](#defining-models)
- [CRUD Operations](#crud-operations)
- [Querying](#querying)
- [Relationships](#relationships)
- [RDF-Specific Features](#rdf-specific-features)
- [Batch Operations](#batch-operations)
- [Syncing Regular Models](#syncing-regular-models)
- [Advanced Topics](#advanced-topics)

## Getting Started

### Installation

```bash
composer require solid-data-workers/laravel-sparql
```

### Configuration

Add to `config/database.php`:

```php
'connections' => [
    'sparql' => [
        'driver' => 'sparql',
        'endpoint' => env('SPARQL_ENDPOINT', 'http://localhost:3030/test/sparql'),

        'auth' => [
            'type' => 'digest',
            'username' => env('SPARQL_USERNAME'),
            'password' => env('SPARQL_PASSWORD'),
        ],

        'namespaces' => [
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'owl' => 'http://www.w3.org/2002/07/owl#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
            'schema' => 'http://schema.org/',
        ],
    ],
],
```

## Defining Models

### Basic Model

```php
use LinkedData\SPARQL\Eloquent\Model;

class Person extends Model
{
    protected $connection = 'sparql';

    // RDF class (rdf:type)
    protected $table = 'http://schema.org/Person';
}
```

### With Property Mappings

Map short names to full URIs:

```php
class Person extends Model
{
    protected $connection = 'sparql';
    protected $table = 'http://schema.org/Person';

    // Map short names to URIs
    protected $propertyUris = [
        'name' => 'http://schema.org/name',
        'email' => 'http://schema.org/email',
        'birthDate' => 'http://schema.org/birthDate',
        'birthPlace' => 'http://schema.org/birthPlace',
    ];

    // Standard Eloquent features
    protected $fillable = ['name', 'email', 'birthDate', 'birthPlace'];

    protected $casts = [
        'birthDate' => 'date',
    ];
}
```

### With Multiple RDF Classes

A model can have multiple rdf:type values:

```php
class Organization extends Model
{
    protected $connection = 'sparql';
    protected $table = 'http://schema.org/Organization';  // Primary type

    // Additional types can be added as attributes
    protected function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Add additional types
            $model->setAttribute('rdf:type', [
                'http://schema.org/Organization',
                'http://xmlns.com/foaf/0.1/Organization',
            ]);
        });
    }
}
```

## CRUD Operations

### Create

```php
// Method 1: New instance + save
$person = new Person();
$person->id = 'http://example.com/person/1';
$person->name = 'John Doe';
$person->email = 'john@example.com';
$person->save();

// Method 2: Mass assignment
$person = Person::create([
    'id' => 'http://example.com/person/1',
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Method 3: Fill and save
$person = new Person();
$person->fill([
    'id' => 'http://example.com/person/1',
    'name' => 'John Doe',
]);
$person->save();
```

### Read

```php
// Find by URI
$person = Person::find('http://example.com/person/1');

// Get all
$people = Person::all();

// Get first
$person = Person::first();

// Find or fail
$person = Person::findOrFail('http://example.com/person/1');

// Find or create
$person = Person::firstOrCreate(
    ['id' => 'http://example.com/person/1'],
    ['name' => 'John Doe']
);

// Find or new
$person = Person::firstOrNew(['id' => 'http://example.com/person/1']);
```

### Update

```php
// Method 1: Find and update
$person = Person::find('http://example.com/person/1');
$person->name = 'Jane Doe';
$person->save();

// Method 2: Mass update
$person = Person::find('http://example.com/person/1');
$person->update(['name' => 'Jane Doe']);

// Method 3: Update or create
Person::updateOrCreate(
    ['id' => 'http://example.com/person/1'],
    ['name' => 'Jane Doe', 'email' => 'jane@example.com']
);
```

### Delete

```php
// Method 1: Find and delete
$person = Person::find('http://example.com/person/1');
$person->delete();

// Method 2: Delete by key
Person::destroy('http://example.com/person/1');

// Method 3: Delete multiple
Person::destroy([
    'http://example.com/person/1',
    'http://example.com/person/2',
]);

// Method 4: Delete with query
Person::where('age', '<', 18)->delete();
```

## Querying

### Basic Where Clauses

```php
// Simple where
$adults = Person::where('age', '>', 18)->get();

// Multiple conditions
$results = Person::where('age', '>', 18)
                 ->where('city', 'New York')
                 ->get();

// Or where
$results = Person::where('age', '>', 65)
                 ->orWhere('disabled', true)
                 ->get();

// Where in
$people = Person::whereIn('city', ['New York', 'Los Angeles', 'Chicago'])->get();

// Where between
$people = Person::whereBetween('age', [18, 65])->get();

// Where null
$people = Person::whereNull('email')->get();

// Where not null
$people = Person::whereNotNull('email')->get();
```

### Advanced Where Clauses

```php
// Nested conditions
$results = Person::where('age', '>', 18)
    ->where(function($query) {
        $query->where('city', 'New York')
              ->orWhere('city', 'Los Angeles');
    })
    ->get();

// Subqueries
$results = Person::whereIn('birthPlace', function($query) {
    $query->select('id')
          ->from('http://schema.org/Place')
          ->where('country', 'USA');
})->get();
```

### SPARQL-Specific Operators

```php
// Regex matching
$people = Person::where('name', 'regex', '^John')->get();

// Language filtering (for language-tagged literals)
$books = Book::where('title', 'lang', 'en')->get();

// Case-insensitive matching
$people = Person::where('name', 'iregex', 'john')->get();
```

### Ordering

```php
// Order by ascending
$people = Person::orderBy('age')->get();

// Order by descending
$people = Person::orderBy('age', 'desc')->get();

// Multiple orders
$people = Person::orderBy('city')
                ->orderBy('age', 'desc')
                ->get();

// Latest/oldest (requires timestamps)
$people = Person::latest()->get();
$people = Person::oldest()->get();
```

### Limiting & Pagination

```php
// Limit
$people = Person::limit(10)->get();

// Offset
$people = Person::offset(20)->limit(10)->get();

// Take (alias for limit)
$people = Person::take(5)->get();

// Skip (alias for offset)
$people = Person::skip(10)->take(5)->get();

// Pagination
$people = Person::paginate(15);

// Simple pagination
$people = Person::simplePaginate(15);
```

### Aggregations

```php
// Count
$count = Person::count();
$count = Person::where('age', '>', 18)->count();

// Max
$maxAge = Person::max('age');

// Min
$minAge = Person::min('age');

// Average
$avgAge = Person::avg('age');

// Sum
$totalArea = Place::sum('area');

// Check if exists
$exists = Person::where('email', 'john@example.com')->exists();
```

### Select Specific Properties

```php
// Select specific properties
$people = Person::select('name', 'email')->get();

// Select with aliases (limited support)
$people = Person::select('name as fullName')->get();

// Add select
$query = Person::select('name');
$query->addSelect('email');
$people = $query->get();
```

### Distinct

```php
// Get distinct values
$cities = Person::distinct()->get(['city']);

// Distinct with where
$cities = Person::where('country', 'USA')
                ->distinct()
                ->get(['city']);
```

### Group By

```php
// Group by property
$results = Person::groupBy('city')->get(['city', 'name']);

// Group with aggregates (may require raw SPARQL)
$results = Person::groupBy('city')
                 ->selectRaw('city, COUNT(*) as total')
                 ->get();
```

## Relationships

Define relationships between SPARQL models:

### BelongsTo

```php
class Book extends Model
{
    protected $table = 'http://schema.org/Book';

    public function author()
    {
        return $this->belongsTo(Person::class, 'http://schema.org/author');
    }
}

// Usage
$book = Book::find('http://example.com/book/1');
$author = $book->author;  // Person model
```

### HasMany

```php
class Person extends Model
{
    protected $table = 'http://schema.org/Person';

    public function books()
    {
        return $this->hasMany(Book::class, 'http://schema.org/author');
    }
}

// Usage
$person = Person::find('http://example.com/person/1');
$books = $person->books;  // Collection of Book models

// Query relationship
$books = $person->books()->where('year', '>', 2000)->get();
```

### BelongsToMany

```php
class Book extends Model
{
    public function genres()
    {
        return $this->belongsToMany(
            Genre::class,
            'http://schema.org/genre'
        );
    }
}

// Usage
$book = Book::find('http://example.com/book/1');
$genres = $book->genres;  // Collection of Genre models
```

### Eager Loading

```php
// Eager load relationships
$books = Book::with('author')->get();

// Multiple relationships
$books = Book::with(['author', 'genres'])->get();

// Nested relationships
$books = Book::with('author.birthPlace')->get();

// Lazy eager loading
$books = Book::all();
$books->load('author');
```

## RDF-Specific Features

### Language Tags

```php
// Set language-tagged value
$book = new Book();
$book->id = 'http://example.com/book/1';
$book->setAttributeWithLang('title', 'The Great Gatsby', 'en');
$book->setAttributeWithLang('title', 'Le Grand Gatsby', 'fr');
$book->save();

// Result in SPARQL:
// <http://example.com/book/1> <http://schema.org/title> "The Great Gatsby"@en .
// <http://example.com/book/1> <http://schema.org/title> "Le Grand Gatsby"@fr .
```

### Multi-Valued Properties

```php
// Method 1: Set as array
$person = new Person();
$person->id = 'http://example.com/person/1';
$person->emails = ['john@work.com', 'john@personal.com'];
$person->save();

// Method 2: Add values individually
$person = new Person();
$person->id = 'http://example.com/person/1';
$person->addPropertyValue('email', 'john@work.com');
$person->addPropertyValue('email', 'john@personal.com');
$person->save();

// Method 3: Mix methods
$person->email = 'john@primary.com';  // Single value
$person->addPropertyValue('email', 'john@secondary.com');  // Add another
$person->save();
```

### Typed Literals

```php
// Integers
$person->age = 30;  // Automatically typed as xsd:integer

// Floats
$product->price = 19.99;  // Automatically typed as xsd:decimal

// Booleans
$person->active = true;  // Automatically typed as xsd:boolean

// Dates (with casting)
protected $casts = [
    'birthDate' => 'date',
];

$person->birthDate = '1990-01-15';  // Typed as xsd:date
```

### URI References

```php
// Set URI reference (object property)
$book->author = 'http://example.com/person/1';  // URI as string

// With Expression helper
use LinkedData\SPARQL\Query\Expression;

$book->author = Expression::iri('http://example.com/person/1');
```

## Batch Operations

### Batch Insert

Efficiently insert multiple resources with a single SPARQL query:

```php
$people = [
    (new Person())->fill([
        'id' => 'http://example.com/person/1',
        'name' => 'Alice',
        'age' => 25,
    ]),
    (new Person())->fill([
        'id' => 'http://example.com/person/2',
        'name' => 'Bob',
        'age' => 30,
    ]),
    (new Person())->fill([
        'id' => 'http://example.com/person/3',
        'name' => 'Charlie',
        'age' => 35,
    ]),
];

Person::insertBatch($people);
```

### Batch Delete

Delete multiple resources by URI:

```php
$uris = [
    'http://example.com/person/1',
    'http://example.com/person/2',
    'http://example.com/person/3',
];

$deletedCount = Person::deleteBatch($uris);
```

## Syncing Regular Models

Sync regular Eloquent models to SPARQL using the `SyncsToSparql` trait:

### Setup

```php
use Illuminate\Database\Eloquent\Model as EloquentModel;
use LinkedData\SPARQL\Eloquent\Concerns\SyncsToSparql;

class Product extends EloquentModel
{
    use SyncsToSparql;

    protected $connection = 'mysql';  // Regular database
    protected $fillable = ['name', 'price', 'description', 'active'];

    // SPARQL configuration
    public function getSparqlConnection(): string
    {
        return 'sparql';
    }

    public function getSparqlUri(): string
    {
        return 'http://example.com/product/' . $this->id;
    }

    public function getSparqlRdfClass(): string
    {
        return 'http://schema.org/Product';
    }

    public function toSparqlAttributes(): array
    {
        return [
            'http://schema.org/name' => $this->name,
            'http://schema.org/price' => $this->price,
            'http://schema.org/description' => $this->description,
        ];
    }
}
```

### Single Sync

```php
// Sync one model
$product = Product::find(1);
$product->syncToSparql();

// Automatically sync on create/update
Product::created(function ($product) {
    $product->syncToSparql();
});

Product::updated(function ($product) {
    $product->syncToSparql();
});
```

### Batch Sync

```php
// Sync multiple models efficiently
$products = Product::where('active', true)->get();
$syncedCount = Product::syncBatchToSparql($products);

echo "Synced {$syncedCount} products";
```

### Delete from SPARQL

```php
// Delete single
$product = Product::find(1);
$product->deleteFromSparql();

// Batch delete
$products = Product::where('active', false)->get();
Product::deleteBatchFromSparql($products);

// Automatically delete from SPARQL
Product::deleted(function ($product) {
    $product->deleteFromSparql();
});
```

## Advanced Topics

### Generic Resources

For dynamic RDF resources without defining models:

```php
use LinkedData\SPARQL\Eloquent\GenericResource;

// Create resource
$resource = GenericResource::make(
    'http://example.com/resource/1',
    'http://schema.org/Thing'
);

$resource->setAttribute('http://schema.org/name', 'My Resource');
$resource->setAttribute('http://schema.org/description', 'A dynamic resource');
$resource->save();

// Query
$resources = GenericResource::on('sparql')
    ->where('http://schema.org/name', 'like', 'My%')
    ->get();

// Update
$resource = GenericResource::on('sparql')->find('http://example.com/resource/1');
$resource->setAttribute('http://schema.org/name', 'Updated Name');
$resource->save();
```

### Working with Named Graphs

```php
// Query specific graph
$people = Person::on('sparql')
    ->from('http://example.com/graph/people')
    ->get();

// Or set in connection config
'sparql' => [
    'driver' => 'sparql',
    'endpoint' => 'http://localhost:3030/test/sparql',
    'graph' => 'http://example.com/graph/default',
],
```

### Raw SPARQL Queries

```php
// Execute raw SELECT query
$results = DB::connection('sparql')->select('
    SELECT ?person ?name WHERE {
        ?person a <http://schema.org/Person> .
        ?person <http://schema.org/name> ?name .
    }
    LIMIT 10
');

// Execute raw UPDATE query
DB::connection('sparql')->update('
    INSERT DATA {
        <http://example.com/person/1> <http://schema.org/name> "John" .
    }
');

// Execute raw DELETE query
DB::connection('sparql')->delete('
    DELETE WHERE {
        <http://example.com/person/1> ?p ?o .
    }
');
```

### Model Events

```php
class Person extends Model
{
    protected static function boot()
    {
        parent::boot();

        // Before creating
        static::creating(function ($person) {
            // Generate URI if not set
            if (empty($person->id)) {
                $person->id = 'http://example.com/person/' . Str::uuid();
            }
        });

        // After creating
        static::created(function ($person) {
            Log::info("Person created: {$person->id}");
        });

        // Before updating
        static::updating(function ($person) {
            $person->updated_at = now();
        });

        // After updating
        static::updated(function ($person) {
            Cache::forget("person:{$person->id}");
        });

        // Before deleting
        static::deleting(function ($person) {
            // Delete related data
        });

        // After deleting
        static::deleted(function ($person) {
            Log::info("Person deleted: {$person->id}");
        });
    }
}
```

### Accessors & Mutators

```php
class Person extends Model
{
    // Accessor: Compute value when accessed
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Mutator: Transform value before saving
    public function setEmailAttribute($value)
    {
        $this->attributes['http://schema.org/email'] = strtolower($value);
    }

    // Mutator with multiple properties
    public function setFullNameAttribute($value)
    {
        [$firstName, $lastName] = explode(' ', $value, 2);
        $this->first_name = $firstName;
        $this->last_name = $lastName ?? '';
    }
}

// Usage
$person = new Person();
$person->email = 'JOHN@EXAMPLE.COM';  // Stored as john@example.com
$person->full_name = 'John Doe';  // Sets first_name and last_name

echo $person->full_name;  // "John Doe" (computed)
```

### Casts

```php
class Person extends Model
{
    protected $casts = [
        'age' => 'integer',
        'height' => 'float',
        'active' => 'boolean',
        'birth_date' => 'date',
        'metadata' => 'array',
        'emails' => 'array',  // Multi-valued property
    ];
}

// Usage
$person->age = '30';  // Cast to integer
$person->active = '1';  // Cast to boolean
$person->birth_date = '1990-01-15';  // Cast to Carbon date
$person->metadata = ['key' => 'value'];  // Cast to/from JSON
```

## Best Practices

1. **Always Define URIs**: Set the `id` property to a full URI
2. **Use Property Mappings**: Map short names for convenience
3. **Batch When Possible**: Use batch operations for multiple records
4. **Define Relationships**: Explicit relationships are clearer than dynamic access
5. **Use Casts**: Define casts for proper type handling
6. **Model Events**: Use events for side effects, not business logic
7. **Eager Load**: Prevent N+1 queries with eager loading
8. **Index Your SPARQL Store**: Ensure your endpoint is properly configured

## Troubleshooting

### Slow Queries
- Enable query logging: `DB::connection('sparql')->enableQueryLog()`
- Check your SPARQL endpoint performance
- Use LIMIT and pagination
- Eager load relationships

### Connection Issues
- Verify endpoint URL
- Check authentication credentials
- Test endpoint with curl
- Check firewall settings

### Data Not Saving
- Verify the `id` (URI) is set
- Check `$fillable` or disable `$guarded`
- Enable query logging to see generated SPARQL
- Check SPARQL endpoint write permissions

## Next Steps

- Review the [API Reference](API.md)
- See [README](../README.md) for quick reference
