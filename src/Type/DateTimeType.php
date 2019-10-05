<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Type;

use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\StringValueNode;

class DateTimeType extends ScalarType
{
    public $name = 'DateTime';

    /**
     * Serialize $value to ISO8601 date string
     *
     * @param \DateTime $value
     * @return string
     */
    public function serialize($value)
    {
        return $value->format(\DateTime::RFC3339);
    }
    /**
     * {@inheritdoc}
     */
    public function parseValue($value)
    {
        return new \DateTime($value);
    }
    /**
     * {@inheritdoc}
     */
    public function parseLiteral($valueNode, array $variables = null)
    {
        if ($valueNode instanceof StringValueNode === false) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
        }
        return $this->parseValue($valueNode->value);
    }
}
