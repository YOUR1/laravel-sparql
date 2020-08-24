<?php

/*
SPDX-FileCopyrightText: 2020, Roberto Guido
SPDX-License-Identifier: MIT
*/

namespace SolidDataWorkers\SPARQL\Query;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use Cache;

class Introspector
{
    protected $graph;
    protected $models_map;

    /*
        This provides the default names for generated classes mapping the RDF ontologies
    */
    private function modelName($class)
    {
        return 'Model' . Str::ucfirst(Str::camel(preg_replace('/[^a-zA-Z0-9:]*/', '', str_replace(':', '-', $class))));
    }

    private function generateClass($class)
    {
        $class = \EasyRdf\RdfNamespace::shorten($class);
        $modelname = $this->modelName($class);

        $fullclassname = 'SolidDataWorkers\SPARQL\Eloquent\\' . $modelname;
        if (class_exists($fullclassname)) {
            return $modelname;
        }

        $string = <<<CLASS
namespace SolidDataWorkers\SPARQL\Eloquent;
use SolidDataWorkers\SPARQL\Eloquent\Model;

class $modelname extends Model {
    protected \$table = '$class';
}
CLASS;

        eval($string);
        return $fullclassname;
    }

    public function __construct($connection, $config)
    {
        /*
            Here we leverage the Laravel's cache to keep the reference graph,
            instead of fetching the ontologies every time.
            When the required ontologies are changed, the previous cached graph
            is invalidated and regenerated.
        */
        $required_namespaces = \EasyRdf\RdfNamespace::namespaces();
        $required_namespaces_key = join(',', array_keys($required_namespaces));
        $loaded_ontologies = Cache::get('sparql_introspector_graph_ontologies');

        if ($loaded_ontologies != $required_namespaces_key) {
            Cache::put('sparql_introspector_graph_ontologies', $required_namespaces_key);
            Cache::forget('sparql_introspector_graph');
        }

        $this->graph = Cache::rememberForever('sparql_introspector_graph', function() use ($required_namespaces) {
            $graph = new \EasyRdf\Graph();

            foreach($required_namespaces as $prefix => $url) {
                try {
                    $graph->load($url);
                }
                catch(\Exception $e) {
                    echo "Unable to load ontology from $url\n";
                }
            }

            return $graph;
        });

        if (isset($config['ontologies'])) {
            $others = Arr::wrap($config['ontologies']);
            foreach($others as $o) {
                $this->graph->parseFile($o);
            }
        }

        $this->models_map = [];

        foreach(get_declared_classes() as $item) {
            if (is_subclass_of($item, 'SolidDataWorkers\SPARQL\Eloquent\Model')) {
                $i = new $item();
                $this->models_map[$i->getTable()] = $item;
                unset($i);
            }
        }

        $base_classes = ['http://www.w3.org/2002/07/owl#Class'];
        if (isset($config['base_classes'])) {
            $base_classes = Arr::wrap($config['base_classes']);
        }

        foreach($base_classes as $bc) {
            foreach($this->graph->allOfType($bc) as $resource) {
                $class_id = $resource->getUri();
                $short_class_id = \EasyRdf\RdfNamespace::shorten($class_id);

                if (!isset($this->models_map[$class_id]) && !isset($this->models_map[$short_class_id])) {
                    $modelname = $this->generateClass($class_id);
                    $this->models_map[$class_id] = $modelname;
                    $this->models_map[$short_class_id] = $modelname;
                }
            }
        }
    }

    public function getModel($rdf_type)
    {
        return $this->models_map[$rdf_type] ?? null;
    }

    public function getClassesInternal($rdf_type, &$managed)
    {
        $managed[] = $rdf_type->getUri();

        $aliases = ['owl:equivalentClass'];

        foreach($aliases as $alias) {
            foreach ($rdf_type->all($alias) as $other) {
                if (in_array($other->getUri(), $managed)) {
                    continue;
                }

                $this->getClassesInternal($other, $managed);
            }

            $equivalent = $this->graph->resourcesMatching($alias, ['type' => 'uri', 'value' => $rdf_type->getUri()]);
            if (!empty($equivalent)) {
                foreach ($equivalent as $other) {
                    if (in_array($other->getUri(), $managed)) {
                        continue;
                    }

                    $this->getClassesInternal($other, $managed);
                }
            }
        }

        foreach ($rdf_type->all('rdfs:subClassOf') as $other) {
            if (in_array($other->getUri(), $managed)) {
                continue;
            }

            $this->getClassesInternal($other, $managed);
        }
    }

    public function getClasses($rdf_type)
    {
        $classes = [];
        $resource = $this->graph->resource($rdf_type);
        $this->getClassesInternal($resource, $classes);
        return array_unique($classes);
    }

    public function propertyDatatype($type)
    {
        static $done = [];

        if (is_string($type)) {
            $type = $this->graph->resource($type);
        }

        if (is_null($type)) {
            return null;
        }

        $type_uri = $type->shorten();

        if (isset($done[$type_uri])) {
            return $done[$type_uri];
        }

        $ret = (object) [];

        $label = $type->getLiteral('rdfs:label');
        if ($label) {
            $ret->label = $label->getValue();
        }

        $comment = $type->getLiteral('rdfs:comment');
        if ($comment) {
            $ret->comment = $comment->getValue();
        }

        $range = $type->get('rdfs:range');
        if ($range) {
            $ret->range = $range->toRdfPhp();
        }

        $done[$type_uri] = $ret;
        return $ret;
    }

    /*
        TODO: enclose datetime contents in Carbon objects
    */
    public function encloseProperty($key, $value)
    {
        if (is_array($value)) {
            $ret = new Collection();

            foreach($value as $v) {
                $ret->push($this->encloseProperty($key, $v));
            }

            return $ret;
        }
        else {
            $value = (string) $value;

            $attr_meta = $this->propertyDatatype($key);
            if ($attr_meta) {
                if (isset($attr_meta->range)) {
                    if ($attr_meta->range['type'] == 'uri') {
                        $model = $this->getModel($attr_meta->range['value']);
                        if ($model) {
                            $relation = new $model();
                            $relation->id = $value;
                            $value = $relation;
                        }
                    }
                }
            }

            return $value;
        }
    }

    public function getProperties($rdf_type)
    {
        static $done = [];

        if (isset($done[$rdf_type])) {
            return $done[$rdf_type];
        }

        $properties = [];
        $classes = $this->getClasses($rdf_type);

        foreach($classes as $c) {
            $attributes = $this->graph->resourcesMatching('rdfs:domain', ['type' => 'uri', 'value' => $c]);
            foreach($attributes as $attr) {
                $attr_uri = $attr->shorten();

                if (isset($properties[$attr_uri])) {
                    continue;
                }

                $properties[$attr_uri] = $this->propertyDatatype($attr);
            }
        }

        $done[$rdf_type] = $properties;

        return $properties;
    }
}
