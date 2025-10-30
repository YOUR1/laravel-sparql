# Development & Testing Guide

This guide covers setup, testing, and development for Laravel SPARQL contributors.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Local Setup](#local-setup)
- [Running Tests](#running-tests)
- [Fuseki Server](#fuseki-server)
- [Code Quality](#code-quality)
- [Performance](#performance)
- [Contributing](#contributing)

## Prerequisites

- PHP 8.2 or higher
- Composer
- Docker and Docker Compose (for SPARQL endpoint)

## Local Setup

### 1. Clone and Install

```bash
git clone https://github.com/YOUR1/laravel-sparql.git
cd laravel-sparql

composer install
```

### 2. Create Environment File

```bash
cp .env.example .env
```

### 3. Start SPARQL Endpoint

The project includes a Docker Compose setup for Apache Jena Fuseki:

```bash
# Quick start with script
./setup-fuseki.sh

# Or manually start
docker-compose up -d
```

This starts:
- **Fuseki Server**: http://localhost:3030
- **Admin UI**: http://localhost:3030 (username: `admin`, password: `admin`)
- **SPARQL Endpoint**: http://localhost:3030/test/sparql
- **Dataset**: `test`

## Running Tests

### All Tests

```bash
composer test
```

### Test Suites

Run specific test suites:

```bash
# Unit tests only (fast, no SPARQL endpoint required)
composer test:unit

# Feature tests only (full integration suite)
composer test:feature

# Smoke tests only (critical functionality validation)
composer test:smoke

# With coverage report
composer test:coverage
```

### Testing Different Triple Store Implementations

The package supports multiple SPARQL implementations (Fuseki, Blazegraph, Virtuoso, GraphDB, etc.) through adapters. Each implementation has slightly different conventions for Graph Store Protocol.

#### Test Strategy

**Unit Tests** (tests/Unit/TripleStore/) - Fast, no dependencies
- Tests adapter-specific logic (endpoint derivation, URL building, response parsing)
- Run automatically with `composer test:unit`
- Always run these first - they catch 90% of adapter issues

**Integration Tests** (tests/Feature/) - Full test suite against ONE implementation
- Complete end-to-end testing (441 tests)
- Default: Runs against Fuseki at http://localhost:3030
- Takes ~1-2 seconds to complete

**Smoke Tests** (tests/Smoke/) - Critical functionality across ALL implementations
- ~18 essential tests covering CRUD, batch operations, and relationships
- Quick validation (completes in <1 second per implementation)
- Ensures basic compatibility with all triple stores

#### Running Tests Against Specific Implementations

**Fuseki** (default):
```bash
composer test:fuseki
# or
composer test:smoke:fuseki
```

**Blazegraph**:
```bash
# Make sure Blazegraph is running (port 9090)
docker-compose up -d blazegraph

# Run full suite
composer test:blazegraph

# Or just smoke tests
composer test:smoke:blazegraph
```

**All Implementations** (smoke tests only):
```bash
composer test:smoke:all
```

#### Setting Up Blazegraph

The docker-compose.yml includes both Fuseki and Blazegraph:

```bash
# Start both servers
docker-compose up -d

# Check status
docker-compose ps

# View Blazegraph UI
# http://localhost:9090/bigdata/
```

#### Manual Testing Against Other Implementations

For Virtuoso, GraphDB, Stardog, or Amazon Neptune:

```bash
# Set environment variables
export SPARQL_ENDPOINT=http://your-endpoint:port/sparql
export SPARQL_IMPLEMENTATION=generic

# Run smoke tests
composer test:smoke
```

### Specific Test

```bash
./vendor/bin/phpunit tests/Unit/ModelTest.php

# Specific test method
./vendor/bin/phpunit tests/Unit/ModelTest.php --filter testCreate
```

### Test Configuration

Tests are configured in `phpunit.xml`. Key settings:

- **Test Endpoint**: Uses Docker Compose SPARQL endpoint at `http://localhost:3030/test/sparql`
- **Database**: Uses `sparql` connection from test config
- **Coverage**: Configured for minimum 80% coverage

## Fuseki Server

### Access

- **Admin UI**: http://localhost:3030
  - Username: `admin`
  - Password: `admin`
- **SPARQL Query Endpoint**: http://localhost:3030/test/sparql
- **SPARQL Update Endpoint**: http://localhost:3030/test/update
- **Dataset**: `test`

### Manual Commands

```bash
# Start Fuseki
docker-compose up -d

# View logs
docker-compose logs -f fuseki

# Stop Fuseki
docker-compose down

# Stop and remove all data
docker-compose down -v

# Restart Fuseki
docker-compose restart fuseki
```

### Dataset Management

The `test` dataset is automatically created on startup. To reset it:

```bash
# Stop and remove all volumes
docker-compose down -v

# Start fresh
docker-compose up -d
```

## Code Quality

### Static Analysis

```bash
composer analyse
```

This runs:
- **PHPStan**: Static analysis for type checking (level 8)
- **Laravel Pint**: Code style fixing

### Code Style

```bash
# Check style
composer pint:check

# Auto-fix style
composer pint
```

### Testing Requirements

Before committing:

1. All tests pass: `composer test`
2. Code analysis passes: `composer analyse`
3. Code style follows PSR-12: `composer pint`

## Performance

### Benchmarking

Performance characteristics you can expect:

- **Batch Operations**: Insert/delete 1000 records in < 2 seconds
- **Efficient Queries**: Minimal overhead over native SPARQL
- **Memory Efficient**: < 128MB for 10,000 records
- **No External Dependencies**: No ontology downloads or API calls during operation

### Memory Usage

Monitor memory usage during tests:

```bash
./vendor/bin/phpunit tests/Feature --track-memory
```

## Contributing

### Development Workflow

1. **Create a branch** from `master`:
   ```bash
   git checkout -b feature/my-feature
   ```

2. **Make changes** and add tests

3. **Run tests and analysis**:
   ```bash
   composer test
   composer analyse
   composer pint
   ```

4. **Commit with clear messages**:
   ```bash
   git commit -m "Add feature: description"
   ```

5. **Create a pull request** against `master`

### Adding Features

When adding new features:

1. **Write tests first** (TDD)
2. **Implement the feature**
3. **Update documentation** in `docs/USAGE.md` or `docs/API.md`
4. **Ensure tests pass** with >= 80% coverage
5. **Update CHANGELOG.md** with your changes

### Fixing Bugs

When fixing bugs:

1. **Add a failing test** that reproduces the bug
2. **Fix the bug** to make the test pass
3. **Update documentation** if behavior changes
4. **Update CHANGELOG.md**

### Code Standards

- Follow PSR-12 coding standards (enforced by Laravel Pint)
- Write descriptive commit messages
- Add docblocks to public methods
- Include type hints
- Keep methods focused and small
- Add tests for all functionality

## Directory Structure

```
laravel-sparql/
├── src/                      # Source code
│   ├── Eloquent/
│   │   ├── Model.php         # Base SPARQL model
│   │   ├── Builder.php       # Query builder
│   │   └── ...
│   ├── Query/
│   │   ├── Builder.php       # Query builder
│   │   ├── Grammar.php       # SPARQL grammar
│   │   └── ...
│   ├── TripleStore/          # Triple store adapters
│   │   ├── FusekiAdapter.php
│   │   ├── BlazegraphAdapter.php
│   │   └── GenericAdapter.php
│   └── Connection.php        # SPARQL connection
├── tests/                    # Test suite
│   ├── Unit/                 # Unit tests (fast, no dependencies)
│   │   ├── TripleStore/      # Adapter-specific tests
│   │   │   ├── FusekiAdapterTest.php      (19 tests)
│   │   │   ├── BlazegraphAdapterTest.php  (22 tests)
│   │   │   └── GenericAdapterTest.php     (23 tests)
│   │   └── ...               # Model, Builder, Grammar tests
│   ├── Feature/              # Integration tests (requires SPARQL endpoint)
│   │   └── ...               # Full end-to-end tests (441 tests)
│   ├── Smoke/                # Critical functionality tests
│   │   ├── BasicCrudTest.php         (7 tests)
│   │   ├── BatchOperationsTest.php   (5 tests)
│   │   └── RelationshipsTest.php     (6 tests)
│   ├── IntegrationTestCase.php
│   └── TestCase.php
├── docs/                     # Documentation
│   ├── USAGE.md             # Usage guide
│   ├── API.md               # API reference
│   └── DEVELOPMENT.md       # This file
├── phpunit.xml              # PHPUnit configuration
├── phpstan.neon             # PHPStan configuration
└── pint.json                # Laravel Pint configuration
```

### Test Organization

**Unit Tests** (`tests/Unit/`)
- Fast execution, no external dependencies
- Mock SPARQL connections where needed
- Test individual components in isolation
- ~60 tests covering models, builders, grammars, adapters

**Feature Tests** (`tests/Feature/`)
- Full integration testing with real SPARQL endpoint
- Tests complete workflows end-to-end
- Requires Fuseki running on localhost:3030
- ~441 tests covering all package functionality

**Smoke Tests** (`tests/Smoke/`)
- Essential functionality validation
- Quick checks across different implementations
- ~18 tests covering CRUD, batches, relationships
- Designed to run against any SPARQL implementation

## Troubleshooting

### Tests Fail - Connection Refused

**Problem**: `Could not resolve host: localhost:3030`

**Solution**: Start Fuseki server:
```bash
docker-compose up -d
```

### Tests Fail - Dataset Not Found

**Problem**: `Dataset /test not found`

**Solution**: Reset Fuseki data:
```bash
docker-compose down -v
docker-compose up -d
```

### Memory Issues

**Problem**: Tests fail with memory exhaustion

**Solution**: Increase PHP memory limit:
```bash
php -d memory_limit=512M ./vendor/bin/phpunit tests/
```

### Port Already in Use

**Problem**: `Error starting userland proxy: listen tcp 0.0.0.0:3030: bind: address already in use`

**Solution**: Stop the container or change port:
```bash
# Stop container using port 3030
docker-compose down

# Or change port in docker-compose.yml
```

## Resources

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [SPARQL 1.1 Specification](https://www.w3.org/TR/sparql11-query/)
- [Apache Jena Fuseki](https://jena.apache.org/documentation/fuseki2/)
