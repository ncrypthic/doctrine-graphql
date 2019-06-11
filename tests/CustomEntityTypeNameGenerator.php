<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQLTest;

use LLA\DoctrineGraphQL\EntityTypeNameGenerator;

class CustomEntityTypeNameGenerator implements EntityTypeNameGenerator
{
    /**
     * {@inheritDoc}
     */
    public function generate(string $class): string
    {
        return str_replace('LLADoctrineGraphQLTest', '', str_replace('\\', '', $class));
    }
}
