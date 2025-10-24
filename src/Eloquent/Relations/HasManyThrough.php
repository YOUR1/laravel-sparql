<?php

namespace LinkedData\SPARQL\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use LinkedData\SPARQL\Eloquent\Builder;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Query\Expression;

class HasManyThrough extends Relation
{
    /**
     * The "through" parent model instance.
     *
     * @var \LinkedData\SPARQL\Eloquent\Model
     */
    protected $throughParent;

    /**
     * The far parent model instance.
     *
     * @var \LinkedData\SPARQL\Eloquent\Model
     */
    protected $farParent;

    /**
     * The near key on the relationship.
     *
     * @var string
     */
    protected $firstKey;

    /**
     * The far key on the relationship.
     *
     * @var string
     */
    protected $secondKey;

    /**
     * The local key on the relationship.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The local key on the intermediary model.
     *
     * @var string
     */
    protected $secondLocalKey;

    /**
     * Create a new has many through relationship instance.
     *
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, Model $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey)
    {
        $this->localKey = $localKey;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->farParent = $farParent;
        $this->throughParent = $throughParent;
        $this->secondLocalKey = $secondLocalKey;

        parent::__construct($query, $throughParent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        $localValue = $this->farParent[$this->localKey];

        if (static::$constraints) {
            // Create a variable for the intermediate (through) model
            $throughVar = '?through_' . \Illuminate\Support\Str::random(10);

            if (! is_null($localValue)) {
                // Add triple pattern: ?through firstKey localValue
                // Example: ?through geo:country <urn:country:1>
                $this->query->whereRaw(
                    $throughVar . ' ' .
                    $this->firstKey . ' ' .
                    Expression::iri($localValue)->getValue()
                );
            }

            // Add triple pattern for the through model's type
            // Example: ?through rdf:type <foaf:Person>
            $this->query->whereRaw(
                $throughVar . ' rdf:type ' .
                Expression::iri($this->throughParent->getTable())->getValue()
            );

            // Add pattern connecting related model to through model
            // Example: ?blogpost schema:author ?through
            // We need to use whereRaw here to properly construct the triple pattern
            $this->query->whereRaw(
                $this->query->getQuery()->unique_subject . ' ' .
                $this->secondKey . ' ' .
                $throughVar
            );
        }
    }

    /**
     * Perform the join to connect through the intermediate model.
     *
     * @return void
     */
    protected function performJoin()
    {
        // This method is no longer needed with the new implementation
        // but kept for backward compatibility
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $keys = $this->getKeys($models, $this->localKey);

        // Create a variable for the intermediate (through) model
        $throughVar = '?through_' . \Illuminate\Support\Str::random(10);

        // Add pattern: ?through firstKey ?country_val
        $this->query->whereRaw(
            $throughVar . ' ' . $this->firstKey . ' ?country_val'
        );

        // Use VALUES to bind the country values
        $this->query->whereValues(['?country_val'], array_map(function ($key) {
            return [Expression::iri($key)];
        }, $keys));

        // Add triple pattern for the through model's type
        $this->query->whereRaw(
            $throughVar . ' rdf:type ' .
            Expression::iri($this->throughParent->getTable())->getValue()
        );

        // Add pattern connecting related model to through model
        $this->query->whereRaw(
            $this->query->getQuery()->unique_subject . ' ' .
            $this->secondKey . ' ' .
            $throughVar
        );
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $this->related->newCollection($dictionary[$key]));
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        foreach ($results as $result) {
            // Get the through key value
            $throughKey = $result->{$this->firstKey};

            if ($throughKey instanceof BaseCollection) {
                $throughKey = $throughKey->first();
            }

            // Handle array values (new hybrid approach)
            if (is_array($throughKey)) {
                $throughKey = reset($throughKey) ?: null;
            }

            $throughKey = (string) $throughKey;

            $dictionary[$throughKey][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return ! is_null($this->farParent->{$this->localKey})
                ? $this->get()
                : $this->related->newCollection();
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getExistenceCompareKey()
    {
        return $this->getQualifiedFarKeyName();
    }

    /**
     * Get the qualified foreign key on the related model.
     *
     * @return string
     */
    public function getQualifiedFarKeyName()
    {
        return $this->related->qualifyColumn($this->secondLocalKey);
    }

    /**
     * Get the qualified foreign key on the "through" model.
     *
     * @return string
     */
    public function getQualifiedFirstKeyName()
    {
        return $this->throughParent->qualifyColumn($this->firstKey);
    }

    /**
     * Get the qualified foreign key on the related model.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->secondKey;
    }

    /**
     * Get the local key name on the far parent model.
     *
     * @return string
     */
    public function getLocalKeyName()
    {
        return $this->localKey;
    }

    /**
     * Get the first key name.
     *
     * @return string
     */
    public function getFirstKeyName()
    {
        return $this->firstKey;
    }

    /**
     * Get the second local key name.
     *
     * @return string
     */
    public function getSecondLocalKeyName()
    {
        return $this->secondLocalKey;
    }
}
