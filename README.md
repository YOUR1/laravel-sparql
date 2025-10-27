# Laravel SPARQL

A lean, production-ready **Laravel Eloquent adapter for SPARQL triple stores**. Query and manage RDF data using familiar Laravel patterns.

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](tests)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Overview

Laravel SPARQL brings the power of RDF triple stores to Laravel with an Eloquent-style interface that feels native to Laravel developers. Built on the original Illuminate Database package by Taylor Otwell.

### Key Features

- **Familiar Eloquent API** - Use standard Laravel patterns like `where()`, `get()`, `first()`, `save()`, `delete()`
- **Hybrid Approach** - Scalars for single values, arrays for multi-values (no unnecessary Collections)
- **RDF Extensions** - Language tags, multi-valued properties, and URI mappings when you need them
- **Batch Operations** - Efficient bulk insert/update/delete operations
- **Sync Trait** - Easily sync regular Eloquent models to SPARQL endpoints
- **No Magic** - Explicit model definitions, no dynamic class generation
- **Production Ready** - 100% test coverage, optimized for performance

## Requirements

- PHP 8.2+
- Laravel 12.0+
- A SPARQL 1.1 compliant endpoint with **Graph Store Protocol** support
  - ✅ Apache Jena Fuseki
  - ✅ Blazegraph
  - ✅ Amazon Neptune
  - ✅ GraphDB
  - ✅ Virtuoso
  - ✅ Most modern SPARQL stores

> **Note:** This package uses the W3C SPARQL 1.1 Graph Store HTTP Protocol for efficient bulk operations. This is a standard feature in modern SPARQL endpoints.

## Installation

Install via Composer:

```bash
composer require your1/laravel-sparql
```

The service provider will automatically register a `sparql` database driver with Laravel's database manager.

## Quick Start

### 1. Configure Your SPARQL Endpoint

Add to `config/database.php`:

```php
'connections' => [
    'sparql' => [
        'driver' => 'sparql',
        'endpoint' => env('SPARQL_ENDPOINT', 'http://localhost:3030/test/sparql'),

        // IMPORTANT: Specify your triple store implementation
        'implementation' => env('SPARQL_IMPLEMENTATION', 'fuseki'),  // fuseki|blazegraph|generic

        'auth' => [
            'type' => 'digest',
            'username' => env('SPARQL_USERNAME'),
            'password' => env('SPARQL_PASSWORD'),
        ],
        'namespaces' => [
            'schema' => 'http://schema.org/',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
        ],
    ],
],
```

**Implementation Options:**
- `fuseki` - Apache Jena Fuseki (default)
- `blazegraph` - Blazegraph triple store
- `generic` - W3C standard (Virtuoso, GraphDB, Stardog, Amazon Neptune, etc.)

Add to `.env`:

```env
SPARQL_ENDPOINT=http://localhost:3030/test/sparql
SPARQL_IMPLEMENTATION=fuseki
```

### 2. Define a Model

```php
use LinkedData\SPARQL\Eloquent\Model;

class Person extends Model
{
    protected $connection = 'sparql';
    protected $table = 'http://schema.org/Person';

    protected $propertyUris = [
        'name' => 'http://schema.org/name',
        'email' => 'http://schema.org/email',
        'age' => 'http://schema.org/age',
    ];

    protected $fillable = ['name', 'email', 'age'];
    protected $casts = ['age' => 'integer'];
}
```

### 3. Start Using It

```php
// Create
$person = new Person();
$person->id = 'http://example.com/person/1';
$person->name = 'John Doe';
$person->email = 'john@example.com';
$person->save();

// Query
$adults = Person::where('age', '>', 18)->get();
$john = Person::where('name', 'John')->first();

// Update
$person->age = 31;
$person->save();

// Delete
$person->delete();
```

## Documentation

For comprehensive guides and examples, see:

- **[Usage Guide](docs/USAGE.md)** - Core concepts, CRUD operations, queries, RDF features, batch operations, and syncing regular Eloquent models
- **[API Reference](docs/API.md)** - Complete API documentation for all classes and methods
- **[Development Guide](docs/DEVELOPMENT.md)** - Setup, testing, and development instructions

## Philosophy

- **Simple is Better** - Minimal complexity, maximum clarity
- **No Magic** - Explicit over implicit, predictable behavior
- **Performance First** - Optimized for production workloads
- **Laravel Native** - Feels like standard Eloquent

## Credits

**Original Author**: Roberto Guido - Created the foundational Laravel SPARQL adapter and maintained it as part of the Solid Data Workers project.

**Original Repository**: https://gitlab.com/solid-data-workers/laravel-sparql

**Original Licenses**: MIT License and Creative Commons Attribution 3.0 Unported

This fork preserves all original copyright and attribution while adding significant enhancements and modernizations.

Built on Laravel's Illuminate Database package by Taylor Otwell.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/YOUR1/laravel-sparql/issues)
- **Discussions**: [GitHub Discussions](https://github.com/YOUR1/laravel-sparql/discussions)
