# Laravel SPARQL - TODO

## Query Builder Enhancements

### Support for Complex SELECT Expressions with BIND + GROUP BY

**Priority:** Medium
**Status:** Pending
**Complexity:** High

#### Current Limitation

The query builder currently doesn't support complex analytical queries that combine:
- Custom SELECT expressions with BIND clauses
- GROUP BY with computed columns
- Aggregate functions (COUNT, SUM, etc.) in the same query

**Example of what doesn't work:**
```php
// This fails - selectRaw is interpreted as triple patterns
$results = DB::connection('sparql')
    ->graph('')
    ->namespace($namespace)
    ->table($conceptIri)
    ->selectRaw('(COALESCE(LANG(?label), "no-lang") as ?language) (COUNT(?label) as ?count)')
    ->where($inSchemeRelationIri, Expression::iri($schemeIri))
    ->whereRaw('?concept <prefLabel> ?label')
    ->groupBy('?language')
    ->get();
```

**Current workaround:**
```php
// Raw SPARQL works fine
$results = DB::connection('sparql')->graph('')->namespace($namespace)->select("
    SELECT ?language (COUNT(?label) as ?count)
    WHERE {
        ?concept <inScheme> <scheme> .
        ?concept <prefLabel> ?label .
        BIND(COALESCE(LANG(?label), 'no-lang') as ?language)
    }
    GROUP BY ?language
");
```

#### Proposed Enhancement

Add support for analytical queries similar to Laravel's Eloquent query builder:

```php
$results = DB::connection('sparql')
    ->graph('')
    ->namespace($namespace)
    ->select([
        DB::raw('(COALESCE(LANG(?label), "no-lang") as ?language)'),
        DB::raw('COUNT(?label) as ?count')
    ])
    ->whereTriple('?concept', $inSchemeRelationIri, Expression::iri($schemeIri))
    ->whereTriple('?concept', $prefLabelIri, '?label')
    ->groupBy('?language')
    ->get();
```

#### Implementation Considerations

1. **Separate triple patterns from SELECT expressions**
   - Current `selectRaw()` is interpreted as triple patterns
   - Need a way to distinguish SELECT clause modifications from WHERE clause patterns

2. **New methods needed:**
   - `selectExpression()` or `addSelect()` for custom SELECT expressions
   - `whereTriple()` to explicitly add triple patterns without ambiguity
   - `bind()` for BIND clauses in WHERE

3. **Grammar changes:**
   - Modify `Grammar::compileSelect()` to handle both triple patterns and custom SELECT expressions
   - Support BIND clauses in WHERE block
   - Ensure proper variable scoping between SELECT, WHERE, and GROUP BY

4. **Backward compatibility:**
   - Existing `selectRaw()` behavior must be preserved
   - Consider using method chaining to detect intent

#### Use Cases

**Statistics by language:**
```php
// Count labels by language
$labelStats = DB::connection('sparql')
    ->namespace($namespace)
    ->selectExpression('(COALESCE(LANG(?label), "no-lang") as ?language)')
    ->selectExpression('COUNT(?label) as ?count')
    ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
    ->whereTriple('?concept', 'skos:prefLabel', '?label')
    ->groupBy('?language')
    ->get();
```

**Type statistics:**
```php
// Count resources by type
$typeStats = DB::connection('sparql')
    ->namespace($namespace)
    ->select(['?type', DB::raw('COUNT(DISTINCT ?concept) as ?count')])
    ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
    ->whereTriple('?concept', 'rdf:type', '?type')
    ->groupBy('?type')
    ->get();
```

**Computed aggregates:**
```php
// Average age by city with age groups
$ageStats = Person::select([
    '?city',
    DB::raw('(IF(AVG(?age) > 50, "senior", "adult") as ?ageGroup)'),
    DB::raw('AVG(?age) as ?avgAge'),
    DB::raw('COUNT(?person) as ?count')
])
->groupBy('?city', '?ageGroup')
->having('?count', '>', 10)
->get();
```

#### Related Files

- `src/Query/Builder.php` - Main query builder
- `src/Query/Grammar.php` - SPARQL grammar compilation
- `src/Query/Concerns/BuildsSparqlFunctions.php` - SPARQL function helpers (already has `lang()`, `coalesce()`)
- `tests/Unit/BlazegraphQueryBuilderTest.php` - Test suite documenting current behavior

#### References

- Current working implementation: `/Users/youri/Development/Herd/Hiero/app/Modules/SkosConceptBrowser/Services/ConceptSchemeFilter.php:129-224` (uses raw SPARQL as workaround)
- Test cases: `/Users/youri/Development/Laravel/laravel-sparql/tests/Unit/BlazegraphQueryBuilderTest.php`

#### Notes

- This is a nice-to-have enhancement
- Raw SPARQL `select()` is a perfectly valid workaround for complex queries
- The query builder excels at simple queries; raw SPARQL is appropriate for analytical queries
- Consider if this adds too much complexity for limited benefit
