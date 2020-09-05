<?php

/*
SPDX-FileCopyrightText: 2020, Roberto Guido
SPDX-License-Identifier: MIT
*/

namespace SolidDataWorkers\SPARQL\Query;

class Expression
{
    /**
     * The value of the expression.
     *
     * @var mixed
     */
    protected $value;

    /**
     * The type of the value.
     *
     * @var mixed
     */
    protected $type;

    public static function str($string)
    {
        return new Expression($string, 'string');
    }

    public static function lit($string)
    {
        return new Expression($string, 'literal');
    }

    public static function urn($string)
    {
        return new Expression($string, 'urn');
    }

    public static function cls($string)
    {
        return new Expression($string, 'class');
    }

    public static function par($string)
    {
        return new Expression($string, 'param');
    }

    /**
     * Create a new raw query expression.
     *
     * @param  mixed  $value
     * @return void
     */
    public function __construct($value, $type = 'string')
    {
        if (is_a($value, self::class)) {
            $this->value = $value->value;
        }
        else {
            $this->value = $value;
        }

        if (!is_string($this->value)) {
            $type = 'literal';
        }

        $this->type = $type;
    }

    /**
     * Get the value of the expression.
     *
     * @return mixed
     */
    public function getValue()
    {
        switch($this->type) {
            case 'string':
                return sprintf('"%s"', $this->value);

            case 'uri':
            case 'urn':
                return sprintf('<%s>', $this->value);

            case 'class':
                $uri = \EasyRdf\RdfNamespace::expand($this->value);

                if (filter_var($uri, FILTER_VALIDATE_URL)) {
                    return sprintf('<%s>', $uri);
                }
                else {
                    return sprintf('<%s>', $this->value);
                }

                break;

            case 'param':
            case 'literal':
            default:
                return $this->value;
        }
    }

    /**
     * Get the type of the expression.
     *
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    public static function is($value, $type)
    {
        return $value && $value instanceof self && $value->getType() == $type;
    }

    public static function same($first, $second)
    {
        return $first && $second && $first instanceof self && $second instanceof self && $first->getType() == $second->getType() && $first->getValue() == $second->getValue();
    }

    /**
     * Get the value of the expression.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getValue();
    }
}
