<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Util;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use GraphQL\Type\Definition\InputObjectType;
use LLA\DoctrineGraphQL\Type\BuiltInTypes;

class QueryUtil
{
    /**
     * @param QueryBuilder $queryBuilder
     * @param string $alias Entity alias
     * @param string $field Field name
     * @param array $filters Array of SearchFilterInput type values
     * @param callable $callback
     * @return QueryBuilder
     */
    public static function iteratePredicates(string $alias, string $field, array $filters, callable $callback): void
    {
        $parameters = [];
        $expresions = [];
        foreach($filters as $filter) {
            $expr = new Expr();
            $paramName = ":{$alias}_{$field}";
            $parameters[$paramName] = $filter['value'];
            switch($filter['operator']) {
                case BuiltInTypes::FILTER_OP_LESS_THAN:
                    $expresions[] = $expr->lt("{$alias}.{$field}", $paramName);
                    break;
                case BuiltInTypes::FILTER_OP_LESS_THAN_EQUAL:
                    $expresions[] = $expr->lte("{$alias}.{$field}", $paramName);
                    break;
                case BuiltInTypes::FILTER_OP_EQUAL:
                    $expresions[] = $expr->eq("{$alias}.{$field}", $paramName);
                    break;
                case BuiltInTypes::FILTER_OP_GREATER_THAN:
                    $expresions[] = $expr->gt("{$alias}.{$field}", $paramName);
                    break;
                case BuiltInTypes::FILTER_OP_GREATER_THAN_EQUAL:
                    $expresions[] = $expr->gte("{$alias}.{$field}", $paramName);
                    break;
                case BuiltInTypes::FILTER_OP_NOT_EQUAL:
                    $expresions[] = $expr->neq("{$alias}.{$field}", $paramName);
                    break;
            }
        }
        call_user_func_array($callback, [$expresions, $parameters]);
    }
    /**
     * @param QueryBuilder $queryBuilder
     * @param InputObjectType $type
     * @param array $filters
     * @param string $alias
     * @param callable $callback
     * @return void
     */
    public static function walkFilters(QueryBuilder &$queryBuilder, InputObjectType $type, array $filters, string $alias, callable $callback): void
    {
        if(!isset($type->config['fields'])) return;

        foreach($type->config['fields'] as $fieldName => $fieldConfig) {
            if(!isset($filters[$fieldName])) continue;

            $filter = $filters[$fieldName];
            $fieldType = $fieldConfig['type'];
            $fieldAlias = "$alias{$fieldName[0]}";
            if($fieldType instanceof InputObjectType) {
                $queryBuilder->join("$alias.$fieldName", $fieldAlias);
                self::walkFilters($queryBuilder, $fieldType, $filter, $fieldAlias, $callback);
            } else {
                self::iteratePredicates($alias, $fieldName, $filter, $callback);
            }
        }
    }
}
