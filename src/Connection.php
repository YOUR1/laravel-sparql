<?php

/*
SPDX-FileCopyrightText: 2020, Roberto Guido
SPDX-License-Identifier: MIT
*/

namespace SolidDataWorkers\SPARQL;

use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use MadBob\EasyRDFonGuzzle\HttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

class Connection extends BaseConnection
{
    protected $connection;
    protected $httpclient;
    protected $graph;
    protected $introspector;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connection = $this->createConnection($config);

        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultQueryGrammar();

        $this->introspector = new Query\Introspector($this, $config);
    }

    public function rdftype($collection)
    {
        $query = self::query();
        return $query->from($collection)->graph($this->graph);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        $default = new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());
        $default->graph($this->graph);
        return $default;
    }

    public function table($table, $as = null)
    {
        return $this->rdftype($table);
    }

    public function graph($graph)
    {
        $this->graph = $graph;
        return $this;
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
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $binded_query = $this->altBindValues($query, $bindings);

            // echo $query . "\n";
            // print_r($bindings);
            echo $binded_query . "\n";

            return $this->connection->query($binded_query);
        });
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) {
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

            $binded_query = $this->altBindValues($query, $bindings);

            // echo $query . "\n";
            // echo $binded_query . "\n";

            return $this->connection->query($binded_query);
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
        $this->httpclient = new HttpClient();

        if (isset($config['namespaces'])) {
            foreach(\EasyRdf\RdfNamespace::namespaces() as $prefix => $url) {
                \EasyRdf\RdfNamespace::delete($prefix);
            }

            foreach($config['namespaces'] as $prefix => $uri) {
                $this->addRdfNamespace($prefix, $uri);
            }
        }

        if (isset($config['graph'])) {
            $this->graph = $config['graph'];
        }

        if (isset($config['auth'])) {
            switch($config['auth']['type']) {
                case 'basic':
                    $this->httpclient->setOptions('auth', [$config['auth']['username'], $config['auth']['password'], 'basic']);
                    break;
                case 'digest':
                    $this->httpclient->setOptions('auth', [$config['auth']['username'], $config['auth']['password'], 'digest']);
                    break;
            }

            $handler = new CurlHandler();
            $stack = HandlerStack::create($handler);
            $this->httpclient->setOptions('handler', $stack);
        }

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

    public function getIntrospector()
    {
        return $this->introspector;
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
