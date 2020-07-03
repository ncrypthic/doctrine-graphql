<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Query;

use GraphQL\Type\Definition\ResolveInfo;

interface QueryManagerInterface
{
    /**
     * Get a single object with specified $args
     *
     * @param $rootValue
     * @param $args
     * @return object
     */
    public function get($rootValue, $args, $ctx, ResolveInfo $resolveInfo): object;
    /**
     * Get list of objects with specified $args
     * @param $rootValue
     * @param $args
     * @return object
     */
    public function getMany($rootValue, $args, $ctx, ResolveInfo $resolveInfo): array;
    /**
     * Get paginated list of objects with specified $args
     *
     * @param $rootValue
     * @param $args
     * @return object
     */
    public function getPage($rootValue, $args, $ctx, ResolveInfo $resolveInfo): object;
}
