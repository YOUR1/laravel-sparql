<?php

namespace LinkedData\SPARQL\Tenancy;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * SPARQL Tenancy Bootstrapper
 *
 * This bootstrapper makes SPARQL connections tenant-aware by switching to
 * tenant-specific named graphs. This is the recommended approach for SPARQL
 * multi-tenancy as it allows multiple tenants to share the same triple store
 * while maintaining data isolation through graph URIs.
 *
 * Graph-Based Tenancy (Recommended):
 * - All tenants share the same SPARQL endpoint
 * - Each tenant has their own named graph URI
 * - More efficient than separate endpoints
 * - Standard SPARQL multi-tenancy pattern
 *
 * Endpoint-Based Tenancy (Also Supported):
 * - Each tenant can optionally have their own SPARQL endpoint
 * - Useful when tenants need completely separate triple stores
 *
 * Usage:
 * 1. Add this bootstrapper to config/tenancy.php:
 *    'bootstrappers' => [
 *        // ... other bootstrappers
 *        \LinkedData\SPARQL\Tenancy\SPARQLTenancyBootstrapper::class,
 *    ],
 *
 * 2. Define tenant-specific graphs in your Tenant model (required):
 *    protected $casts = [
 *        'sparql_graph' => 'string',  // Required for tenant isolation
 *    ];
 *
 * 3. Optionally configure per-tenant endpoints in config/tenancy.php:
 *    'sparql' => [
 *        'connection_name' => 'sparql',  // default connection to make tenant-aware
 *    ],
 */
class SPARQLTenancyBootstrapper implements TenancyBootstrapper
{
    /**
     * Original connection configuration before tenant bootstrap.
     *
     * @var array<string, mixed>
     */
    private array $originalConfig = [];

    /**
     * Connection name to make tenant-aware.
     */
    private string $connectionName;

    /**
     * Whether the connection was purged during bootstrap.
     */
    private bool $connectionWasPurged = false;

    public function __construct()
    {
        $this->connectionName = config('tenancy.sparql.connection_name', 'sparql');
    }

    /**
     * Bootstrap tenancy by switching the SPARQL connection to the tenant's endpoint.
     */
    public function bootstrap(Tenant $tenant): void
    {
        // Store original configuration
        $this->originalConfig = config("database.connections.{$this->connectionName}", []);

        // Get tenant-specific SPARQL configuration
        $tenantConfig = $this->getTenantSparqlConfig($tenant);

        if (empty($tenantConfig)) {
            return;
        }

        // Merge tenant config with original config
        $newConfig = array_merge($this->originalConfig, $tenantConfig);

        // Set the new configuration
        Config::set("database.connections.{$this->connectionName}", $newConfig);

        // Purge and reconnect if connection already exists
        try {
            $connection = DB::connection($this->connectionName);
            if ($connection->getPdo() !== null) {
                DB::purge($this->connectionName);
                $this->connectionWasPurged = true;
            }

            // Force Laravel to use the new connection configuration
            DB::reconnect($this->connectionName);
        } catch (\Exception $e) {
            // Silently handle connection errors during bootstrap
            // This can happen during testing or if the connection isn't used yet
        }
    }

    /**
     * Revert the SPARQL connection to the original configuration.
     */
    public function revert(): void
    {
        if (empty($this->originalConfig)) {
            return;
        }

        // Restore original configuration
        Config::set("database.connections.{$this->connectionName}", $this->originalConfig);

        // Purge and reconnect to use original config
        if ($this->connectionWasPurged) {
            try {
                DB::purge($this->connectionName);
                DB::reconnect($this->connectionName);
            } catch (\Exception $e) {
                // Silently handle connection errors during revert
            }
        }

        // Clear state
        $this->originalConfig = [];
        $this->connectionWasPurged = false;
    }

    /**
     * Get tenant-specific SPARQL configuration.
     *
     * This method implements graph-based tenancy as the primary approach:
     * - sparql_graph (required) - The named graph URI for this tenant
     * - sparql_endpoint (optional) - Override the default SPARQL endpoint
     * - sparql_implementation (optional) - Override the triple store type
     * - sparql_update_endpoint (optional) - Separate update endpoint
     * - sparql_auth (optional) - Authentication config array
     * - sparql_namespaces (optional) - Custom RDF namespaces array
     *
     * Graph-Based Pattern (Recommended):
     * - Set only 'sparql_graph' on the tenant
     * - All tenants share the central SPARQL endpoint
     * - Data isolated by named graph URI
     *
     * Endpoint-Based Pattern:
     * - Set both 'sparql_endpoint' and 'sparql_graph' on the tenant
     * - Each tenant uses their own SPARQL endpoint
     * - Useful for completely separate triple stores
     *
     * @return array<string, mixed>
     */
    protected function getTenantSparqlConfig(Tenant $tenant): array
    {
        // Graph is required for tenant isolation
        $graph = $tenant->getAttribute('sparql_graph');

        if (empty($graph)) {
            return [];
        }

        $config = [
            'graph' => $graph,
        ];

        // Optional: Override the SPARQL endpoint (for endpoint-based tenancy)
        if ($endpoint = $tenant->getAttribute('sparql_endpoint')) {
            $config['driver'] = 'sparql';
            $config['host'] = $endpoint;
            $config['endpoint'] = $endpoint; // Laravel convention

            // Add implementation if endpoint is overridden
            $config['implementation'] = $tenant->getAttribute('sparql_implementation') ?? 'fuseki';

            // Add optional update endpoint
            if ($updateEndpoint = $tenant->getAttribute('sparql_update_endpoint')) {
                $config['update_endpoint'] = $updateEndpoint;
            }

            // Add optional authentication
            if ($auth = $tenant->getAttribute('sparql_auth')) {
                if (is_array($auth) && isset($auth['type'])) {
                    $config['auth'] = $auth;
                }
            }
        }

        // Add optional namespaces (works for both patterns)
        if ($namespaces = $tenant->getAttribute('sparql_namespaces')) {
            if (is_array($namespaces)) {
                $config['namespaces'] = $namespaces;
            }
        }

        return $config;
    }
}
