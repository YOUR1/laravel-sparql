# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **Grammar: Fixed predicate URI wrapping in WHERE clauses** - Predicates (properties) in WHERE clauses are now properly wrapped in angle brackets (`<URI>`), which is required by SPARQL specification. This fixes issues where queries with `Expression::iri()` in WHERE clauses would fail with "HTTP request for SPARQL query failed" errors, particularly when using Blazegraph namespaces with count() and other aggregates.

  **Before**: `?subject http://example.com/property <http://example.com/value>`
  **After**: `?subject <http://example.com/property> <http://example.com/value>`

  Files changed:
  - `src/Query/Grammar.php`: Added `wrapUri()` calls in `whereBasic()` and `whereReversed()` methods

### Added
- **Documentation**: Added comprehensive examples for using `Expression::iri()` with aggregates (count, sum, avg, etc.) in `docs/USAGE.md`
- **Documentation**: Added examples showing complex Blazegraph namespace queries with multiple IRI conditions
- **Tests**: Added `BlazegraphCountTest` smoke tests to verify count queries work correctly with Blazegraph namespaces

### Changed
- **Tests**: Updated test assertions to expect properly wrapped URIs instead of prefixed forms in generated SPARQL queries

## [Previous Releases]

See git history for changes prior to this changelog.
