<?php

namespace SolidDataWorkers\SPARQL;

use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Connection extends BaseConnection
{
    protected $connection;
    protected $httpclient;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connection = $this->createConnection($config);

        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultQueryGrammar();
    }

    public function rdftype($collection)
    {
        $query = self::query();
        return $query->from($collection);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        return new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    public function table($table)
    {
        return $this->rdftype($table);
    }

    private function altBindValues($query, $bindings)
    {
        $index = 0;

        return preg_replace_callback('/ \? /', function($matches) use (&$index, $bindings) {
            $value = $bindings[$index++];

            if (is_string($value) && preg_match('/^<.*>$/', $value) === 0 && preg_match('/^\?.*$/', $value) === 0) {
                $value = "'" . $value . "'";
            }

            return ' ' . $value . ' ';
        }, $query);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $binded_query = $this->altBindValues($query, $bindings);
            echo $query . "\n";
            echo $binded_query . "\n";

            return $this->connection->query($binded_query);
        });
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $binded_query = $this->altBindValues($query, $bindings);
            $ret = $this->connection->query($binded_query);
        });

        foreach($ret as $record) {
            yield $record;
        }
    }

    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);
            $this->bindValues($statement, $this->prepareBindings($bindings));
            $this->recordsHaveBeenModified();
            return $statement->execute();
        });
    }

    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $statement = $this->getPdo()->prepare($query);
            $this->bindValues($statement, $this->prepareBindings($bindings));
            $statement->execute();
            $this->recordsHaveBeenModified(($count = $statement->rowCount()) > 0);
            return $count;
        });
    }

    public function unprepared($query)
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }
            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->exec($query) !== false
            );
            return $change;
        });
    }

    public function getHttpClient()
    {
        return $this->httpclient;
    }

    public function addRdfNamespace($prefix, $uri)
    {
        \EasyRdf\RdfNamespace::set($prefix, $uri);
    }

    public function getRdfNamespaces()
    {
        return \EasyRdf\RdfNamespace::namespaces();
    }

    protected function createConnection(array $config)
    {
        if (isset($config['namespaces'])) {
            foreach($config['namespaces'] as $prefix => $uri) {
                $this->addRdfNamespace($prefix, $uri);
            }
        }

        $this->httpclient = new \EasyRdf\Http\Client();
        \EasyRdf\Http::setDefaultHttpClient($this->httpclient);

        return new \EasyRdf\Sparql\Client($config['host']);
    }

    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->connection)) {
            $this->reconnect();
        }
    }

    public function disconnect()
    {
        unset($this->connection);
    }

    public function getDriverName()
    {
        return 'sparql';
    }

    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }

    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
    }
}
