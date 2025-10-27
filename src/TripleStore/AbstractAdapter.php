<?php

namespace LinkedData\SPARQL\TripleStore;

/**
 * Abstract base class for triple store adapters.
 *
 * Provides common functionality shared across all implementations.
 */
abstract class AbstractAdapter implements TripleStoreAdapter
{
    /**
     * {@inheritdoc}
     */
    public function isSuccessResponse(int $statusCode, string $responseBody): bool
    {
        // Standard HTTP success range
        return $statusCode >= 200 && $statusCode < 300;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryHints(): array
    {
        // No special hints by default
        return [];
    }

    /**
     * Extract the dataset name from a SPARQL endpoint URL.
     *
     * Example: http://localhost:3030/test/sparql -> test
     */
    protected function extractDatasetName(string $endpoint): ?string
    {
        // Match pattern: http://host:port/dataset/sparql
        if (preg_match('~^https?://[^/]+/([^/]+)(?:/(?:sparql|update|query|data))?/?$~', $endpoint, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get the base URL without path segments.
     *
     * Example: http://localhost:3030/test/sparql -> http://localhost:3030
     */
    protected function getBaseUrl(string $endpoint): string
    {
        $parsed = parse_url($endpoint);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? 'localhost';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    /**
     * Remove trailing SPARQL service names from path.
     *
     * Removes common endpoint names: /sparql, /update, /query, /data
     */
    protected function removeServiceSuffix(string $endpoint): string
    {
        return preg_replace('~/(sparql|update|query|data)/?$~', '', $endpoint);
    }
}
