<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

use GraphQL\Type\Definition\Type;

/**
 * ListTypeDefinition
 */
class ListTypeDefinition implements WrappedDefinition
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
    static $counter = 0;
    /**
     * @param string $name The type name
     * @param TypeDefinition $typeDef The underlying type definition
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
        return '['.$this->typeDef->getName().']';
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
            $pType = Type::listOf($base);
            $visited[$this->getName()] = true;
        }
    }
}
