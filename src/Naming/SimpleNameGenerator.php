<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Naming;

class SimpleNameGenerator implements GeneratorInterface
{
    /**
     * {@inheritDoc}
     */
    public function generate(string $class): string
    {
        return str_replace('\\', '', $class);
    }
}
