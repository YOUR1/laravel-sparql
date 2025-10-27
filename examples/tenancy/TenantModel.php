<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

/**
 * Example Tenant model with SPARQL endpoint support.
 *
 * This model demonstrates how to configure a tenant with SPARQL
 * endpoint information for use with the SPARQLTenancyBootstrapper.
 *
 * Usage:
 * 1. Copy this to your app/Models directory (or modify your existing Tenant model)
 * 2. Add the SPARQL-related fields to $fillable and $casts
 * 3. Create tenants with SPARQL endpoint information
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        // Standard tenancy fields
        'data',

        // SPARQL endpoint configuration fields
        'sparql_endpoint',           // Required: SPARQL query endpoint URL
        'sparql_implementation',     // Optional: fuseki|blazegraph|generic
        'sparql_update_endpoint',    // Optional: Separate update endpoint
        'sparql_graph',              // Optional: Default graph URI
        'sparql_auth',               // Optional: Auth config array
        'sparql_namespaces',         // Optional: RDF namespaces array
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sparql_auth' => 'array',       // Cast JSON to array
        'sparql_namespaces' => 'array', // Cast JSON to array
    ];

    /**
     * Example: Create a tenant with Fuseki endpoint.
     */
    public static function createWithFuseki(string $id, string $endpointUrl, ?string $graph = null): self
    {
        return static::create([
            'id' => $id,
            'sparql_endpoint' => $endpointUrl,
            'sparql_implementation' => 'fuseki',
            'sparql_graph' => $graph,
        ]);
    }

    /**
     * Example: Create a tenant with Blazegraph endpoint and authentication.
     */
    public static function createWithBlazegraph(
        string $id,
        string $endpointUrl,
        ?string $username = null,
        ?string $password = null
    ): self {
        $data = [
            'id' => $id,
            'sparql_endpoint' => $endpointUrl,
            'sparql_implementation' => 'blazegraph',
        ];

        if ($username && $password) {
            $data['sparql_auth'] = [
                'type' => 'digest',
                'username' => $username,
                'password' => $password,
            ];
        }

        return static::create($data);
    }

    /**
     * Example: Create a tenant with generic SPARQL endpoint (GraphDB, Virtuoso, etc.)
     */
    public static function createWithGenericEndpoint(
        string $id,
        string $endpointUrl,
        array $namespaces = []
    ): self {
        return static::create([
            'id' => $id,
            'sparql_endpoint' => $endpointUrl,
            'sparql_implementation' => 'generic',
            'sparql_namespaces' => $namespaces,
        ]);
    }

    /**
     * Check if tenant has SPARQL endpoint configured.
     */
    public function hasSparqlEndpoint(): bool
    {
        return !empty($this->sparql_endpoint);
    }

    /**
     * Get the full SPARQL connection configuration for this tenant.
     */
    public function getSparqlConnectionConfig(): array
    {
        if (!$this->hasSparqlEndpoint()) {
            return [];
        }

        $config = [
            'driver' => 'sparql',
            'host' => $this->sparql_endpoint,
            'endpoint' => $this->sparql_endpoint,
            'implementation' => $this->sparql_implementation ?? 'fuseki',
        ];

        if ($this->sparql_update_endpoint) {
            $config['update_endpoint'] = $this->sparql_update_endpoint;
        }

        if ($this->sparql_graph) {
            $config['graph'] = $this->sparql_graph;
        }

        if ($this->sparql_auth) {
            $config['auth'] = $this->sparql_auth;
        }

        if ($this->sparql_namespaces) {
            $config['namespaces'] = $this->sparql_namespaces;
        }

        return $config;
    }
}
