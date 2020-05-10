<?php

namespace SolidDataWorkers\SPARQL\Query;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class Processor
{
    public function processSelect(Builder $query, $results)
    {
        $ret = new Collection();

        $obj = null;
        $next_column = null;
        $last_id = null;

        foreach($results as $row) {
            foreach($row as $param => $value) {
                $value = (string) $value;

                if ($param == substr($query->unique_subject, 1)) {
                    if ($last_id != $value) {
                        $obj = $ret->where('id', $value)->first();

                        if (is_null($obj)) {
                            $obj = (object)[
                                'id' => $value
                            ];

                            $ret->push($obj);
                            $last_id = $value;
                        }
                    }
                }
                else if ($param == 'prop') {
                    $next_column = $value;
                }
                else {
                    $column_name = null;

                    foreach($query->wheres as $where) {
                        if (isset($where['value']) && $where['value'][0] == '?' && substr($where['value'], 1) == $param) {
                            $column_name = $where['column'];
                            break;
                        }
                    }

                    if (!$column_name) {
                        if ($next_column) {
                            $column_name = $next_column;
                            $next_column = null;
                        }
                        else {
                            $column_name = $param;
                        }
                    }

                    if (filter_var($column_name, FILTER_VALIDATE_URL)) {
                        $short = \EasyRdf\RdfNamespace::shorten($column_name);
                        if (!is_null($short)) {
                            $column_name = $short;
                        }
                    }

                    if (is_null($obj)) {
                        $obj = (object)[];
                        $ret->push($obj);
                    }

                    if (isset($obj->$column_name)) {
                        if (is_array($obj->$column_name)) {
                            $obj->$column_name[] = $value;
                        }
                        else {
                            $obj->$column_name = [$obj->$column_name, $value];
                        }
                    }
                    else {
                        $obj->$column_name = $value;
                    }
                }
            }
        }

        return $ret;
    }

    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $query->getConnection()->insert($sql, $values);
        return $query->unique_subject;
    }

    public function processColumnListing($results)
    {
        return $results;
    }
}
