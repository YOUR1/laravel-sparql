<?php

namespace SolidDataWorkers\SPARQL\Query\Literal;

class Double extends \EasyRdf\Literal
{
    public function __construct($value, $lang = null, $datatype = null)
    {
        parent::__construct($value, null, $datatype);
    }

    public function getValue()
    {
        return (double) $this->value;
    }
}
