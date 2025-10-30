<?php

/**
 * Laravel SPARQL - Analytical Queries Examples
 *
 * This file demonstrates the new analytical query features added to support
 * complex SELECT expressions, BIND clauses, and GROUP BY with computed columns.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use LinkedData\SPARQL\Query\Expression;

// ============================================================================
// Example 1: Language Statistics
// ============================================================================
// Count labels by language for a concept scheme

$schemeUri = 'http://example.com/scheme/1';

$labelStats = DB::connection('sparql')
    ->query()
    ->graph('')
    ->selectExpression('?language')
    ->selectExpression('(COUNT(?label) as ?count)')
    ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
    ->whereTriple('?concept', 'skos:prefLabel', '?label')
    ->bind('COALESCE(LANG(?label), "no-lang")', '?language')
    ->groupBy('?language')
    ->orderBy('?count', 'desc')
    ->get();

echo "Language Statistics:\n";
print_r($labelStats);

// Generated SPARQL:
/*
SELECT ?language (COUNT(?label) as ?count)
WHERE {
    ?concept <http://www.w3.org/2004/02/skos/core#inScheme> <http://example.com/scheme/1> .
    ?concept <http://www.w3.org/2004/02/skos/core#prefLabel> ?label .
    BIND(COALESCE(LANG(?label), "no-lang") AS ?language)
}
GROUP BY ?language
ORDER BY DESC(?count)
*/

// ============================================================================
// Example 2: Type Statistics
// ============================================================================
// Count resources by type within a concept scheme

$typeStats = DB::connection('sparql')
    ->query()
    ->graph('')
    ->selectExpression('?type')
    ->selectExpression('(COUNT(DISTINCT ?concept) as ?count)')
    ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
    ->whereTriple('?concept', 'rdf:type', '?type')
    ->groupBy('?type')
    ->get();

echo "\nType Statistics:\n";
print_r($typeStats);

// ============================================================================
// Example 3: Complex Analytical Query with HAVING
// ============================================================================
// Find languages with more than 10 labels

$popularLanguages = DB::connection('sparql')
    ->query()
    ->graph('')
    ->selectExpression('?language')
    ->selectExpression('(COUNT(?label) as ?labelCount)')
    ->selectExpression('(COUNT(DISTINCT ?concept) as ?conceptCount)')
    ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
    ->whereTriple('?concept', 'skos:prefLabel', '?label')
    ->bind('COALESCE(LANG(?label), "no-lang")', '?language')
    ->groupBy('?language')
    ->having('?labelCount', '>', 10)
    ->orderBy('?labelCount', 'desc')
    ->get();

echo "\nPopular Languages (>10 labels):\n";
print_r($popularLanguages);

// ============================================================================
// Example 4: Using DB::raw() for Complex Expressions
// ============================================================================
// You can also use Laravel's DB::raw() helper

$complexStats = DB::connection('sparql')
    ->query()
    ->graph('')
    ->selectExpression(DB::raw('(COALESCE(LANG(?label), "no-lang") as ?language)'))
    ->selectExpression(DB::raw('(COUNT(?label) as ?count)'))
    ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
    ->whereTriple('?concept', 'skos:prefLabel', '?label')
    ->groupBy('?language')
    ->get();

echo "\nComplex Stats (using DB::raw()):\n";
print_r($complexStats);

// ============================================================================
// Example 5: Multiple BIND Expressions
// ============================================================================
// Chain multiple BIND expressions for computed values

$computedValues = DB::connection('sparql')
    ->query()
    ->graph('')
    ->selectExpression('?language')
    ->selectExpression('?category')
    ->selectExpression('(COUNT(?label) as ?count)')
    ->whereTriple('?concept', 'skos:inScheme', Expression::iri($schemeUri))
    ->whereTriple('?concept', 'skos:prefLabel', '?label')
    ->bind('LANG(?label)', '?lang')
    ->bind('COALESCE(?lang, "no-lang")', '?language')
    ->bind('IF(?count > 100, "high", "low")', '?category')
    ->groupBy('?language', '?category')
    ->get();

echo "\nComputed Values:\n";
print_r($computedValues);

// ============================================================================
// Example 6: Backward Compatibility
// ============================================================================
// The old query builder syntax still works perfectly

$oldStyleQuery = DB::connection('sparql')
    ->graph('')
    ->table('http://www.w3.org/2004/02/skos/core#Concept')
    ->where('http://www.w3.org/2004/02/skos/core#inScheme', Expression::iri($schemeUri))
    ->count();

echo "\nOld Style Query (count): $oldStyleQuery\n";

// ============================================================================
// Key Takeaways
// ============================================================================
/*
 * 1. Use selectExpression() to add custom SELECT expressions
 *    - Supports aggregates: COUNT(), SUM(), AVG(), MIN(), MAX()
 *    - Supports computed values: CONCAT(), COALESCE(), IF(), etc.
 *    - Supports DB::raw() for complex expressions
 *
 * 2. Use whereTriple() for explicit triple patterns
 *    - More readable than whereRaw() for SPARQL patterns
 *    - Handles namespace expansion automatically
 *
 * 3. Use bind() for BIND expressions
 *    - Compute values during query execution
 *    - Chain multiple BIND expressions
 *    - Variable names can include or exclude the ? prefix
 *
 * 4. Use groupBy() with variables
 *    - Variables (starting with ?) are now properly supported
 *    - Works seamlessly with BIND expressions
 *
 * 5. Backward compatibility is maintained
 *    - All existing queries continue to work
 *    - Old-style query builder methods are unchanged
 */
