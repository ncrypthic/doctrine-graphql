<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * ObjectTypeDefinition
 * 
 * This class is a meta class of \GraphQL\Type\Definition\Type
 * It will be use to create actual type of GraphQL type once
 * all the information is final.
 */
class ObjectTypeDefinition implements TypeDefinition
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var array
     */
    private $fields;
    /**
     * @var string
     */
    private $description;
    /**
     * @var Type
     */
    private static $graphqlType;
    /**
     * @param string $name The type name
     * @param string $description The type's description
     * @param array $fields Type type's fields
     */
    public function __construct(string $name, string $description, array $fields = [])
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->description = $description;
    }
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
    /**
     * Get specified fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }
    /**
     * Add a field
     *
     * @param string $name The field's name
     * @param TypeDefinition $type The field's type
     * @param array $config The fields misc. configuration
     * @return ObjectTypeDefinition
     */
    public function addField(string $name, TypeDefinition $type, array $config = []): ObjectTypeDefinition
    {
        $this->fields[$name] = array_merge($config, [ 'type' => $type ]);

        return $this;
    }
    /**
     * Remove a named field
     *
     * @return ObjectTypeDefinition
     */
    public function removeField(string $name): ObjectTypeDefinition
    {
        if(isset($this->fields[$name])) {
            unset($this->fields[$name]);
        }

        return $this;
    }
    /**
     * {@inheritDoc}
     */
    public function buildType(array &$types=[], array &$wrapped=[], array &$visited=[]): void
    {
        if(!isset($visited[$this->getName()])) {
            $visited[$this->getName()] = true;
            $fields = [];
            foreach($this->fields as $name=>$definition) {
                $typeDef = $definition['type'];
                $typeName = $typeDef->getName();
                $isWrapped = $typeDef instanceof WrappedDefinition;
                $typeDef->buildType($types, $wrapped, $visited);
                // Wrapped type is a pseudo type, so it shouldn't be listed on the types array
                // but it is needed for sanitity type checking, so we used separate array for wrapped
                // types
                if($isWrapped) {
                    $fields[$name] = array_merge($definition, ['type' => &$wrapped[$typeName]]);
                } else {
                    $fields[$name] = array_merge($definition, ['type' => &$types[$typeName]]);
                }
            }
            $types[$this->getName()] = new ObjectType([
                'name' => $this->name,
                'description' => $this->description,
                'fields' => $fields,
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
            foreach($this->fields as $name=>$definition) {
                $typeDef  = $definition['type'];
                $typeDef->buildRelations($types, $wrapped, $visited);
            }
        }
    }
}
