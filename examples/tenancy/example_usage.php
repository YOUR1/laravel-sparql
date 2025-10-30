<?php

/**
 * Laravel SPARQL Multi-Tenancy Usage Examples
 *
 * This file demonstrates how to use Laravel SPARQL with stancl/tenancy
 * for building multi-tenant applications where each tenant has their own
 * SPARQL endpoint.
 *
 * Prerequisites:
 * 1. Install stancl/tenancy: composer require stancl/tenancy
 * 2. Run: php artisan tenancy:install
 * 3. Add SPARQLTenancyBootstrapper to config/tenancy.php
 * 4. Add SPARQL fields to tenants table (see add_sparql_to_tenants_table.php)
 */

use App\Models\Tenant;
use LinkedData\SPARQL\Eloquent\Model;

// ============================================================================
// EXAMPLE 1: Creating Tenants with SPARQL Endpoints
// ============================================================================

// Create tenant with Fuseki endpoint
$acmeCorp = Tenant::create([
    'id' => 'acme-corp',
    'sparql_endpoint' => 'http://fuseki.acme.example.com:3030/acme/sparql',
    'sparql_implementation' => 'fuseki',
    'sparql_graph' => 'http://acme.example.com/data',
]);

// Create tenant with Blazegraph endpoint and authentication
$widgetsInc = Tenant::create([
    'id' => 'widgets-inc',
    'sparql_endpoint' => 'http://blazegraph.widgets.example.com:9090/bigdata/sparql',
    'sparql_implementation' => 'blazegraph',
    'sparql_auth' => [
        'type' => 'digest',
        'username' => 'admin',
        'password' => 'secret',
    ],
    'sparql_namespaces' => [
        'wdg' => 'http://widgets.example.com/vocab/',
        'schema' => 'http://schema.org/',
    ],
]);

// Create tenant with generic SPARQL endpoint (GraphDB, Virtuoso, etc.)
$dataCo = Tenant::create([
    'id' => 'data-co',
    'sparql_endpoint' => 'https://graphdb.data-co.example.com/repositories/main',
    'sparql_implementation' => 'generic',
]);

// ============================================================================
// EXAMPLE 2: Define SPARQL Models
// ============================================================================

class Product extends Model
{
    protected $connection = 'sparql';
    protected $table = 'http://schema.org/Product';

    protected $propertyUris = [
        'name' => 'http://schema.org/name',
        'description' => 'http://schema.org/description',
        'price' => 'http://schema.org/price',
        'category' => 'http://schema.org/category',
    ];

    protected $fillable = ['name', 'description', 'price', 'category'];
    protected $casts = ['price' => 'float'];
}

class Person extends Model
{
    protected $connection = 'sparql';
    protected $table = 'http://schema.org/Person';

    protected $propertyUris = [
        'name' => 'http://schema.org/name',
        'email' => 'http://schema.org/email',
        'organization' => 'http://schema.org/worksFor',
    ];

    protected $fillable = ['name', 'email', 'organization'];
}

// ============================================================================
// EXAMPLE 3: Using Models in Tenant Context
// ============================================================================

// Initialize tenant context
tenancy()->initialize(Tenant::find('acme-corp'));

// Now all SPARQL queries use acme-corp's endpoint
$product = new Product([
    'id' => 'http://acme.example.com/product/laptop-001',
    'name' => 'Professional Laptop',
    'price' => 1299.99,
    'category' => 'Electronics',
]);
$product->save();

// Query data
$products = Product::where('price', '>', 1000)->get();
$laptops = Product::where('category', 'Electronics')->get();

// Switch to another tenant
tenancy()->initialize(Tenant::find('widgets-inc'));

// Now queries use widgets-inc's endpoint
$widget = new Product([
    'id' => 'http://widgets.example.com/product/widget-001',
    'name' => 'Super Widget',
    'price' => 49.99,
]);
$widget->save();

// End tenant context (return to central/default)
tenancy()->end();

// ============================================================================
// EXAMPLE 4: Tenant Context with Helper Function
// ============================================================================

// Using tenant() helper - automatically manages context
tenant('acme-corp', function () {
    // Create multiple products for acme-corp
    $products = [
        ['id' => 'http://acme.example.com/product/1', 'name' => 'Product 1', 'price' => 99.99],
        ['id' => 'http://acme.example.com/product/2', 'name' => 'Product 2', 'price' => 149.99],
        ['id' => 'http://acme.example.com/product/3', 'name' => 'Product 3', 'price' => 199.99],
    ];

    foreach ($products as $productData) {
        Product::create($productData);
    }

    return Product::all();
}); // Context automatically reverted after closure

// ============================================================================
// EXAMPLE 5: Multi-Tenant Routes
// ============================================================================

// In routes/tenant.php (created by stancl/tenancy)
Route::get('/products', function () {
    // Tenant is automatically identified from domain
    // tenancy() is already initialized
    return response()->json(Product::all());
});

Route::post('/products', function (Request $request) {
    $product = new Product($request->all());
    $product->id = 'http://' . tenant('id') . '.example.com/product/' . Str::uuid();
    $product->save();

    return response()->json($product, 201);
});

Route::get('/products/{id}', function ($id) {
    // Construct full URI
    $uri = 'http://' . tenant('id') . '.example.com/product/' . $id;
    $product = Product::find($uri);

    if (!$product) {
        abort(404);
    }

    return response()->json($product);
});

// ============================================================================
// EXAMPLE 6: Controller with Tenant-Aware SPARQL
// ============================================================================

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        // Tenancy is automatically initialized for tenant routes
        $products = Product::all();

        return view('products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string',
        ]);

        $product = new Product($validated);
        $product->id = 'http://' . tenant('id') . '.example.com/product/' . Str::uuid();
        $product->save();

        return redirect()->route('products.index')
            ->with('success', 'Product created successfully');
    }

    public function show(string $id)
    {
        $uri = 'http://' . tenant('id') . '.example.com/product/' . $id;
        $product = Product::find($uri);

        if (!$product) {
            abort(404);
        }

        return view('products.show', compact('product'));
    }

    public function update(Request $request, string $id)
    {
        $uri = 'http://' . tenant('id') . '.example.com/product/' . $id;
        $product = Product::find($uri);

        if (!$product) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'category' => 'sometimes|required|string',
        ]);

        $product->fill($validated);
        $product->save();

        return redirect()->route('products.show', $id)
            ->with('success', 'Product updated successfully');
    }

    public function destroy(string $id)
    {
        $uri = 'http://' . tenant('id') . '.example.com/product/' . $id;
        $product = Product::find($uri);

        if (!$product) {
            abort(404);
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully');
    }
}

// ============================================================================
// EXAMPLE 7: Seeding Tenant Data
// ============================================================================

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // This seeder runs in tenant context (tenant is already initialized)

        // Seed products
        $products = [
            [
                'id' => 'http://' . tenant('id') . '.example.com/product/p1',
                'name' => 'Sample Product 1',
                'description' => 'Description for product 1',
                'price' => 99.99,
                'category' => 'Electronics',
            ],
            [
                'id' => 'http://' . tenant('id') . '.example.com/product/p2',
                'name' => 'Sample Product 2',
                'description' => 'Description for product 2',
                'price' => 149.99,
                'category' => 'Books',
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }
    }
}

// Run for a specific tenant:
// php artisan tenants:seed --tenants=acme-corp

// ============================================================================
// EXAMPLE 8: Artisan Command for Tenant Management
// ============================================================================

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;

class CheckTenantSparqlHealth extends Command
{
    protected $signature = 'tenant:check-sparql {tenant?}';
    protected $description = 'Check SPARQL endpoint health for tenant(s)';

    public function handle()
    {
        $tenantId = $this->argument('tenant');

        $tenants = $tenantId
            ? [Tenant::find($tenantId)]
            : Tenant::all();

        foreach ($tenants as $tenant) {
            if (!$tenant->hasSparqlEndpoint()) {
                $this->warn("Tenant {$tenant->id} has no SPARQL endpoint configured");
                continue;
            }

            try {
                tenancy()->initialize($tenant);

                // Simple ASK query to check connectivity
                $result = \DB::connection('sparql')->select('ASK { ?s ?p ?o }');

                $this->info("✓ Tenant {$tenant->id}: SPARQL endpoint is healthy");
            } catch (\Exception $e) {
                $this->error("✗ Tenant {$tenant->id}: SPARQL endpoint error - {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }
    }
}

// ============================================================================
// EXAMPLE 9: Testing Multi-Tenant SPARQL
// ============================================================================

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantSparqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_tenants_use_different_endpoints()
    {
        $tenant1 = Tenant::create([
            'id' => 'tenant1',
            'sparql_endpoint' => 'http://localhost:3030/tenant1/sparql',
            'sparql_implementation' => 'fuseki',
        ]);

        $tenant2 = Tenant::create([
            'id' => 'tenant2',
            'sparql_endpoint' => 'http://localhost:3030/tenant2/sparql',
            'sparql_implementation' => 'fuseki',
        ]);

        // Create product for tenant1
        tenancy()->initialize($tenant1);
        Product::create([
            'id' => 'http://tenant1.example.com/product/1',
            'name' => 'Tenant 1 Product',
            'price' => 100,
        ]);
        tenancy()->end();

        // Verify tenant2 cannot see tenant1's products
        tenancy()->initialize($tenant2);
        $products = Product::all();
        $this->assertCount(0, $products);
        tenancy()->end();
    }

    public function test_tenant_sparql_crud_operations()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant',
            'sparql_endpoint' => 'http://localhost:3030/test/sparql',
            'sparql_implementation' => 'fuseki',
        ]);

        tenancy()->initialize($tenant);

        // Create
        $product = Product::create([
            'id' => 'http://test.example.com/product/1',
            'name' => 'Test Product',
            'price' => 99.99,
        ]);

        // Read
        $found = Product::find('http://test.example.com/product/1');
        $this->assertEquals('Test Product', $found->name);

        // Update
        $found->price = 89.99;
        $found->save();
        $updated = Product::find('http://test.example.com/product/1');
        $this->assertEquals(89.99, $updated->price);

        // Delete
        $found->delete();
        $deleted = Product::find('http://test.example.com/product/1');
        $this->assertNull($deleted);

        tenancy()->end();
    }
}
