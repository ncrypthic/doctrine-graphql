<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Type\Definition;

interface WrappedDefinition extends TypeDefinition
{
    /**
     * Get wrapped type
     *
     * @param bool $recurse
     * @return TypeDefinition
     */
    public function getWrappedType(bool $recurse=false): TypeDefinition;
}

