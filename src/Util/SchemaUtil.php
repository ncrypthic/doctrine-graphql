<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Util;

use GraphQL\Type\Definition\Type;
use LLA\DoctrineGraphQL\Type\BuiltInTypes;

class SchemaUtil
{
    /**
     * Change FQDN $className to graphql object type by stripping '\'
     * character
     *
     * @param string $className
     * @return string
     */
    public static function mkObjectName($className): string
    {
        return str_replace('\\', '', $className);
    }
    /**
     * Make graphql type
     *
     * @param GraphQL\Type\Definition\Type $type
     * @param bool $isNullable
     * @return GraphQL\Type\Definition\Type $type
     */
    public static function mkGraphqlType(Type $type, $isNullable)
    {
        return $isNullable ? $type : Type::nonNull($type);
    }
    /**
     * Map doctrine type to graphql type
     *
     * @param string $type Doctrine type
     * @param bool $isNullable Default: false
     * @return Maybe
     */
    public static function mapTypeToGraphqlType($type, $isNullable=false, $isList=false): Maybe
    {
        switch($type) {
        case 'integer':
            $res = self::mkGraphqlType(Type::int(), $isNullable);
            return Maybe::Some($res);
        case 'bigint':
            $res = self::mkGraphqlType(Type::int(), $isNullable);
            return Maybe::Some($res);
        case 'smallint':
            $res = self::mkGraphqlType(Type::int(), $isNullable);
            return Maybe::Some($res);
        case 'uuid':
            $res = self::mkGraphqlType(Type::string(), $isNullable);
            return Maybe::Some($res);
        case 'string':
            $res = self::mkGraphqlType(Type::string(), $isNullable);
            return Maybe::Some($res);
        case 'text':
            $res = self::mkGraphqlType(Type::string(), $isNullable);
            return Maybe::Some($res);
        case 'date':
            $res = self::mkGraphqlType(BuiltInTypes::dateTime(), $isNullable);
            return Maybe::Some($res);
        case 'decimal':
            $res = self::mkGraphqlType(Type::float(), $isNullable);
            return Maybe::Some($res);
        case 'float':
            $res = self::mkGraphqlType(Type::float(), $isNullable);
            return Maybe::Some($res);
        case 'boolean':
            $res = self::mkGraphqlType(Type::boolean(), $isNullable);
            return Maybe::Some($res);
        case 'time':
            $res = self::mkGraphqlType(BuiltInTypes::dateTime(), $isNullable);
            return Maybe::Some($res);
        case 'date':
            $res = self::mkGraphqlType(BuiltInTypes::dateTime(), $isNullable);
            return Maybe::Some($res);
        case 'datetime':
            $res = self::mkGraphqlType(BuiltInTypes::dateTime(), $isNullable);
            return Maybe::Some($res);
        case 'datetimetz':
            $res = self::mkGraphqlType(BuiltInTypes::dateTime(), $isNullable);
            return Maybe::Some($res);
        default:
            return Maybe::None();
        }
    }
}
