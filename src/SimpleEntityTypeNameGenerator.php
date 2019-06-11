<?php
namespace LLA\DoctrineGraphQL;

class SimpleEntityTypeNameGenerator implements EntityTypeNameGenerator
{
    /**
     * {@inheritDoc}
     */
    public function generate(string $class): string
    {
        return str_replace('\\', '', $class);
    }
}

