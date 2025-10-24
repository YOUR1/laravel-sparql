<?php

namespace LinkedData\SPARQL\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use LinkedData\SPARQL\Eloquent\Builder;
use LinkedData\SPARQL\Eloquent\Model;
use LinkedData\SPARQL\Query\Expression;

class BelongsToMany extends Relation
{
    /**
     * The intermediate table/property for the relation.
     * In SPARQL, this represents the property connecting the two resources.
     * For pivot models, this would be the type of the intermediate resource.
     *
     * @var string
     */
    protected $table;

    /**
     * The foreign key of the parent model on the pivot.
     * In SPARQL without pivot: represents the property on the parent pointing to related.
     * In SPARQL with pivot: represents the property on the parent pointing to pivot resource.
     *
     * @var string
     */
    protected $foreignPivotKey;

    /**
     * The associated key of the related model on the pivot.
     * In SPARQL with pivot: represents the property on pivot pointing to related resource.
     *
     * @var string|null
     */
    protected $relatedPivotKey;

    /**
     * The key name of the parent model.
     *
     * @var string
     */
    protected $parentKey;

    /**
     * The key name of the related model.
     *
     * @var string
     */
    protected $relatedKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * The pivot table columns to retrieve.
     *
     * @var array
     */
    protected $pivotColumns = [];

    /**
     * Any pivot table restrictions for where clauses.
     *
     * @var array
     */
    protected $pivotWheres = [];

    /**
     * Any pivot table restrictions for whereIn clauses.
     *
     * @var array
     */
    protected $pivotWhereIns = [];

    /**
     * The default pivot values.
     *
     * @var array
     */
    protected $pivotValues = [];

    /**
     * The custom pivot table model name.
     *
     * @var string|null
     */
    protected $using;

    /**
     * The name for accessing the pivot data.
     *
     * @var string
     */
    protected $accessor = 'pivot';

    /**
     * Create a new belongs to many relationship instance.
     *
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @return void
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->performJoin();

            $parentKey = $this->parent->getAttribute($this->parentKey);
            if ($parentKey) {
                $this->query->where($this->getQualifiedForeignPivotKeyName(), '=', $parentKey);
            }
        }
    }

    /**
     * Set the join clause for the relation query.
     * In SPARQL, this builds the graph pattern to navigate from parent through pivot to related.
     *
     * @param  \LinkedData\SPARQL\Eloquent\Builder|null  $query
     * @return $this
     */
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        // In SPARQL, we use graph patterns instead of joins
        // The pattern is: ?parent foreignPivotKey ?pivotResource . ?pivotResource relatedPivotKey ?related
        // This will be handled by the Grammar when building the query

        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $keys = $this->getKeys($models, $this->parentKey);

        $this->query->whereIn(
            $this->getQualifiedForeignPivotKeyName(),
            array_map(function ($key) {
                return Expression::iri($key);
            }, array_values(array_unique($keys)))
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

        foreach ($models as $model) {
            $key = $this->getDictionaryKey($model->getAttribute($this->parentKey));

            if (isset($dictionary[$key])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$key])
                );
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
            $value = $result->{$this->accessor}->{$this->foreignPivotKey};

            // Handle Collection values (SPARQL models return Collections)
            if ($value instanceof \Illuminate\Support\Collection) {
                $value = $value->first();
            }

            // Convert to string for dictionary key
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }

            $key = $this->getDictionaryKey($value);

            if (! isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the dictionary key for the given value.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function getDictionaryKey($value)
    {
        // Handle Collection values (old approach)
        if ($value instanceof \Illuminate\Support\Collection) {
            $value = $value->first();
        }

        // Handle array values (new hybrid approach)
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        // Convert to string
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        return $value;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return ! is_null($this->parent->getAttribute($this->parentKey))
                ? $this->get()
                : $this->related->newCollection();
    }

    /**
     * Execute the query and get the results with pivot data.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        $columns = $this->query->getQuery()->columns ? [] : $columns;

        $builder = $this->query->applyScopes();

        $models = $builder->addSelect(
            $this->shouldSelect($columns)
        )->getModels();

        $this->hydratePivotRelation($models);

        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Get the select columns for the relation query.
     *
     * @return array
     */
    protected function shouldSelect(array $columns = ['*'])
    {
        if ($columns == ['*']) {
            $columns = [$this->related->getTable() . '.*'];
        }

        return array_merge($columns, $this->aliasedPivotColumns());
    }

    /**
     * Get the pivot columns for the relation.
     *
     * @return array
     */
    protected function aliasedPivotColumns()
    {
        $defaults = [$this->foreignPivotKey, $this->relatedPivotKey];

        return collect(array_merge($defaults, $this->pivotColumns))->map(function ($column) {
            return $this->qualifyPivotColumn($column) . ' as pivot_' . $column;
        })->unique()->all();
    }

    /**
     * Hydrate the pivot relationship on the models.
     *
     * @return void
     */
    protected function hydratePivotRelation(array $models)
    {
        foreach ($models as $model) {
            $pivot = $this->newExistingPivot($this->migratePivotAttributes($model));

            $model->setRelation($this->accessor, $pivot);
        }
    }

    /**
     * Get the pivot attributes from a model.
     *
     * @return array
     */
    protected function migratePivotAttributes(Model $model)
    {
        $values = [];

        foreach ($model->getAttributes() as $key => $value) {
            if (strpos($key, 'pivot_') === 0) {
                $values[substr($key, 6)] = $value;

                unset($model->$key);
            }
        }

        return $values;
    }

    /**
     * Create a new pivot model instance.
     *
     * @param  bool  $exists
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function newPivot(array $attributes = [], $exists = false)
    {
        $pivot = $this->related->newInstance();
        $pivot->forceFill($attributes);
        $pivot->exists = $exists;

        return $pivot;
    }

    /**
     * Create a new existing pivot model instance.
     *
     * @return \LinkedData\SPARQL\Eloquent\Model
     */
    public function newExistingPivot(array $attributes = [])
    {
        return $this->newPivot($attributes, true);
    }

    /**
     * Set the columns on the pivot table to retrieve.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function withPivot($columns)
    {
        $this->pivotColumns = array_merge(
            $this->pivotColumns,
            is_array($columns) ? $columns : func_get_args()
        );

        return $this;
    }

    /**
     * Specify that the pivot table has creation and update timestamps.
     *
     * @param  mixed  ...$createdAt
     * @return $this
     */
    public function withTimestamps($createdAt = null, $updatedAt = null)
    {
        $this->withPivot($createdAt ?: 'created_at', $updatedAt ?: 'updated_at');

        return $this;
    }

    /**
     * Set the name for accessing the pivot data.
     *
     * @param  string  $accessor
     * @return $this
     */
    public function as($accessor)
    {
        $this->accessor = $accessor;

        return $this;
    }

    /**
     * Set a where clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function wherePivot($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->pivotWheres[] = func_get_args();

        $this->where($this->qualifyPivotColumn($column), $operator, $value, $boolean);

        return $this;
    }

    /**
     * Set a "where in" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function wherePivotIn($column, $values, $boolean = 'and', $not = false)
    {
        $this->pivotWhereIns[] = func_get_args();

        $this->whereIn($this->qualifyPivotColumn($column), $values, $boolean, $not);

        return $this;
    }

    /**
     * Set a "where not in" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @return $this
     */
    public function wherePivotNotIn($column, $values, $boolean = 'and')
    {
        return $this->wherePivotIn($column, $values, $boolean, true);
    }

    /**
     * Set an "or where" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function orWherePivot($column, $operator = null, $value = null)
    {
        return $this->wherePivot($column, $operator, $value, 'or');
    }

    /**
     * Set an "or where in" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function orWherePivotIn($column, $values)
    {
        return $this->wherePivotIn($column, $values, 'or');
    }

    /**
     * Set an "or where not in" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function orWherePivotNotIn($column, $values)
    {
        return $this->wherePivotIn($column, $values, 'or', true);
    }

    /**
     * Set an "order by" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderByPivot($column, $direction = 'asc')
    {
        $this->orderBy($this->qualifyPivotColumn($column), $direction);

        return $this;
    }

    /**
     * Attach a model to the parent.
     *
     * @param  mixed  $id
     * @param  bool  $touch
     * @return void
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        // In SPARQL, we insert triples for the relationship
        // Format: INSERT DATA { <parent> foreignPivotKey <id> }
        // If we have pivot attributes, we use an intermediate resource

        $ids = $this->parseIds($id);

        foreach ($ids as $key => $value) {
            $pivotData = array_merge(
                $this->baseAttachRecord($key, false),
                $this->castAttributes($attributes),
                is_array($value) ? $value : []
            );

            $this->newPivot($pivotData, false)->save();
        }

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /**
     * Create an array representing an attach record.
     *
     * @param  int  $id
     * @param  bool  $timed
     * @return array
     */
    protected function baseAttachRecord($id, $timed)
    {
        $record = [
            $this->foreignPivotKey => $this->parent->{$this->parentKey},
            $this->relatedPivotKey => $id,
        ];

        if ($timed) {
            $record = $this->addTimestampsToAttachment($record);
        }

        return array_merge($record, $this->pivotValues);
    }

    /**
     * Add timestamps to the pivot attachment record.
     *
     * @return array
     */
    protected function addTimestampsToAttachment(array $record, $exists = false)
    {
        $timestamp = $this->parent->freshTimestamp();

        if (! $exists && $this->hasPivotColumn('created_at')) {
            $record['created_at'] = $timestamp;
        }

        if ($this->hasPivotColumn('updated_at')) {
            $record['updated_at'] = $timestamp;
        }

        return $record;
    }

    /**
     * Determine if the pivot table has a given column.
     *
     * @param  string  $column
     * @return bool
     */
    protected function hasPivotColumn($column)
    {
        return in_array($column, $this->pivotColumns);
    }

    /**
     * Detach models from the relationship.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        // In SPARQL, we delete triples for the relationship
        // Format: DELETE DATA { <parent> foreignPivotKey <id> }

        if (is_null($ids)) {
            $ids = $this->query->pluck($this->relatedKey)->all();
        }

        $ids = $this->parseIds($ids);

        if (count($ids) === 0) {
            return 0;
        }

        $query = $this->newPivotQuery();

        $query->where($this->foreignPivotKey, '=', $this->parent->{$this->parentKey});

        $query->whereIn($this->relatedPivotKey, array_map(function ($id) {
            return Expression::iri($id);
        }, $ids));

        $results = $query->delete();

        if ($touch) {
            $this->touchIfTouching();
        }

        return $results;
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array  $ids
     * @param  bool  $detaching
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        $current = $this->getCurrentlyAttachedPivots()->all();

        $records = $this->formatRecordsList($this->parseIds($ids));

        $detach = array_diff(array_keys($current), array_keys($records));

        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = $this->castKeys($detach);
        }

        $changes = array_merge(
            $changes,
            $this->attachNew($records, $current, false)
        );

        return $changes;
    }

    /**
     * Sync the intermediate tables with a list of IDs without detaching.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array  $ids
     * @return array
     */
    public function syncWithoutDetaching($ids)
    {
        return $this->sync($ids, false);
    }

    /**
     * Toggle a model ID in the intermediate table.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return array
     */
    public function toggle($ids, $touch = true)
    {
        $changes = [
            'attached' => [],
            'detached' => [],
        ];

        $ids = $this->parseIds($ids);

        foreach ($ids as $id => $attributes) {
            if ($this->isAttached($id)) {
                $this->detach($id, false);

                $changes['detached'][] = is_numeric($id) ? (int) $id : (string) $id;
            } else {
                $this->attach($id, $attributes, false);

                $changes['attached'][] = is_numeric($id) ? (int) $id : (string) $id;
            }
        }

        if ($touch) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * Get the currently attached pivots.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getCurrentlyAttachedPivots()
    {
        return $this->newPivotQuery()->get()->mapWithKeys(function ($pivot) {
            $key = $pivot->getAttribute($this->relatedPivotKey);

            // Handle Collection values
            if ($key instanceof \Illuminate\Support\Collection) {
                $key = $key->first();
            }

            // Convert to string
            if (is_object($key) && method_exists($key, '__toString')) {
                $key = (string) $key;
            }

            return [$key => $pivot];
        });
    }

    /**
     * Create a new pivot statement for a given "other" ID.
     *
     * @return \LinkedData\SPARQL\Eloquent\Builder|\LinkedData\SPARQL\Eloquent\Model
     */
    protected function newPivotQuery()
    {
        $query = $this->parent->newModelQuery();

        // Configure query for pivot table access
        // This would select from the intermediate resources

        return $query;
    }

    /**
     * Format the sync / toggle record list so that it is keyed by ID.
     *
     * @return array
     */
    protected function formatRecordsList(array $records)
    {
        return collect($records)->mapWithKeys(function ($attributes, $id) {
            if (! is_array($attributes)) {
                [$id, $attributes] = [$attributes, []];
            }

            return [$id => $attributes];
        })->all();
    }

    /**
     * Attach all of the records that aren't in the given current records.
     *
     * @param  bool  $touch
     * @return array
     */
    protected function attachNew(array $records, array $current, $touch = true)
    {
        $changes = ['attached' => [], 'updated' => []];

        foreach ($records as $id => $attributes) {
            if (! isset($current[$id])) {
                $this->attach($id, $attributes, $touch);

                $changes['attached'][] = $this->castKey($id);
            } elseif (count($attributes) > 0 &&
                      $this->updateExistingPivot($id, $attributes, $touch)) {
                $changes['updated'][] = $this->castKey($id);
            }
        }

        return $changes;
    }

    /**
     * Update an existing pivot record on the table.
     *
     * @param  mixed  $id
     * @param  bool  $touch
     * @return int
     */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        if (empty($attributes)) {
            return 0;
        }

        $query = $this->newPivotQuery();

        $query->where($this->foreignPivotKey, '=', $this->parent->{$this->parentKey});
        $query->where($this->relatedPivotKey, '=', Expression::iri($id));

        $updated = $query->update($this->castAttributes($attributes));

        if ($touch) {
            $this->touchIfTouching();
        }

        return $updated;
    }

    /**
     * Determine if the given model is attached.
     *
     * @param  mixed  $id
     * @return bool
     */
    protected function isAttached($id)
    {
        return $this->newPivotQuery()
            ->where($this->foreignPivotKey, '=', $this->parent->{$this->parentKey})
            ->where($this->relatedPivotKey, '=', Expression::iri($id))
            ->exists();
    }

    /**
     * Get the fully qualified foreign key for the relation.
     *
     * @return string
     */
    public function getQualifiedForeignPivotKeyName()
    {
        return $this->qualifyPivotColumn($this->foreignPivotKey);
    }

    /**
     * Get the fully qualified "related key" for the relation.
     *
     * @return string
     */
    public function getQualifiedRelatedPivotKeyName()
    {
        return $this->qualifyPivotColumn($this->relatedPivotKey);
    }

    /**
     * Qualify the given column name by the pivot table.
     *
     * @param  string  $column
     * @return string
     */
    public function qualifyPivotColumn($column)
    {
        return $this->table ? $this->table . '.' . $column : $column;
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getForeignPivotKeyName()
    {
        return $this->foreignPivotKey;
    }

    /**
     * Get the "related key" for the relationship.
     *
     * @return string
     */
    public function getRelatedPivotKeyName()
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the parent key for the relationship.
     *
     * @return string
     */
    public function getParentKeyName()
    {
        return $this->parentKey;
    }

    /**
     * Get the related key for the relationship.
     *
     * @return string
     */
    public function getRelatedKeyName()
    {
        return $this->relatedKey;
    }

    /**
     * Get the intermediate table for the relationship.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the relationship name for the relationship.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * Get the name of the pivot accessor for this relationship.
     *
     * @return string
     */
    public function getPivotAccessor()
    {
        return $this->accessor;
    }

    /**
     * Get the pivot columns for this relationship.
     *
     * @return array
     */
    public function getPivotColumns()
    {
        return $this->pivotColumns;
    }

    /**
     * Cast the given keys to integers if they are numeric and string otherwise.
     *
     * @return array
     */
    protected function castKeys(array $keys)
    {
        return array_map(function ($key) {
            return $this->castKey($key);
        }, $keys);
    }

    /**
     * Cast the given key to an integer if it is numeric.
     *
     * @param  mixed  $key
     * @return mixed
     */
    protected function castKey($key)
    {
        return is_numeric($key) ? (int) $key : (string) $key;
    }

    /**
     * Cast the given pivot attributes.
     *
     * @return array
     */
    protected function castAttributes(array $attributes)
    {
        // In SPARQL, we may need to convert values to appropriate RDF types
        return $attributes;
    }

    /**
     * Parse the given IDs into a keyed array.
     *
     * @param  mixed  $ids
     * @return array
     */
    protected function parseIds($ids)
    {
        if ($ids instanceof Model) {
            return [$ids->getAttribute($this->relatedKey) => []];
        }

        if ($ids instanceof Collection) {
            return $ids->map(function ($model) {
                return $model->getAttribute($this->relatedKey);
            })->all();
        }

        return (array) $ids;
    }

    /**
     * Touch all of the related models for the relationship.
     *
     * @return void
     */
    public function touchIfTouching()
    {
        if ($this->touchingParent()) {
            $this->getParent()->touch();
        }

        if ($this->getParent()->touches($this->relationName)) {
            $this->touch();
        }
    }

    /**
     * Determine if we should touch the parent on sync.
     *
     * @return bool
     */
    protected function touchingParent()
    {
        return $this->getRelated()->touches($this->guessInverseRelation());
    }

    /**
     * Attempt to guess the name of the inverse of the relation.
     *
     * @return string
     */
    protected function guessInverseRelation()
    {
        return Str::camel(class_basename($this->getParent()));
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getExistenceCompareKey()
    {
        return $this->getQualifiedForeignPivotKeyName();
    }
}
