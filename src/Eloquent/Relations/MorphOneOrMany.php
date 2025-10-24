<?php

namespace LinkedData\SPARQL\Eloquent\Relations;

use LinkedData\SPARQL\Eloquent\Builder;
use LinkedData\SPARQL\Eloquent\Model;

abstract class MorphOneOrMany extends HasMany
{
    /**
     * The foreign key type for the relationship.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The class name of the parent model.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Create a new morph one or many relationship instance.
     *
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $type, $id, $localKey)
    {
        $this->morphType = $type;
        $this->morphClass = $parent->getMorphClass();

        parent::__construct($query, $parent, $id, $localKey);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->morphType, '=', $this->morphClass);

            parent::addConstraints();
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $this->query->where($this->morphType, '=', $this->morphClass);
    }

    /**
     * Get the relationship query.
     *
     * @param  array|mixed  $columns
     * @return \LinkedData\SPARQL\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        /** @var \LinkedData\SPARQL\Eloquent\Builder $relationQuery */
        $relationQuery = parent::getRelationExistenceQuery($query, $parentQuery, $columns)
            ->where($query->getModel()->getTable() . '.' . $this->getMorphType(), '=', $this->morphClass);

        return $relationQuery;
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
     * Get the class name of the parent model.
     *
     * @return string
     */
    public function getMorphClass()
    {
        return $this->morphClass;
    }
}
