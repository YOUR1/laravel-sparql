<?php

namespace LinkedData\SPARQL\Query;

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
        if (is_array($string)) {
            return array_map(fn ($item) => new Expression($item, 'iri'), $string);
        }

        // Handle null/empty values gracefully
        if ($string === null || $string === '') {
            throw new \InvalidArgumentException('Expression::iri() requires a non-empty URI string, got: ' . var_export($string, true));
        }

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

    public static function cls($string)
    {
        return new Expression($string, 'class');
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
        } else {
            if (! $type) {
                // Handle special array format from Model::addPropertyValue
                // Format: ['value' => 'text', 'lang' => 'en', 'datatype' => 'xsd:string']
                if (is_array($value) && array_key_exists('value', $value)) {
                    $actualValue = $value['value'];
                    $lang = $value['lang'] ?? null;
                    $datatype = $value['datatype'] ?? null;

                    // Create EasyRdf Literal with language tag or datatype
                    $value = \EasyRdf\Literal::create($actualValue, $lang, $datatype);
                    $type = 'literal';
                }
                // Explicitly handle numeric types
                elseif (is_int($value)) {
                    $datatype = \EasyRdf\RdfNamespace::expand('xsd:integer');
                    $value = \EasyRdf\Literal::create($value, null, $datatype);
                    $type = 'literal';
                } elseif (is_float($value)) {
                    $datatype = \EasyRdf\RdfNamespace::expand('xsd:decimal');
                    $value = \EasyRdf\Literal::create($value, null, $datatype);
                    $type = 'literal';
                } elseif (is_bool($value)) {
                    $datatype = \EasyRdf\RdfNamespace::expand('xsd:boolean');
                    $value = \EasyRdf\Literal::create($value ? 'true' : 'false', null, $datatype);
                    $type = 'literal';
                } else {
                    // Try EasyRdf's auto-detection
                    $test_type = \EasyRdf\Literal::getDatatypeForValue($value);
                    if ($test_type) {
                        $value = \EasyRdf\Literal::create($value, null, $test_type);
                        $type = 'literal';
                    } else {
                        // Default to string for values without a detected datatype
                        $type = 'string';
                    }
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
        switch ($this->type) {
            case 'literal':
                // Properly format the literal with datatype/language tag
                if ($this->value instanceof \EasyRdf\Literal) {
                    $value = \LinkedData\SPARQL\Support\StringHelper::escapeSparqlLiteral($this->value->getValue());
                    $lang = $this->value->getLang();
                    $datatype = $this->value->getDatatype();

                    if ($lang) {
                        return sprintf('"%s"@%s', $value, $lang);
                    } elseif ($datatype) {
                        // EasyRdf returns datatypes as prefixed names (e.g. "xsd:decimal")
                        // Use them directly without angle brackets for valid SPARQL
                        return sprintf('"%s"^^%s', $value, $datatype);
                    } else {
                        return sprintf('"%s"', $value);
                    }
                }

                return $this->value->getValue();

            case 'string':
                return sprintf('"%s"', \LinkedData\SPARQL\Support\StringHelper::escapeSparqlLiteral($this->value));

            case 'iri':
                $uri = \EasyRdf\RdfNamespace::expand($this->value);

                if (filter_var($uri, FILTER_VALIDATE_URL)) {
                    return sprintf('<%s>', $uri);
                } else {
                    if (Str::startsWith($this->value, '_:')) {
                        return sprintf('<%s>', substr($this->value, 2));
                    } else {
                        return sprintf('<%s>', $this->value);
                    }
                }

                break;

            case 'class':
                // Class names should be returned as prefixed names without expansion
                return $this->value;

            case 'param':
            case 'raw':
                return $this->value;

            default:
                throw new \Exception('Unrecognized data type', 1);
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
