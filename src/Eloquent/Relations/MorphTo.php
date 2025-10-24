<?php

namespace LinkedData\SPARQL\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Builder;
use LinkedData\SPARQL\Eloquent\Model;

class MorphTo extends BelongsTo
{
    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The models whose relations are being eager loaded.
     *
     * @var \Illuminate\Database\Eloquent\Collection
     */
    protected $models;

    /**
     * All of the models keyed by ID.
     *
     * @var array
     */
    protected $dictionary = [];

    /**
     * A map of relations to load for each individual morph type.
     *
     * @var array
     */
    protected $morphableEagerLoads = [];

    /**
     * A map of relationship counts to load for each individual morph type.
     *
     * @var array
     */
    protected $morphableEagerLoadCounts = [];

    /**
     * Create a new morph to relationship instance.
     *
     * @param  string  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string  $type
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        $this->morphType = $type;

        parent::__construct($query, $parent, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->buildDictionary($this->models = Collection::make($models));
    }

    /**
     * Build a dictionary with the models.
     *
     * @return array
     */
    protected function buildDictionary(Collection $models)
    {
        foreach ($models as $model) {
            if ($model->{$this->morphType}) {
                $morphTypeValue = $this->getDictionaryKey($model->{$this->morphType});
                $foreignKeyValue = $this->getDictionaryKey($model->{$this->foreignKey});

                $this->dictionary[$morphTypeValue][$foreignKeyValue][] = $model;
            }
        }

        return $this->dictionary;
    }

    /**
     * Get the dictionary key attribute.
     *
     * @param  mixed  $attribute
     * @return mixed
     */
    protected function getDictionaryKey($attribute)
    {
        if ($attribute instanceof \Illuminate\Support\Collection) {
            return $attribute->first();
        }

        // Handle array values (new hybrid approach)
        if (is_array($attribute)) {
            return reset($attribute) ?: null;
        }

        return $attribute;
    }

    /**
     * Get the results of the relationship.
     *
     * Called via eager load method of Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEager()
    {
        foreach (array_keys($this->dictionary) as $type) {
            $this->matchToMorphParents($type, $this->getResultsByType($type));
        }

        return $this->models;
    }

    /**
     * Get all of the relation results for a type.
     *
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $ownerKey = $this->ownerKey ?? $instance->getKeyName();

        $query = $instance->newQuery();

        // Merge constraints from the original query
        if ($this->query->getQuery()->wheres) {
            foreach ($this->query->getQuery()->wheres as $where) {
                $query->getQuery()->wheres[] = $where;
            }
        }

        // Apply eager loads for this specific morph type
        if (isset($this->morphableEagerLoads[get_class($instance)])) {
            $query->with($this->morphableEagerLoads[get_class($instance)]);
        }

        // Apply eager load counts for this specific morph type
        if (isset($this->morphableEagerLoadCounts[get_class($instance)])) {
            $query->withCount($this->morphableEagerLoadCounts[get_class($instance)]);
        }

        /** @var \Illuminate\Database\Eloquent\Collection $results */
        $results = $query->whereIn(
            $ownerKey,
            $this->gatherKeysByType($type)
        )->get();

        return $results;
    }

    /**
     * Gather all of the foreign keys for a given type.
     *
     * @param  string  $type
     * @return array
     */
    protected function gatherKeysByType($type)
    {
        return array_keys($this->dictionary[$type] ?? []);
    }

    /**
     * Create a new model instance by type.
     *
     * @param  string  $type
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function createModelByType($type)
    {
        $class = Model::getActualClassNameForMorph($type);

        return tap(new $class, function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection($this->parent->getConnectionName());
            }
        });
    }

    /**
     * Match the results for a given type to their parents.
     *
     * @param  string  $type
     * @return void
     */
    protected function matchToMorphParents($type, Collection $results)
    {
        foreach ($results as $result) {
            $ownerKey = ! is_null($this->ownerKey)
                ? $this->getDictionaryKey($result->{$this->ownerKey})
                : $result->getKey();

            if (isset($this->dictionary[$type][$ownerKey])) {
                foreach ($this->dictionary[$type][$ownerKey] as $model) {
                    $model->setRelation($this->relationName, $result);
                }
            }
        }
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
        return $models;
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \LinkedData\SPARQL\Eloquent\Model|null  $model
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function associate($model)
    {
        if ($model instanceof Model) {
            $foreignKey = $this->ownerKey && $model->{$this->ownerKey}
                ? $this->ownerKey
                : $model->getKeyName();

            $this->parent->setAttribute(
                $this->foreignKey,
                $model->{$foreignKey}
            );

            $this->parent->setAttribute(
                $this->morphType,
                $model->getMorphClass()
            );
        } else {
            $this->parent->setAttribute($this->foreignKey, null);
            $this->parent->setAttribute($this->morphType, null);
        }

        return $this->parent->setRelation($this->relationName, $model);
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setAttribute($this->morphType, null);

        return $this->parent->setRelation($this->relationName, null);
    }

    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function getMorphType()
    {
        return $this->morphType;
    }

    /**
     * Get the dictionary used by the relationship.
     *
     * @return array
     */
    public function getDictionary()
    {
        return $this->dictionary;
    }

    /**
     * Specify which relations to load for a given morph type.
     *
     * @return $this
     */
    public function morphWith(array $with)
    {
        $this->morphableEagerLoads = array_merge(
            $this->morphableEagerLoads,
            $with
        );

        return $this;
    }

    /**
     * Specify which relationship counts to load for a given morph type.
     *
     * @return $this
     */
    public function morphWithCount(array $withCount)
    {
        $this->morphableEagerLoadCounts = array_merge(
            $this->morphableEagerLoadCounts,
            $withCount
        );

        return $this;
    }

    /**
     * Get the foreign key of the relationship.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }
}
