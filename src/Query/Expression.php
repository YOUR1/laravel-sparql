<?php

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

            case 'param':
            case 'literal':
                return $this->value;

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
