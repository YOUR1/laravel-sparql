<?php

namespace LinkedData\SPARQL\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Builder;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Query\Expression;

class BelongsTo extends Relation
{
    /**
     * The foreign key of the child model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The associated key on the parent model.
     *
     * @var string|null
     */
    protected $ownerKey;

    /**
     * The name of the relation.
     *
     * @var string
     */
    protected $relationName;

    protected $current_subject = null;

    /**
     * Create a new belongs to relationship instance.
     *
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $relationName
     * @return void
     */
    public function __construct(Builder $query, Model $child, $foreignKey, $ownerKey, $relationName)
    {
        $this->ownerKey = $ownerKey;
        $this->foreignKey = $foreignKey;
        $this->relationName = $relationName;

        parent::__construct($query, $child);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to, we need to query where the ownerKey matches the foreignKey value
            $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

            if ($foreignKeyValue) {
                $this->query->where($this->ownerKey, '=', $foreignKeyValue);
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // Get all the foreign key values from the models
        $keys = $this->getEagerModelKeys($models);

        // Add a whereIn constraint for the owner key
        $this->query->whereIn($this->ownerKey, $keys);
    }

    /**
     * Gather the keys from an array of related models.
     *
     * @return array
     */
    protected function getEagerModelKeys(array $models)
    {
        $keys = [];

        foreach ($models as $model) {
            $value = $model->getAttribute($this->foreignKey);

            // Handle Collection values (SPARQL models return Collections)
            if ($value instanceof \Illuminate\Support\Collection) {
                if ($value->isEmpty()) {
                    continue;
                }
                $value = $value->first();
            }

            // Convert to string if it's an object
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }

            if ($value !== null && $value !== '') {
                // Ensure we create proper IRI expressions
                $keys[] = Expression::iri($value);
            }
        }

        return array_values(array_unique($keys));
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
            $model->setRelation($relation, null);
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

        // Once we have the dictionary, we can match the results
        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);

            // Handle Collection values (SPARQL models return Collections)
            if ($key instanceof \Illuminate\Support\Collection) {
                $key = $key->first();
            }

            // Convert to string for comparison (handles EasyRdf\Literal)
            if ($key && is_object($key) && method_exists($key, '__toString')) {
                $key = (string) $key;
            }

            if ($key && array_key_exists($key, $dictionary)) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's owner key.
     *
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $result->getAttribute($this->ownerKey);

            // Handle Collection values (SPARQL models return Collections)
            if ($key instanceof \Illuminate\Support\Collection) {
                $key = $key->first();
            }

            // Convert to string for array key (handles EasyRdf\Literal)
            if ($key && is_object($key) && method_exists($key, '__toString')) {
                $key = (string) $key;
            }

            if ($key) {
                $dictionary[$key] = $result;
            }
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
        if (is_null($this->parent->getAttribute($this->foreignKey))) {
            return null;
        }

        return $this->query->first();
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \LinkedData\SPARQL\Eloquent\Model|string  $model
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function associate($model)
    {
        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;

        // Wrap the URI in Expression::iri() so it's stored as an IRI, not a string literal
        $this->parent->setAttribute($this->foreignKey, Expression::iri($ownerKey));

        if ($model instanceof Model) {
            $this->parent->setRelation($this->relationName, $model);
        } else {
            $this->parent->unsetRelation($this->relationName);
        }

        return $this->parent;
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent->setRelation($this->relationName, null);
    }

    /**
     * Get the foreign key of the relationship.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the fully qualified foreign key of the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName()
    {
        return $this->parent->qualifyColumn($this->foreignKey);
    }

    /**
     * Get the associated key of the relationship.
     *
     * @return string
     */
    public function getOwnerKeyName()
    {
        return $this->ownerKey;
    }

    /**
     * Get the fully qualified associated key of the relationship.
     *
     * @return string
     */
    public function getQualifiedOwnerKeyName()
    {
        return $this->related->qualifyColumn($this->ownerKey);
    }

    /**
     * Get the name of the relation.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getExistenceCompareKey()
    {
        return $this->getQualifiedOwnerKeyName();
    }
}
