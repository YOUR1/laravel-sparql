<?php

namespace LinkedData\SPARQL\Eloquent;

/**
 * Generic Resource Model
 *
 * Pre-built model for dynamic RDF resources (no schema needed).
 * Useful when you don't want to define a specific model class for each RDF type.
 *
 * Usage:
 * ```php
 * $resource = GenericResource::make('http://example.com/resource/1', 'http://schema.org/Product');
 * $resource->setAttribute('http://schema.org/name', 'Widget');
 * $resource->setAttribute('http://schema.org/price', 19.99);
 * $resource->save();
 * ```
 */
class GenericResource extends Model
{
    /**
     * Mass assignment protection is disabled by default for GenericResource
     */
    protected $guarded = [];

    /**
     * No fixed RDF class - set dynamically
     */
    protected $table = null;

    /**
     * Create a new resource with URI and optional RDF class
     *
     * @param  string  $uri  The resource URI
     * @param  string|null  $rdfClass  Optional RDF class (rdf:type)
     */
    public static function make(string $uri, ?string $rdfClass = null): static
    {
        $instance = new static;
        $instance->setUri($uri);

        if ($rdfClass) {
            $instance->setRdfClass($rdfClass);
        }

        return $instance;
    }
}
