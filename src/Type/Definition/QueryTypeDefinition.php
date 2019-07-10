<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class QueryTypeDefinition implements QueryDefinition
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var TypeDefinition
     */
    private $type;
    /**
     * @var array
     */
    private $args;
    /**
     * @var callable
     */
    private $resolver;
    /**
     * @var string
     */
    private $description;
    public function __construct(string $name, TypeDefinition $type, array $args, callable $resolver, string $description = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->args = $args;
        $this->resolver = $resolver;
        $this->description = $description;
    }
    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    /**
     * Get arguments
     *
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }
    /**
     * Get query resolver
     *
     * @return callable
     */
    public function getResolver(): callable
    {
        return $this->resolver;
    }
    /**
     * Get query return type
     *
     * @return TypeDefinition
     */
    public function getType(): TypeDefinition
    {
        return $this->type;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    /**
     * {@inheritdoc}
     */
    public function buildType(array &$types=[], array &$wrapped=[], array &$visited=[]): array
    {
        $args = [];
        $type = $types[$this->type->getName()];
        if($this->type instanceof WrappedDefinition) {
            $type = &$wrapped[$this->type->getName()];
        }
        foreach($this->args as $name=>$definition) {
            $typeDef = $definition['type'];
            if($typeDef instanceof WrappedDefinition) {
                $argType = &$wrapped[$typeDef->getName()];
            } else {
                $argType = &$types[$typeDef->getName()];
            }
            $args[$name] = array_merge($definition, ['type' => $argType]);
        }
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => $type,
            'args' => $args,
            'resolve' => $this->resolver,
        ];
    }
}
