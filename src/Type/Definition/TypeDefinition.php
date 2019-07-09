<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

use GraphQL\Type\Definition\Type;

/**
 * TypeDefinition
 */
interface TypeDefinition
{
    /**
     * Get type name
     *
     * @return string
     */
    public function getName(): string;
    /**
     * Build \GraphQL\Type\Definition\Type instance from a definition
     *
     * @param *array $types
     * @param *array $wrapped
     * @param *array $visited
     */
    public function buildType(array &$types=[], array &$wrapped=[], array &$visited=[]): void;
    /**
     * Build \GraphQL\Type\Definition\Type instance from a definition
     *
     * @param *array $types
     * @param *array $wrapped
     * @param *array $visited
     */
    public function buildRelations(array &$types=[], array &$wrapped=[], array &$visited=[]): void;
}
