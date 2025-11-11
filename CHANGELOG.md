# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.7] - 2025-11-11
### Fixed
- Fixed an issue in Expression class

## [1.2.4/5/6] - 2025-10-30

### Fixed
- **Unicode Character Encoding in N-Triples** - Fixed corruption of non-ASCII characters (accented letters, special characters) when syncing data to triple stores like Blazegraph. The issue occurred because some triple stores don't properly respect the `charset=utf-8` parameter in Content-Type headers and interpret UTF-8 data as ISO-8859-1 (Latin-1) by default.

  **Solution**: Following the N-Triples specification, non-ASCII characters (code points > U+007F) are now automatically escaped as Unicode sequences (`\uXXXX` for code points up to U+FFFF, `\UXXXXXXXX` for higher code points). For example, "ruïne" is now serialized as "ru\u00EFne" in N-Triples format.

  **Before**: Characters like ï, ë, ñ, ø would display as corrupted characters (��) in the triple store
  **After**: All Unicode characters are correctly preserved and displayed

  Files changed:
    - `src/Support/StringHelper.php`: Updated `escapeSparqlLiteral()` to escape non-ASCII characters as Unicode sequences
    - `src/Connection.php`: Added `charset=utf-8` to Content-Type header as best practice (though not relied upon)

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
