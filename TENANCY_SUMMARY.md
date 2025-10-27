# Multi-Tenancy Support Summary

This document summarizes the stancl/tenancy integration added to laravel-sparql.

## Key Design Decision: Graph-Based Tenancy First

Based on user feedback, the implementation now **prioritizes graph-based tenancy** over endpoint-based tenancy. This aligns with SPARQL best practices and is more efficient for most use cases.

### Graph-Based Tenancy (Primary Pattern)
- **One shared SPARQL endpoint** for all tenants
- **Unique named graph URI per tenant** for data isolation
- More efficient resource usage
- Standard SPARQL multi-tenancy pattern
- Easier to manage and monitor

### Endpoint-Based Tenancy (Secondary Pattern)
- **Optional**: Each tenant can have their own SPARQL endpoint
- Use when complete infrastructure isolation is required
- More resource-intensive

## What Was Implemented

### 1. Core Bootstrapper (`src/Tenancy/SPARQLTenancyBootstrapper.php`)
- Implements `Stancl\Tenancy\Contracts\TenancyBootstrapper`
- **Requires `sparql_graph` attribute** on all tenants
- Optionally supports `sparql_endpoint` for endpoint-based tenancy
- Gracefully handles connection errors during bootstrap/revert
- Properly merges tenant config with central config

### 2. Comprehensive Unit Tests (`tests/Unit/SPARQLTenancyBootstrapperTest.php`)
**11 passing tests covering:**
- Graph-based tenancy (switches only graph, preserves endpoint)
- Endpoint-based tenancy (switches both endpoint and graph)
- Authentication handling
- Custom namespaces
- Separate update endpoints
- Multiple bootstrap/revert cycles
- Configuration preservation and restoration
- Edge cases (missing graph, etc.)

### 3. Test Infrastructure (`tests/Stubs/`)
- Created stub interfaces for `TenancyBootstrapper` and `Tenant`
- Allows testing without requiring stancl/tenancy as a dependency
- Configured in composer.json autoload-dev

### 4. Documentation (`docs/TENANCY.md`)
**Complete guide including:**
- Overview of both tenancy patterns with recommendations
- Installation and configuration steps
- Tenant model setup
- Usage examples for both patterns
- Migration guide for existing applications
- Advanced scenarios (custom bootstrappers, multiple connections)
- Testing strategies
- Troubleshooting guide
- Best practices

### 5. Example Files (`examples/tenancy/`)
- **Migration** (`add_sparql_to_tenants_table.php`) - Adds SPARQL columns to tenants table with clear comments about what's required vs optional
- **Tenant Model** (`TenantModel.php`) - Enhanced model with helper methods
- **Usage Examples** (`example_usage.php`) - 9 real-world scenarios

### 6. Package Configuration Updates
- Added `"multi-tenancy"` keyword to composer.json
- Added stancl/tenancy as suggested dependency
- Updated README.md to highlight multi-tenancy support
- Added link to tenancy guide in documentation section

## Simple Example

```php
// 1. Configure central SPARQL endpoint in config/database.php
'sparql' => [
    'driver' => 'sparql',
    'endpoint' => 'http://localhost:3030/shared/sparql',
    'implementation' => 'fuseki',
],

// 2. Create tenants with graph URIs
$tenant1 = Tenant::create([
    'id' => 'acme-corp',
    'sparql_graph' => 'http://acme.example.com/graph',
]);

$tenant2 = Tenant::create([
    'id' => 'widgets-inc',
    'sparql_graph' => 'http://widgets.example.com/graph',
]);

// 3. Use in tenant context - automatically switches to tenant's graph
tenancy()->initialize($tenant1);
Product::create(['name' => 'Acme Product']); // Stored in acme's graph

tenancy()->initialize($tenant2);
Product::create(['name' => 'Widget Product']); // Stored in widgets' graph
```

## Benefits

1. **Efficient**: Shares SPARQL endpoint infrastructure across tenants
2. **Standard**: Follows SPARQL/RDF multi-tenancy best practices
3. **Flexible**: Still supports endpoint-based tenancy when needed
4. **Simple**: Minimal configuration required (just add `sparql_graph`)
5. **Safe**: Comprehensive tests ensure reliability
6. **Well-Documented**: Complete guide with examples

## Migration Path

Existing laravel-sparql applications can add multi-tenancy by:

1. Installing stancl/tenancy
2. Adding the SPARQLTenancyBootstrapper to config/tenancy.php
3. Running the migration to add sparql_graph column
4. Creating tenants with graph URIs
5. No changes to existing SPARQL models required

## Test Results

All 11 unit tests pass:
- ✓ Graph based tenancy switches only graph
- ✓ Endpoint based tenancy switches endpoint and graph
- ✓ Bootstrap with authentication
- ✓ Bootstrap with custom namespaces and endpoint
- ✓ Bootstrap with separate update endpoint
- ✓ Bootstrap does nothing when no graph configured
- ✓ Revert restores original configuration
- ✓ Multiple bootstrap revert cycles with graph based tenancy
- ✓ Multiple bootstrap revert cycles with endpoint based tenancy
- ✓ Graph based tenancy preserves namespaces from central config
- ✓ Tenant can override namespaces

## Files Changed/Added

### Added Files
- `src/Tenancy/SPARQLTenancyBootstrapper.php`
- `tests/Unit/SPARQLTenancyBootstrapperTest.php`
- `tests/Stubs/TenancyBootstrapper.php`
- `tests/Stubs/Tenant.php`
- `docs/TENANCY.md`
- `examples/tenancy/add_sparql_to_tenants_table.php`
- `examples/tenancy/TenantModel.php`
- `examples/tenancy/example_usage.php`

### Modified Files
- `composer.json` - Added keywords, suggest, autoload-dev
- `README.md` - Added multi-tenancy feature highlight and docs link

## Next Steps for Users

1. Read `docs/TENANCY.md` for complete setup instructions
2. Review `examples/tenancy/` for implementation examples
3. Run the migration to add SPARQL columns to tenants table
4. Start creating tenants with graph URIs
5. Enjoy automatic tenant isolation in your SPARQL models!
