<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type;

use GraphQL\Type\Schema;
use LLA\DoctrineGraphQL\Type\Definition\MutationDefinition;
use LLA\DoctrineGraphQL\Type\Definition\QueryDefinition;
use LLA\DoctrineGraphQL\Type\Definition\TypeDefinition;
use LLA\DoctrineGraphQL\Util\Maybe;

interface RegistryInterface
{
    /**
     * Register a type configuration
     *
     * @param TypeDefinition $typeDef
     * @return RegistryInterface
     */
    public function addType(TypeDefinition $typeDef): RegistryInterface;
    /**
     * Add a query
     *
     * @return RegistryInterface
     */
    public function addQuery(QueryDefinition $query): RegistryInterface;
    /**
     * Add a mutation
     *
     * @return RegistryInterface
     */
    public function addMutation(MutationDefinition $mutation): RegistryInterface;
    /**
     * Get a type, returns a maybe type
     *
     * @param string $name Type name
     * @return Maybe
     */
    public function getType(string $name): Maybe;
    /**
     * Map doctrine type to graphql type
     *
     * @param string $type Doctrine's type name
     * @param boolean $isNullable Default: false
     * @param boolean $isList Default: false
     * @return Maybe
     */
    public function mapDoctrineType(string $doctrineType, bool $isNullable, bool $isList): Maybe;
    /**
     * Build graphql schema of defined types, queries and mutations
     *
     * @return Schema
     */
    public function buildSchema(): Schema;
}
