# Multi-Tenancy Support with stancl/tenancy

This guide explains how to integrate Laravel SPARQL with [stancl/tenancy](https://tenancyforlaravel.com/) to build multi-tenant applications with isolated RDF data per tenant.

## Overview

The Laravel SPARQL package provides full support for multi-tenancy through a custom tenancy bootstrapper. The **recommended approach** uses **graph-based tenancy** where all tenants share the same SPARQL endpoint but have isolated named graphs.

### Graph-Based Tenancy (Recommended)

- All tenants share the same SPARQL endpoint
- Each tenant has their own named graph URI for data isolation
- More efficient resource usage
- Standard SPARQL multi-tenancy pattern
- Easier to manage and monitor

### Namespace-Based Tenancy (Blazegraph)

- Uses Blazegraph's namespace feature for complete data isolation
- Each tenant gets their own namespace within a shared Blazegraph instance
- Provides strong isolation while sharing infrastructure
- Ideal for multi-data source applications
- See "Blazegraph Namespace-Based Tenancy" section below

### Endpoint-Based Tenancy (Optional)

- Each tenant can optionally have their own SPARQL endpoint
- Use when tenants need completely separate triple stores
- More resource-intensive but provides full isolation

This allows each tenant to:

- Have isolated RDF graphs (required for all tenants)
- Optionally connect to their own SPARQL endpoint
- Use different triple store implementations (Fuseki, Blazegraph, etc.)
- Use separate authentication credentials
- Define custom RDF namespaces

## Requirements

- Laravel 12.0+
- PHP 8.2+
- `stancl/tenancy` ^3.0 or ^4.0
- `your1/laravel-sparql` (this package)

## Installation

### 1. Install stancl/tenancy

If you haven't already, install the tenancy package:

```bash
composer require stancl/tenancy
php artisan tenancy:install
php artisan migrate
```

### 2. Configure the SPARQL Bootstrapper

Add the `SPARQLTenancyBootstrapper` to your tenancy configuration file (`config/tenancy.php`):

```php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    // Add the SPARQL bootstrapper
    LinkedData\SPARQL\Tenancy\SPARQLTenancyBootstrapper::class,
],
```

### 3. Configure SPARQL Connection Name (Optional)

By default, the bootstrapper affects the `sparql` connection. You can customize this:

```php
// In config/tenancy.php
'sparql' => [
    'connection_name' => 'sparql', // Change if you use a different connection name
],
```

## Tenant Configuration

### Basic Setup

Add SPARQL endpoint configuration to your Tenant model. The minimum required field is `sparql_endpoint`:

```php
namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    protected $fillable = [
        'id',
        'sparql_endpoint',
        'sparql_implementation',
        'sparql_update_endpoint',
        'sparql_graph',
        'sparql_namespace',  // Blazegraph namespace
        'sparql_auth',
        'sparql_namespaces',
    ];

    protected $casts = [
        'sparql_auth' => 'array',
        'sparql_namespaces' => 'array',
    ];
}
```

### Tenant Attributes

The bootstrapper recognizes the following tenant attributes:

| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| `sparql_graph` | string | **Yes** | **Named graph URI for tenant data isolation (required for graph-based tenancy)** |
| `sparql_namespace` | string | No | **Blazegraph namespace for namespace-based isolation** (alternative to `sparql_graph`) |
| `sparql_endpoint` | string | No | Override the default SPARQL endpoint (for endpoint-based tenancy) |
| `sparql_implementation` | string | No | Triple store type: `fuseki`, `blazegraph`, `generic` (only when `sparql_endpoint` is set) |
| `sparql_update_endpoint` | string | No | Separate update endpoint (only when `sparql_endpoint` is set) |
| `sparql_auth` | array | No | Authentication config (only when `sparql_endpoint` is set): `['type' => 'digest', 'username' => '...', 'password' => '...']` |
| `sparql_namespaces` | array | No | Custom RDF namespaces: `['schema' => 'http://schema.org/', ...]` |

## Usage Examples

### Example 1: Creating Tenants with Graph-Based Isolation (Recommended)

```php
use App\Models\Tenant;

// All tenants share the same SPARQL endpoint configured in config/database.php
// Data isolation is achieved through named graphs

$tenant1 = Tenant::create([
    'id' => 'acme-corp',
    'sparql_graph' => 'http://acme.example.com/graph',
]);

$tenant2 = Tenant::create([
    'id' => 'widgets-inc',
    'sparql_graph' => 'http://widgets.example.com/graph',
]);

$tenant3 = Tenant::create([
    'id' => 'data-co',
    'sparql_graph' => 'http://data-co.example.com/graph',
]);

// Optional: Add custom namespaces per tenant
$tenant4 = Tenant::create([
    'id' => 'custom-tenant',
    'sparql_graph' => 'http://custom.example.com/graph',
    'sparql_namespaces' => [
        'custom' => 'http://custom.example.com/vocab/',
        'schema' => 'http://schema.org/',
    ],
]);
```

### Example 1b: Endpoint-Based Tenancy (When Needed)

If tenants require completely separate SPARQL endpoints:

```php
use App\Models\Tenant;

// Tenant with own Fuseki endpoint
$tenant1 = Tenant::create([
    'id' => 'acme-corp',
    'sparql_graph' => 'http://acme.example.com/graph',
    'sparql_endpoint' => 'http://fuseki-acme.example.com:3030/acme/sparql',
    'sparql_implementation' => 'fuseki',
]);

// Tenant with own Blazegraph endpoint and authentication
$tenant2 = Tenant::create([
    'id' => 'widgets-inc',
    'sparql_graph' => 'http://widgets.example.com/graph',
    'sparql_endpoint' => 'http://blazegraph-widgets.example.com:9999/bigdata/sparql',
    'sparql_implementation' => 'blazegraph',
    'sparql_auth' => [
        'type' => 'digest',
        'username' => 'admin',
        'password' => 'secret',
    ],
]);
```

### Example 2: Using SPARQL Models in Multi-Tenant Context

Once tenancy is initialized, your SPARQL models automatically connect to the tenant's endpoint:

```php
use App\Models\Tenant;
use LinkedData\SPARQL\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'sparql';
    protected $table = 'http://schema.org/Product';

    protected $propertyUris = [
        'name' => 'http://schema.org/name',
        'price' => 'http://schema.org/price',
    ];

    protected $fillable = ['name', 'price'];
}

// In your controller or route
Route::get('/products', function () {
    // tenancy() helper automatically identifies tenant from domain
    tenancy()->initialize(tenant('acme-corp'));

    // Now all SPARQL queries use acme-corp's endpoint
    $products = Product::all(); // Queries acme-corp's SPARQL endpoint

    return response()->json($products);
});
```

### Example 3: Switching Between Tenants

```php
use App\Models\Tenant;

$acme = Tenant::find('acme-corp');
$widgets = Tenant::find('widgets-inc');

// Work with acme-corp's data
tenancy()->initialize($acme);
$acmeProducts = Product::all(); // Uses acme-corp endpoint

// Switch to widgets-inc
tenancy()->initialize($widgets);
$widgetProducts = Product::all(); // Uses widgets-inc endpoint

// Revert to central context
tenancy()->end();
$centralProducts = Product::all(); // Uses default SPARQL config
```

## Migration Guide

If you have an existing Laravel SPARQL application and want to add multi-tenancy:

### 1. Add Tenant SPARQL Endpoints

Update your database schema to store SPARQL configuration in the tenants table:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('sparql_endpoint')->nullable();
            $table->string('sparql_implementation')->default('fuseki');
            $table->string('sparql_update_endpoint')->nullable();
            $table->string('sparql_graph')->nullable();
            $table->json('sparql_auth')->nullable();
            $table->json('sparql_namespaces')->nullable();
        });
    }

    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'sparql_endpoint',
                'sparql_implementation',
                'sparql_update_endpoint',
                'sparql_graph',
                'sparql_auth',
                'sparql_namespaces',
            ]);
        });
    }
};
```

### 2. Keep Central SPARQL Configuration

Your central/default SPARQL configuration in `config/database.php` remains unchanged:

```php
'connections' => [
    'sparql' => [
        'driver' => 'sparql',
        'endpoint' => env('SPARQL_ENDPOINT', 'http://localhost:3030/test/sparql'),
        'implementation' => env('SPARQL_IMPLEMENTATION', 'fuseki'),
    ],
],
```

This configuration is used when no tenant context is active.

### 3. No Model Changes Required

Your existing SPARQL models work without modification. The bootstrapper automatically switches connections based on tenant context.

## Blazegraph Namespace-Based Tenancy

For Blazegraph users, namespace-based tenancy provides complete data isolation using Blazegraph's built-in namespace feature.

### When to Use Namespace-Based Tenancy

- **Multi-Data Source Applications**: Each external data source (API, endpoint, etc.) has its own namespace
- **Complete Isolation Required**: Namespaces provide stronger isolation than named graphs
- **Temporal Data Management**: Historical data in dated namespaces (e.g., `tenant_X_2025_01`)
- **Data Pipeline Stages**: Separate namespaces for raw, validated, and published data

### Setting Up Namespace-Based Tenants

```php
use App\Models\Tenant;

// Namespace-based tenants (no sparql_graph needed)
$tenant1 = Tenant::create([
    'id' => 'data-source-kadaster',
    'sparql_namespace' => 'tenant_begrippen_ds_kadaster',
]);

$tenant2 = Tenant::create([
    'id' => 'data-source-abdl',
    'sparql_namespace' => 'tenant_begrippen_ds_abdl',
]);

$tenant3 = Tenant::create([
    'id' => 'data-source-nen',
    'sparql_namespace' => 'tenant_begrippen_ds_nen',
]);
```

### Using Namespace-Based Tenants

Once a namespace-based tenant is initialized, all queries automatically use the tenant's namespace:

```php
use App\Models\Tenant;

// Initialize tenant with namespace
$tenant = Tenant::find('data-source-kadaster');
tenancy()->initialize($tenant);

// All queries now use the 'tenant_begrippen_ds_kadaster' namespace
$count = DB::connection('sparql')
    ->table('http://www.w3.org/2004/02/skos/core#Concept')
    ->count();

// Models automatically use the namespace
class SKOSConcept extends Model
{
    protected $connection = 'sparql';
    protected $table = 'http://www.w3.org/2004/02/skos/core#Concept';
}

$concepts = SKOSConcept::all(); // Queries in tenant_begrippen_ds_kadaster namespace
```

### Combining Namespace and Graph Isolation

You can use both namespace and graph isolation for maximum security:

```php
$tenant = Tenant::create([
    'id' => 'secure-tenant',
    'sparql_namespace' => 'tenant_secure',
    'sparql_graph' => 'http://secure.example.com/graph',
]);

// Queries will use both the namespace AND the graph
tenancy()->initialize($tenant);
$products = Product::all(); // Queries in 'tenant_secure' namespace within specific graph
```

### Migration Between Graph and Namespace Tenancy

If you need to migrate from graph-based to namespace-based tenancy:

```php
use Illuminate\Support\Facades\DB;

// Before: Graph-based tenant
$oldTenant = Tenant::find('acme-corp');
tenancy()->initialize($oldTenant);

// Export data from graph
$products = Product::all();

// After: Create namespace-based tenant
$newTenant = Tenant::create([
    'id' => 'acme-corp',
    'sparql_namespace' => 'tenant_acme_corp',
]);

tenancy()->initialize($newTenant);

// Import data into namespace
foreach ($products as $product) {
    $product->save(); // Saved to new namespace
}
```

### Important Notes

- Namespace isolation is **complete** - data in one namespace cannot see data in another
- Namespace names must be **alphanumeric with underscores/hyphens only**
- The bootstrapper automatically handles endpoint URL transformation
- Namespaces work with **Blazegraph only** - other triple stores will ignore the `sparql_namespace` attribute

## Advanced Scenarios

### Custom Bootstrapper Configuration

You can extend the bootstrapper to customize tenant configuration resolution:

```php
namespace App\Tenancy;

use LinkedData\SPARQL\Tenancy\SPARQLTenancyBootstrapper as BaseSPARQLBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class CustomSPARQLBootstrapper extends BaseSPARQLBootstrapper
{
    protected function getTenantSparqlConfig(Tenant $tenant): array
    {
        // Custom logic for determining tenant SPARQL config
        // Example: Load from external service, environment variables, etc.

        if ($tenant->getAttribute('use_shared_endpoint')) {
            return [
                'driver' => 'sparql',
                'host' => 'http://shared.sparql.example.com:3030/shared/sparql',
                'implementation' => 'fuseki',
                'graph' => "http://tenant-{$tenant->id}.example.com/graph",
            ];
        }

        return parent::getTenantSparqlConfig($tenant);
    }
}
```

Then use your custom bootstrapper in `config/tenancy.php`:

```php
'bootstrappers' => [
    // ...
    App\Tenancy\CustomSPARQLBootstrapper::class,
],
```

### Multiple SPARQL Connections Per Tenant

If you need multiple SPARQL connections per tenant (e.g., one for public data, one for private data):

```php
// config/database.php
'connections' => [
    'sparql_public' => [
        'driver' => 'sparql',
        'endpoint' => env('SPARQL_PUBLIC_ENDPOINT'),
        'implementation' => 'fuseki',
    ],
    'sparql_private' => [
        'driver' => 'sparql',
        'endpoint' => env('SPARQL_PRIVATE_ENDPOINT'),
        'implementation' => 'fuseki',
    ],
],

// config/tenancy.php - create multiple bootstrappers
'bootstrappers' => [
    LinkedData\SPARQL\Tenancy\SPARQLTenancyBootstrapper::class,
    App\Tenancy\PrivateSPARQLBootstrapper::class, // Custom for second connection
],
```

### Tenant Model with Separate Endpoint Models

For complex setups, you might want to store SPARQL endpoint configurations in a separate model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSPARQLEndpoint extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'endpoint_url',
        'implementation',
        'update_endpoint',
        'graph',
        'auth_config',
        'namespaces',
    ];

    protected $casts = [
        'auth_config' => 'array',
        'namespaces' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

// In your Tenant model
class Tenant extends BaseTenant
{
    public function sparqlEndpoints()
    {
        return $this->hasMany(TenantSPARQLEndpoint::class);
    }

    public function primarySparqlEndpoint()
    {
        return $this->hasOne(TenantSPARQLEndpoint::class)
            ->where('name', 'primary')
            ->latest();
    }
}
```

Then customize the bootstrapper to use this relationship:

```php
protected function getTenantSparqlConfig(Tenant $tenant): array
{
    $endpoint = $tenant->primarySparqlEndpoint;

    if (!$endpoint) {
        return [];
    }

    return [
        'driver' => 'sparql',
        'host' => $endpoint->endpoint_url,
        'implementation' => $endpoint->implementation,
        'update_endpoint' => $endpoint->update_endpoint,
        'graph' => $endpoint->graph,
        'auth' => $endpoint->auth_config,
        'namespaces' => $endpoint->namespaces,
    ];
}
```

## Testing

When testing multi-tenant applications with SPARQL:

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Product;

class TenantSPARQLTest extends TestCase
{
    public function test_tenant_uses_correct_sparql_endpoint()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant',
            'sparql_endpoint' => 'http://localhost:3030/test/sparql',
            'sparql_implementation' => 'fuseki',
        ]);

        tenancy()->initialize($tenant);

        // Assert connection config was switched
        $this->assertEquals(
            'http://localhost:3030/test/sparql',
            config('database.connections.sparql.host')
        );

        // Test SPARQL operations
        $product = new Product([
            'id' => 'http://example.com/product/1',
            'name' => 'Test Product',
        ]);
        $product->save();

        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }

    public function test_tenant_isolation()
    {
        $tenant1 = Tenant::create([
            'id' => 'tenant1',
            'sparql_endpoint' => 'http://localhost:3030/tenant1/sparql',
        ]);

        $tenant2 = Tenant::create([
            'id' => 'tenant2',
            'sparql_endpoint' => 'http://localhost:3030/tenant2/sparql',
        ]);

        // Create product for tenant1
        tenancy()->initialize($tenant1);
        Product::create(['id' => 'http://example.com/p1', 'name' => 'Product 1']);

        // Switch to tenant2
        tenancy()->initialize($tenant2);
        $products = Product::all();

        // tenant2 should not see tenant1's products
        $this->assertCount(0, $products);
    }
}
```

## Troubleshooting

### Issue: Tenant Connection Not Switching

**Solution**: Ensure the bootstrapper is registered in `config/tenancy.php` and that your tenant model has the `sparql_endpoint` attribute.

### Issue: Authentication Failing

**Solution**: Verify the `sparql_auth` array structure:

```php
[
    'type' => 'digest', // or 'basic'
    'username' => 'your-username',
    'password' => 'your-password',
]
```

### Issue: Namespaces Not Being Applied

**Solution**: Ensure `sparql_namespaces` is cast to array in your Tenant model:

```php
protected $casts = [
    'sparql_namespaces' => 'array',
];
```

### Issue: Connection Leaking Between Tenants

**Solution**: Always call `tenancy()->end()` when done with tenant context, or use the `tenant()` helper which automatically manages context:

```php
tenant($tenantId, function () {
    // Tenant-specific code
    Product::all();
}); // Context automatically reverted after closure
```

## Best Practices

1. **Isolate SPARQL Endpoints**: Each tenant should have their own SPARQL endpoint or at minimum their own named graph.

2. **Use Environment-Specific Configuration**: Store sensitive SPARQL credentials in environment variables or encrypted storage, not directly in the database.

3. **Monitor Connection Pooling**: Be aware of connection limits on your SPARQL endpoints when serving many tenants.

4. **Implement Health Checks**: Regularly verify tenant SPARQL endpoints are accessible:

```php
use Illuminate\Support\Facades\DB;

public function checkTenantSparqlHealth(Tenant $tenant): bool
{
    tenancy()->initialize($tenant);

    try {
        DB::connection('sparql')->select('ASK { ?s ?p ?o }');
        return true;
    } catch (\Exception $e) {
        \Log::error("Tenant {$tenant->id} SPARQL endpoint unreachable", [
            'error' => $e->getMessage(),
        ]);
        return false;
    } finally {
        tenancy()->end();
    }
}
```

5. **Cache Tenant Configurations**: For high-traffic applications, consider caching tenant SPARQL configurations to reduce database queries.

## Resources

- [stancl/tenancy Documentation](https://tenancyforlaravel.com/)
- [Laravel SPARQL Documentation](../README.md)
- [SPARQL 1.1 Specification](https://www.w3.org/TR/sparql11-query/)

## Support

For issues related to:
- **Multi-tenancy integration**: Open an issue at [laravel-sparql GitHub](https://github.com/YOUR1/laravel-sparql/issues)
- **Tenancy package**: See [stancl/tenancy issues](https://github.com/archtechx/tenancy/issues)
