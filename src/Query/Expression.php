<?php

/*
SPDX-FileCopyrightText: 2020, Roberto Guido
SPDX-License-Identifier: MIT
*/

namespace SolidDataWorkers\SPARQL\Query;

use Illuminate\Support\Str;

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

    public static function lit($literal)
    {
        return new Expression($literal, 'literal');
    }

    public static function str($string)
    {
        return new Expression($string, 'string');
    }

    public static function iri($string)
    {
        return new Expression($string, 'iri');
    }

    public static function raw($string)
    {
        return new Expression($string, 'raw');
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
    public function __construct($value, $type = null)
    {
        if (is_a($value, self::class)) {
            $this->value = $value->value;
            $this->type = $value->type;
        }
        else {
            if (!$type) {
                $test_type = \EasyRdf\Literal::getDatatypeForValue($value);
                if ($test_type) {
                    $value = \EasyRdf\Literal::create($value, null, $test_type);
                    $type = 'literal';
                }
                else {
                    $type = 'string';
                }
            }

            $this->value = $value;
            $this->type = $type;
        }
    }

    /**
     * Get the value of the expression.
     *
     * @return mixed
     */
    public function getValue()
    {
        switch($this->type) {
            case 'literal':
                return $this->value->getValue();

            case 'string':
                return sprintf('"%s"', addslashes($this->value));

            case 'iri':
                $uri = \EasyRdf\RdfNamespace::expand($this->value);

                if (filter_var($uri, FILTER_VALIDATE_URL)) {
                    return sprintf('<%s>', $uri);
                }
                else {
                    if (Str::startsWith($this->value, '_:')) {
                        return sprintf('<%s>', substr($this->value, 2));
                    }
                    else {
                        return sprintf('<%s>', $this->value);
                    }
                }

                break;

            case 'param':
            case 'raw':
                return $this->value;

            default:
                throw new \Exception("Unrecognized data type", 1);
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
