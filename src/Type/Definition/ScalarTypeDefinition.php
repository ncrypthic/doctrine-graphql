<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

use GraphQL\Type\Definition\Type;

/**
 * ScalarTypeDefinition
 */
class ScalarTypeDefinition implements TypeDefinition
{
    /**
     * @var Type
     */
    private $type;
    /**
     * @param string $name
     */
    public function __construct(Type $type)
    {
        $this->type = $type;
    }
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->type->name;
    }
    /**
     * {@inheritDoc}
     */
    public function buildType(array &$types=[], array &$wrapped=[], array &$visited=[]): void
    {
        if(!isset($types[$this->getName()])) {
            $visited[$this->getName()] = true;
            $types[$this->getName()] = $this->type;
        }
    }
    /**
     * {@inheritDoc}
     */
    public function buildRelations(array &$types=[], array &$wrapped=[], array &$visited=[]): void
    {
        if(!isset($visited[$this->getName()])) {
            $visited[$this->getName()] = true;
        }
    }
}
