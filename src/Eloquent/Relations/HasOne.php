<?php

namespace LinkedData\SPARQL\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use LinkedData\SPARQL\Eloquent\Model;

class HasOne extends HasMany
{
    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (is_null($this->getParentKey())) {
            return null;
        }

        return $this->query->first();
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
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Make a new related instance for the given model.
     *
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function newRelatedInstanceFor(Model $parent)
    {
        return $this->related->newInstance()->setAttribute(
            $this->getForeignKeyName(),
            $parent->getAttribute($this->localKey)
        );
    }
}
