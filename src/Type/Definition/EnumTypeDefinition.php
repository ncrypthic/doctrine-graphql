<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;

/**
 * ObjectTypeDefinition
 * 
 * This class is a meta class of \GraphQL\Type\Definition\Type
 * It will be use to create actual type of GraphQL type once
 * all the information is final.
 */
class EnumTypeDefinition implements TypeDefinition
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $description;
    /**
     * @var array
     */
    private $values;
    /**
     * @var Type
     */
    private static $graphqlType;
    /**
     * @param string $name The type name
     * @param string $description The type's description
     * @param array $fields Type type's fields
     */
    public function __construct(string $name, string $description, array $values = [])
    {
        $this->name = $name;
        $this->values = $values;
        $this->description = $description;
    }
    /**
     * Get defined values
     *
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
    /**
     * {@inheritDoc}
     */
    public function buildType(array &$types=[], array &$wrapped=[], array &$visited=[]): void
    {
        if(!isset($visited[$this->getName()])) {
            $visited[$this->getName()] = true;
            $types[$this->getName()] = new EnumType([
                'name' => $this->getName(),
                'description' => $this->description,
                'values' => $this->values
            ]);
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
