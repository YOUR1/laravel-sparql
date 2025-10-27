<?php

namespace Stancl\Tenancy\Contracts;

/**
 * Stub interface for testing purposes.
 * This mirrors the actual interface from stancl/tenancy.
 */
interface TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant): void;

    public function revert(): void;
}
