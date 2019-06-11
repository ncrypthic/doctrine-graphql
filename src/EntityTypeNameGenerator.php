<?php
namespace LLA\DoctrineGraphQL;

interface EntityTypeNameGenerator
{
    /**
     * Generate GraphQL type name for a class name
     *
     * @param string $class
     * @return string
     */
    public function generate(string $class): string;
}
