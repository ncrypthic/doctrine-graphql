<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

interface QueryDefinition
{
    /**
     * Get type name
     *
     * @return string
     */
    public function getName(): string;
    /**
     * Build query object field configuration array
     *
     * @param *array $types
     * @param *array $wrapped
     * @param *array $visitor
     * @return array
     */
    public function buildType(array &$types=[], array &$wrapped=[], array &$visitor=[]): array;
    /**
     * Get arguments
     *
     * @return array
     */
    public function getArgs(): array;
    /**
     * Get query resolver
     *
     * @return callable
     */
    public function getResolver(): callable;
    /**
     * Get query return type
     *
     * @return TypeDefinition
     */
    public function getType(): TypeDefinition;
}
