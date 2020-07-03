<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Mutation;

interface MutationListenerInterface
{
    const MUTATION_CREATE = 'graphql:query:create';
    const MUTATION_UPDATE = 'graphql:query:update';
    const MUTATION_DELETE = 'graphql:query:delete';

    public function onCreate(callable $callable);
    public function onUpdate(callable $callable);
    public function onDelete(callable $callable);
}
