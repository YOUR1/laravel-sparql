<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Tenancy\SPARQLTenancyBootstrapper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use Mockery;

/**
 * Tests for SPARQLTenancyBootstrapper
 *
 * Note: These tests mock the Tenant interface since stancl/tenancy
 * is not included as a dependency of this package.
 *
 * This test suite covers both graph-based and endpoint-based tenancy patterns.
 */
class SPARQLTenancyBootstrapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up default SPARQL connection config
        Config::set('database.connections.sparql', [
            'driver' => 'sparql',
            'host' => 'http://localhost:3030/default/sparql',
            'endpoint' => 'http://localhost:3030/default/sparql',
            'implementation' => 'fuseki',
            'graph' => 'http://default.example.com/graph',
        ]);

        Config::set('tenancy.sparql.connection_name', 'sparql');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_graph_based_tenancy_switches_only_graph()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $tenant = $this->createMockTenant([
            'sparql_graph' => 'http://tenant1.example.com/graph',
            // No sparql_endpoint - uses shared endpoint
        ]);

        // Store original config
        $originalConfig = config('database.connections.sparql');

        // Bootstrap tenancy
        $bootstrapper->bootstrap($tenant);

        // Assert only graph was changed, endpoint remains the same
        $newConfig = config('database.connections.sparql');
        $this->assertEquals('http://tenant1.example.com/graph', $newConfig['graph']);
        $this->assertEquals($originalConfig['host'], $newConfig['host']); // Endpoint unchanged
        $this->assertEquals($originalConfig['implementation'], $newConfig['implementation']);

        // Revert
        $bootstrapper->revert();

        // Assert config was restored
        $revertedConfig = config('database.connections.sparql');
        $this->assertEquals($originalConfig['graph'], $revertedConfig['graph']);
    }

    public function test_endpoint_based_tenancy_switches_endpoint_and_graph()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $tenant = $this->createMockTenant([
            'sparql_endpoint' => 'http://tenant1.example.com:3030/tenant1/sparql',
            'sparql_implementation' => 'blazegraph',
            'sparql_graph' => 'http://tenant1.example.com/graph',
        ]);

        // Store original config
        $originalConfig = config('database.connections.sparql');

        // Bootstrap tenancy
        $bootstrapper->bootstrap($tenant);

        // Assert both endpoint and graph were changed
        $newConfig = config('database.connections.sparql');
        $this->assertEquals('http://tenant1.example.com:3030/tenant1/sparql', $newConfig['host']);
        $this->assertEquals('http://tenant1.example.com/graph', $newConfig['graph']);
        $this->assertEquals('blazegraph', $newConfig['implementation']);

        // Revert
        $bootstrapper->revert();

        // Assert config was restored
        $revertedConfig = config('database.connections.sparql');
        $this->assertEquals($originalConfig['host'], $revertedConfig['host']);
        $this->assertEquals($originalConfig['graph'], $revertedConfig['graph']);
    }

    public function test_bootstrap_with_authentication()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $tenant = $this->createMockTenant([
            'sparql_graph' => 'http://secure.example.com/graph',
            'sparql_endpoint' => 'http://secure.example.com:3030/sparql',
            'sparql_implementation' => 'blazegraph',
            'sparql_auth' => [
                'type' => 'digest',
                'username' => 'admin',
                'password' => 'secret',
            ],
        ]);

        $bootstrapper->bootstrap($tenant);

        $newConfig = config('database.connections.sparql');
        $this->assertArrayHasKey('auth', $newConfig);
        $this->assertEquals('digest', $newConfig['auth']['type']);
        $this->assertEquals('admin', $newConfig['auth']['username']);

        $bootstrapper->revert();
    }

    public function test_bootstrap_with_custom_namespaces_and_endpoint()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $tenant = $this->createMockTenant([
            'sparql_graph' => 'http://tenant.example.com/graph',
            'sparql_endpoint' => 'http://tenant.example.com:3030/sparql',
            'sparql_implementation' => 'fuseki',
            'sparql_namespaces' => [
                'custom' => 'http://custom.example.com/vocab/',
                'schema' => 'http://schema.org/',
            ],
        ]);

        $bootstrapper->bootstrap($tenant);

        $newConfig = config('database.connections.sparql');
        $this->assertArrayHasKey('namespaces', $newConfig);
        $this->assertEquals('http://custom.example.com/vocab/', $newConfig['namespaces']['custom']);

        $bootstrapper->revert();
    }

    public function test_bootstrap_with_separate_update_endpoint()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $tenant = $this->createMockTenant([
            'sparql_graph' => 'http://tenant.example.com/graph',
            'sparql_endpoint' => 'http://tenant.example.com:3030/sparql',
            'sparql_update_endpoint' => 'http://tenant.example.com:3030/update',
            'sparql_implementation' => 'fuseki',
        ]);

        $bootstrapper->bootstrap($tenant);

        $newConfig = config('database.connections.sparql');
        $this->assertArrayHasKey('update_endpoint', $newConfig);
        $this->assertEquals('http://tenant.example.com:3030/update', $newConfig['update_endpoint']);

        $bootstrapper->revert();
    }

    public function test_bootstrap_does_nothing_when_no_graph_configured()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $tenant = $this->createMockTenant([
            // No sparql_graph - required for tenant isolation
            'sparql_endpoint' => 'http://some-endpoint.example.com/sparql',
        ]);

        $originalConfig = config('database.connections.sparql');

        $bootstrapper->bootstrap($tenant);

        // Config should remain unchanged because graph is required
        $newConfig = config('database.connections.sparql');
        $this->assertEquals($originalConfig, $newConfig);

        $bootstrapper->revert();
    }

    public function test_revert_restores_original_configuration()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $originalConfig = config('database.connections.sparql');

        $tenant1 = $this->createMockTenant([
            'sparql_graph' => 'http://tenant1.example.com/graph',
            'sparql_endpoint' => 'http://tenant1.example.com:3030/sparql',
            'sparql_implementation' => 'fuseki',
        ]);

        // Bootstrap first tenant
        $bootstrapper->bootstrap($tenant1);
        $tenant1Config = config('database.connections.sparql');
        $this->assertNotEquals($originalConfig['host'], $tenant1Config['host']);
        $this->assertNotEquals($originalConfig['graph'], $tenant1Config['graph']);

        // Revert
        $bootstrapper->revert();
        $revertedConfig = config('database.connections.sparql');
        $this->assertEquals($originalConfig['host'], $revertedConfig['host']);
        $this->assertEquals($originalConfig['graph'], $revertedConfig['graph']);
    }

    public function test_multiple_bootstrap_revert_cycles_with_graph_based_tenancy()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $originalConfig = config('database.connections.sparql');

        $tenant1 = $this->createMockTenant([
            'sparql_graph' => 'http://tenant1.example.com/graph',
        ]);

        $tenant2 = $this->createMockTenant([
            'sparql_graph' => 'http://tenant2.example.com/graph',
        ]);

        // First cycle
        $bootstrapper->bootstrap($tenant1);
        $this->assertEquals('http://tenant1.example.com/graph', config('database.connections.sparql.graph'));
        $this->assertEquals($originalConfig['host'], config('database.connections.sparql.host')); // Endpoint unchanged
        $bootstrapper->revert();
        $this->assertEquals($originalConfig['graph'], config('database.connections.sparql.graph'));

        // Second cycle
        $bootstrapper->bootstrap($tenant2);
        $this->assertEquals('http://tenant2.example.com/graph', config('database.connections.sparql.graph'));
        $this->assertEquals($originalConfig['host'], config('database.connections.sparql.host')); // Endpoint unchanged
        $bootstrapper->revert();
        $this->assertEquals($originalConfig['graph'], config('database.connections.sparql.graph'));
    }

    public function test_multiple_bootstrap_revert_cycles_with_endpoint_based_tenancy()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $originalConfig = config('database.connections.sparql');

        $tenant1 = $this->createMockTenant([
            'sparql_endpoint' => 'http://tenant1.example.com:3030/sparql',
            'sparql_graph' => 'http://tenant1.example.com/graph',
        ]);

        $tenant2 = $this->createMockTenant([
            'sparql_endpoint' => 'http://tenant2.example.com:3030/sparql',
            'sparql_graph' => 'http://tenant2.example.com/graph',
        ]);

        // First cycle
        $bootstrapper->bootstrap($tenant1);
        $this->assertEquals('http://tenant1.example.com:3030/sparql', config('database.connections.sparql.host'));
        $this->assertEquals('http://tenant1.example.com/graph', config('database.connections.sparql.graph'));
        $bootstrapper->revert();
        $this->assertEquals($originalConfig['host'], config('database.connections.sparql.host'));

        // Second cycle
        $bootstrapper->bootstrap($tenant2);
        $this->assertEquals('http://tenant2.example.com:3030/sparql', config('database.connections.sparql.host'));
        $this->assertEquals('http://tenant2.example.com/graph', config('database.connections.sparql.graph'));
        $bootstrapper->revert();
        $this->assertEquals($originalConfig['host'], config('database.connections.sparql.host'));
    }

    public function test_graph_based_tenancy_preserves_namespaces_from_central_config()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        // Add namespaces to central config
        Config::set('database.connections.sparql.namespaces', [
            'schema' => 'http://schema.org/',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
        ]);

        $tenant = $this->createMockTenant([
            'sparql_graph' => 'http://tenant1.example.com/graph',
        ]);

        $bootstrapper->bootstrap($tenant);

        $newConfig = config('database.connections.sparql');
        // Namespaces should be preserved from original config
        $this->assertArrayHasKey('namespaces', $newConfig);
        $this->assertEquals('http://schema.org/', $newConfig['namespaces']['schema']);

        $bootstrapper->revert();
    }

    public function test_tenant_can_override_namespaces()
    {
        $bootstrapper = new SPARQLTenancyBootstrapper();

        $tenant = $this->createMockTenant([
            'sparql_graph' => 'http://tenant1.example.com/graph',
            'sparql_namespaces' => [
                'custom' => 'http://custom.example.com/vocab/',
            ],
        ]);

        $bootstrapper->bootstrap($tenant);

        $newConfig = config('database.connections.sparql');
        $this->assertArrayHasKey('namespaces', $newConfig);
        $this->assertEquals('http://custom.example.com/vocab/', $newConfig['namespaces']['custom']);

        $bootstrapper->revert();
    }

    /**
     * Create a mock tenant object with the given attributes.
     *
     * @param array $attributes
     * @return \Mockery\MockInterface
     */
    protected function createMockTenant(array $attributes = [])
    {
        $tenant = Mockery::mock('Stancl\Tenancy\Contracts\Tenant');

        // Mock getAttribute method to return values from $attributes
        $tenant->shouldReceive('getAttribute')
            ->andReturnUsing(function ($key) use ($attributes) {
                return $attributes[$key] ?? null;
            });

        return $tenant;
    }
}
