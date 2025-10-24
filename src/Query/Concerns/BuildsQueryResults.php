<?php

namespace LinkedData\SPARQL\Query\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Trait for building query result retrieval methods.
 *
 * This trait provides caching capabilities for SPARQL queries.
 * Laravel's BuildsQueries trait already provides: chunk, each, lazy, lazyById,
 * firstOr, firstOrFail, cursor, etc. This trait adds SPARQL-specific caching.
 */
trait BuildsQueryResults
{
    /**
     * Indicate that the query results should be cached.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $ttl
     * @param  string|null  $key
     * @return $this
     */
    public function remember($ttl, $key = null)
    {
        [$this->cacheSeconds, $this->cacheKey] = [
            $ttl instanceof \DateTimeInterface ? $ttl->getTimestamp() - time() : $ttl,
            $key,
        ];

        if ($this->cacheSeconds instanceof \DateInterval) {
            $this->cacheSeconds = (int) \Carbon\Carbon::now()->add($this->cacheSeconds)->diffInRealSeconds();
        }

        return $this;
    }

    /**
     * Indicate that the query results should be cached forever.
     *
     * @param  string|null  $key
     * @return $this
     */
    public function rememberForever($key = null)
    {
        return $this->remember(-1, $key);
    }

    /**
     * Indicate that the query should not be cached.
     *
     * @return $this
     */
    public function dontRemember()
    {
        $this->cacheSeconds = $this->cacheKey = null;

        return $this;
    }

    /**
     * Get the cache key for the query.
     *
     * @return string
     */
    public function getCacheKey()
    {
        return $this->cacheKey ?: $this->generateCacheKey();
    }

    /**
     * Generate the unique cache key for the query.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        $name = $this->connection->getName();

        return hash('sha256', $name . $this->toSql() . serialize($this->getBindings()));
    }

    /**
     * Execute the query as a "select" statement.
     *
     * This method is overridden from the base implementation to support caching.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Support\Collection
     */
    protected function runSelect()
    {
        if (! is_null($this->cacheSeconds ?? null)) {
            return $this->getCachedResults();
        }

        return $this->connection->select(
            $this->toSql(),
            $this->getBindings(),
            ! $this->useWritePdo
        );
    }

    /**
     * Get the cached results of the query.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedResults()
    {
        $key = $this->getCacheKey();
        $seconds = $this->cacheSeconds;

        $callback = function () {
            $this->cacheSeconds = null;

            return $this->connection->select(
                $this->toSql(),
                $this->getBindings(),
                ! $this->useWritePdo
            );
        };

        // If the cache seconds is -1, we'll cache forever
        if ($seconds < 0) {
            return Cache::rememberForever($key, $callback);
        }

        return Cache::remember($key, $seconds, $callback);
    }
}
