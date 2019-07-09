<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Util;

use Doctrine\Common\Collections\Collection;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Resolver utility
 */
class ResolverUtil
{
    public static function fieldResolver($data, $context, $args, ResolveInfo $resolveInfo)
    {
        if($data instanceof Collection) {
            return array_map(function($elmt) use($resolveInfo){
                return call_user_func([$elmt, 'get'.ucfirst($resolveInfo->fieldName)]);
            }, $data->toArray());
        } else {
            return call_user_func([$data, 'get'.ucfirst($resolveInfo->fieldName)]);
        }
    }
}
