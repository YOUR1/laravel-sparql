<?php

namespace LinkedData\SPARQL\Eloquent\Concerns;

use LinkedData\SPARQL\Eloquent\GenericResource;

/**
 * Trait SyncsToSparql
 *
 * Enables regular Laravel Eloquent models to sync their data to a SPARQL endpoint.
 * Perfect for maintaining a knowledge graph representation of your relational data.
 *
 * Usage:
 * ```php
 * class Product extends Model
 * {
 *     use SyncsToSparql;
 *
 *     public function getSparqlConnection(): string
 *     {
 *         return 'sparql';
 *     }
 *
 *     public function getSparqlUri(): string
 *     {
 *         return 'http://example.com/product/' . $this->id;
 *     }
 *
 *     public function getSparqlRdfClass(): string
 *     {
 *         return 'http://schema.org/Product';
 *     }
 *
 *     public function toSparqlAttributes(): array
 *     {
 *         return [
 *             'http://schema.org/name' => $this->name,
 *             'http://schema.org/price' => $this->price,
 *         ];
 *     }
 * }
 *
 * // Sync single model
 * $product->syncToSparql();
 *
 * // Sync multiple models in batch
 * Product::syncBatchToSparql(Product::all());
 * ```
 */
trait SyncsToSparql
{
    /**
     * Get the SPARQL connection name to use for syncing
     */
    abstract public function getSparqlConnection(): string;

    /**
     * Get the URI for this resource in the SPARQL store
     * Typically based on the model's primary key
     */
    abstract public function getSparqlUri(): string;

    /**
     * Get the RDF class (rdf:type) for this resource
     */
    abstract public function getSparqlRdfClass(): string;

    /**
     * Map model attributes to SPARQL predicates
     * Return array of ['predicate' => value]
     *
     * Multi-valued properties should be arrays:
     * ['http://schema.org/email' => ['email1@example.com', 'email2@example.com']]
     */
    abstract public function toSparqlAttributes(): array;

    /**
     * Sync this model to SPARQL store
     * Creates or updates the resource
     */
    public function syncToSparql(): bool
    {
        $resource = $this->buildSparqlResource();

        return $resource->save();
    }

    /**
     * Build SPARQL resource from this model
     */
    protected function buildSparqlResource(): GenericResource
    {
        $resource = GenericResource::make(
            $this->getSparqlUri(),
            $this->getSparqlRdfClass()
        );

        $resource->setConnection($this->getSparqlConnection());

        foreach ($this->toSparqlAttributes() as $predicate => $value) {
            if (is_array($value)) {
                // Handle multiple values for a single property
                foreach ($value as $v) {
                    $resource->addPropertyValue($predicate, $v);
                }
            } else {
                $resource->setAttribute($predicate, $value);
            }
        }

        return $resource;
    }

    /**
     * Delete this model from SPARQL store
     */
    public function deleteFromSparql(): bool
    {
        $resource = GenericResource::make($this->getSparqlUri());
        $resource->setConnection($this->getSparqlConnection());
        $resource->exists = true;

        return $resource->delete();
    }

    /**
     * Sync multiple models to SPARQL store in a single batch operation
     * Much more efficient than syncing models one by one
     *
     * @param  iterable  $models  Collection or array of models
     * @return int Number of models synced
     */
    public static function syncBatchToSparql(iterable $models): int
    {
        $resources = [];

        foreach ($models as $model) {
            $resources[] = $model->buildSparqlResource();
        }

        if (empty($resources)) {
            return 0;
        }

        GenericResource::insertBatch($resources);

        return count($resources);
    }

    /**
     * Delete multiple models from SPARQL store in a single batch operation
     *
     * @param  iterable  $models  Collection or array of models
     * @return int Number of models deleted
     */
    public static function deleteBatchFromSparql(iterable $models): int
    {
        $uris = [];

        foreach ($models as $model) {
            $uris[] = $model->getSparqlUri();
        }

        if (empty($uris)) {
            return 0;
        }

        return GenericResource::deleteBatch($uris);
    }
}
