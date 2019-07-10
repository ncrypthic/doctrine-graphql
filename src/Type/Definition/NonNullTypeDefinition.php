<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

use GraphQL\Type\Definition\Type;

/**
 * NonNullTypeDefinition
 *
 * This class is a meta class of \GraphQL\Type\Definition\Type
 * It will be use to create actual type of GraphQL type once
 * all the information is final.
 */
class NonNullTypeDefinition implements WrappedDefinition
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var TypeDefinition
     */
    private $typeDef;
    /**
     * @var Type
     */
    private static $graphqlType;
    /**
     * @param string $name
     * @param TypeDefinition $typeDef
     */
    public function __construct(TypeDefinition $typeDef)
    {
        $this->name = null;
        $this->typeDef = $typeDef;
    }
    /**
     * {@inheritdoc}
     */
    public function getWrappedType(bool $recurse=false): TypeDefinition
    {
        if($recurse && $this->typeDef instanceof WrappedDefinition) {
            return $this->typeDef->getWrappedType($recurse);
        }

        return $this->typeDef;
    }
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->typeDef->getName().'!';
    }
    /**
     * {@inheritDoc}
     */
    public function buildType(array &$types=[], array &$wrapped=[], array &$visited=[]): void
    {
        if(!isset($visited[$this->getName()])) {
            $pType = &$wrapped[$this->getName()];
            // Make a pointer placeholder of this wrapped type on the `wrapped` array, actual instantiation
            // of this types is when `buildRelations` called after all objects with scalar properties were
            // instantiated
            $typeName = $this->getName();
            $pType = &$typeName;
            $visited[$this->getName()] = true;
        }
    }
    /**
     * {@inheritDoc}
     */
    public function buildRelations(array &$types=[], array &$wrapped=[], array &$visited=[]): void
    {
        if(!isset($visited[$this->getName()])) {
            $this->typeDef->buildRelations($types, $wrapped, $visited);
            $pType = &$wrapped[$this->getName()];
            $base = &$types[$this->typeDef->getName()];
            $pType = Type::nonNull($base);
            $visited[$this->getName()] = true;
        }
    }
}
