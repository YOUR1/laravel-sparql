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

- PHP 8.0 or higher
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
# Unit tests only
./vendor/bin/phpunit tests/Unit

# Feature tests only
./vendor/bin/phpunit tests/Feature

# With coverage report
composer test -- --coverage-html coverage/
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
│   └── Eloquent/
│       ├── Model.php         # Base SPARQL model
│       ├── Builder.php       # Query builder
│       ├── Connection.php    # SPARQL connection
│       └── Grammar.php       # SPARQL grammar
├── tests/                    # Test suite
│   ├── Unit/                 # Unit tests
│   └── Feature/              # Feature tests
├── docs/                     # Documentation
│   ├── USAGE.md             # Usage guide
│   ├── API.md               # API reference
│   └── DEVELOPMENT.md       # This file
├── phpunit.xml              # PHPUnit configuration
├── phpstan.neon             # PHPStan configuration
└── pint.json                # Laravel Pint configuration
```

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
