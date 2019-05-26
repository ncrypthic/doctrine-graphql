<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Type;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class BuiltInTypes
{
    const FILTER_OP_LESS_THAN          = 'LT';
    const FILTER_OP_LESS_THAN_EQUAL    = 'LTE';
    const FILTER_OP_EQUAL              = 'EQ';
    const FILTER_OP_GREATER_THAN_EQUAL = 'GTE';
    const FILTER_OP_GREATER_THAN       = 'GT';
    const FILTER_OP_NOT_EQUAL          = 'NEQ';
    const SORT_ASC                     = 'ASC';
    const SORT_DESC                    = 'DESC';
    /**
     * @var DateTimeType
     */
    private static $dateTimeType;
    /**
     * @var EnumType
     */
    private static $sortDirection;
    /**
     * @var ObjectType
     */
    private static $searchFilter;
    /**
     * @var InputObjectType
     */
    private static $searchFilterInput;
    /**
     * @var EnumType
     */
    private static $searchOperator;
    /**
     * @var EnumType
     */
    private static $sortingOrientation;
    /**
     * @return DateTimeType
     */
    public static function dateTime()
    {
        return self::$dateTimeType ?: (self::$dateTimeType = new DateTimeType());
    }
    /**
     * @var EnumType
     */
    public static function sortingOrientation()
    {
        return self::$sortingOrientation ?: (self::$sortingOrientation = new EnumType([
            'name' => 'SortingOrientation',
            'description' => 'Sorting orientation (ascending or descending).',
            'values' => [
                self::SORT_ASC => [
                    'value' => self::SORT_ASC,
                    'description' => 'Ascending sort',
                ],
                self::SORT_DESC => [
                    'value' => self::SORT_DESC,
                    'description' => 'Descending sort',
                ],
            ]
        ]));
    }
    /**
     * @return EnumType
     */
    private static function searchOperator()
    {
        return self::$searchOperator ?: (self::$searchOperator = new EnumType([
            'name' => 'SearchOperator',
            'description' => 'Sorting orientation (ascending or descending).',
            'values' => [
                self::FILTER_OP_LESS_THAN => [
                    'value' => self::FILTER_OP_LESS_THAN,
                    'description' => 'Less than',
                ],
                self::FILTER_OP_LESS_THAN_EQUAL => [
                    'value' => self::FILTER_OP_LESS_THAN_EQUAL,
                    'description' => 'Less than equal',
                ],
                self::FILTER_OP_EQUAL => [
                    'value' => self::FILTER_OP_EQUAL,
                    'description' => 'Equal operator',
                ],
                self::FILTER_OP_GREATER_THAN_EQUAL => [
                    'value' => self::FILTER_OP_GREATER_THAN_EQUAL,
                    'description' => 'Greater than',
                ],
                self::FILTER_OP_GREATER_THAN => [
                    'value' => self::FILTER_OP_GREATER_THAN,
                    'description' => 'Greater than equal',
                ],
                self::FILTER_OP_NOT_EQUAL => [
                    'value' => self::FILTER_OP_NOT_EQUAL,
                    'description' => 'Not equal operator',
                ],
            ]
        ]));
    }
    /**
     * @return InputObjectType
     */
    public static function searchFilterInput()
    {
        return self::$searchFilterInput ?: (self::$searchFilterInput = new InputObjectType([
            'name' => 'SearchFilterInput',
            'fields' => [
                'operator' => self::searchOperator(),
                'value' => Type::string(),
            ]
        ]));
    }
    /**
     * @return ObjectType
     */
    public static function searchFilter()
    {
        return self::$searchFilter ?: (self::$searchFilter = new ObjectType([
            'name' => 'SearchFilter',
            'fields' => [
                'operator' => self::searchOperator(),
                'value' => Type::string(),
            ]
        ]));
    }
}
