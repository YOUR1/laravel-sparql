<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add SPARQL configuration to the tenants table.
 *
 * This migration adds columns to store tenant-specific SPARQL configurations
 * when using stancl/tenancy for multi-tenant applications.
 *
 * Graph-Based Tenancy (Recommended):
 * - Only sparql_graph is required
 * - All tenants share the central SPARQL endpoint
 * - Data isolation through named graphs
 *
 * Endpoint-Based Tenancy (Optional):
 * - Set both sparql_graph and sparql_endpoint
 * - Each tenant uses their own SPARQL endpoint
 *
 * Usage:
 * 1. Copy this file to your database/migrations directory
 * 2. Run: php artisan migrate
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // REQUIRED: Named graph URI for tenant data isolation
            $table->string('sparql_graph')->nullable()->after('id');

            // OPTIONAL: Override default SPARQL endpoint (for endpoint-based tenancy)
            $table->string('sparql_endpoint')->nullable()->after('sparql_graph');

            // Optional: Triple store implementation (fuseki, blazegraph, generic)
            // Only used when sparql_endpoint is set
            $table->string('sparql_implementation')->default('fuseki')->after('sparql_endpoint');

            // Optional: Separate update endpoint if different from query endpoint
            // Only used when sparql_endpoint is set
            $table->string('sparql_update_endpoint')->nullable()->after('sparql_implementation');

            // Optional: Authentication configuration (stored as JSON)
            // Only used when sparql_endpoint is set
            // Example: {"type": "digest", "username": "admin", "password": "secret"}
            $table->json('sparql_auth')->nullable()->after('sparql_update_endpoint');

            // Optional: Custom RDF namespaces (stored as JSON)
            // Works for both graph-based and endpoint-based tenancy
            // Example: {"schema": "http://schema.org/", "foaf": "http://xmlns.com/foaf/0.1/"}
            $table->json('sparql_namespaces')->nullable()->after('sparql_auth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'sparql_graph',
                'sparql_endpoint',
                'sparql_implementation',
                'sparql_update_endpoint',
                'sparql_auth',
                'sparql_namespaces',
            ]);
        });
    }
};
