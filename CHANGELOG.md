# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.3] - 2025-10-30

### Added
- **Query Builder: Analytical Query Support** - Added support for complex analytical queries with custom SELECT expressions, BIND clauses, and GROUP BY with computed columns. This enables building sophisticated SPARQL queries using Laravel's fluent query builder syntax.

  **New Methods:**
  - `selectExpression($expression)` - Add custom SELECT expressions (aggregates, computed values)
  - `whereTriple($subject, $predicate, $object)` - Add explicit triple patterns to WHERE clause
  - `bind($expression, $variable)` - Add BIND expressions for computed values
  - Enhanced `groupBy()` to support raw SPARQL variables (e.g., `?language`)

  **Example - Language Statistics:**
  ```php
  $stats = DB::connection('sparql')
      ->query()
      ->selectExpression('?language')
      ->selectExpression('(COUNT(?label) as ?count)')
      ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
      ->whereTriple('?concept', 'skos:prefLabel', '?label')
      ->bind('COALESCE(LANG(?label), "no-lang")', '?language')
      ->groupBy('?language')
      ->get();
  ```

  Files changed:
  - `src/Query/Builder.php`: Added `selectExpression()`, `whereTriple()`, `bind()` methods and new properties
  - `src/Query/Grammar.php`: Added `compileBinds()`, `whereTriple()`, updated `compileColumns()` to handle SELECT expressions
  - `tests/Unit/AnalyticalQueriesTest.php`: Comprehensive test suite with 10 tests covering all new features
  - `examples/analytical-queries.php`: Usage examples and documentation

- **Documentation**: Added `examples/analytical-queries.php` with comprehensive examples showing:
  - Language statistics queries
  - Type statistics queries
  - Complex analytical queries with HAVING and ORDER BY
  - Multiple BIND expressions
  - DB::raw() support
  - Backward compatibility examples

### Fixed
- **Grammar: Fixed predicate URI wrapping in WHERE clauses** - Predicates (properties) in WHERE clauses are now properly wrapped in angle brackets (`<URI>`), which is required by SPARQL specification. This fixes issues where queries with `Expression::iri()` in WHERE clauses would fail with "HTTP request for SPARQL query failed" errors, particularly when using Blazegraph namespaces with count() and other aggregates.

  **Before**: `?subject http://example.com/property <http://example.com/value>`
  **After**: `?subject <http://example.com/property> <http://example.com/value>`

  Files changed:
  - `src/Query/Grammar.php`: Added `wrapUri()` calls in `whereBasic()` and `whereReversed()` methods

### Changed
- **Tests**: Updated test assertions to expect properly wrapped URIs instead of prefixed forms in generated SPARQL queries
- **Query Builder**: Enhanced `groupBy()` method to properly handle SPARQL variables (strings starting with `?`)

### Technical Details
- Maintains full backward compatibility - all existing queries continue to work
- Supports Laravel's `DB::raw()` for complex expressions
- Supports namespace prefix expansion in `whereTriple()`
- All 514 unit tests passing

## [Previous Releases]

See git history for changes prior to this changelog.
