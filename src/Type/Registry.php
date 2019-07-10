<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Type;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use LLA\DoctrineGraphQL\Type\Definition\EnumTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\InputTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ListTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\MutationDefinition;
use LLA\DoctrineGraphQL\Type\Definition\NonNullTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ObjectTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\QueryDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ScalarTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\TypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\WrappedDefinition;
use LLA\DoctrineGraphQL\Util\Maybe;

class Registry implements RegistryInterface
{
    const FILTER_OP_LESS_THAN          = 'LT';
    const FILTER_OP_LESS_THAN_EQUAL    = 'LTE';
    const FILTER_OP_EQUAL              = 'EQ';
    const FILTER_OP_GREATER_THAN_EQUAL = 'GTE';
    const FILTER_OP_GREATER_THAN       = 'GT';
    const FILTER_OP_NOT_EQUAL          = 'NEQ';
    /**
     * @var array
     */
    private $types;
    /**
     * @var ObjectType
     */
    private $queries;
    /**
     * @var InputObjectType
     */
    private $mutations;

    public function __construct()
    {
        $filterOperators = [
            Registry::FILTER_OP_LESS_THAN => [ 'value' => Registry::FILTER_OP_LESS_THAN ],
            Registry::FILTER_OP_LESS_THAN_EQUAL => [ 'value' => Registry::FILTER_OP_LESS_THAN_EQUAL ],
            Registry::FILTER_OP_EQUAL => [ 'value' => Registry::FILTER_OP_EQUAL ],
            Registry::FILTER_OP_GREATER_THAN_EQUAL => [ 'value' => Registry::FILTER_OP_GREATER_THAN_EQUAL ],
            Registry::FILTER_OP_GREATER_THAN => [ 'value' => Registry::FILTER_OP_GREATER_THAN ],
            Registry::FILTER_OP_NOT_EQUAL => [ 'value' => Registry::FILTER_OP_NOT_EQUAL ],
        ];
        $searchOperator = new EnumTypeDefinition('SearchOperator', 'Search filter operator', $filterOperators);
        $int = new ScalarTypeDefinition(Type::int());
        $float = new ScalarTypeDefinition(Type::float());
        $bool = new ScalarTypeDefinition(Type::boolean());
        $string = new ScalarTypeDefinition(Type::string());
        $datetime = new ScalarTypeDefinition(new DateTimeType());
        $this->types = [
            'Int' => $int,
            'Float' => $float,
            'Boolean' => $bool,
            'String' => $string,
            'DateTime' => $datetime,
            'Int!' => new NonNullTypeDefinition($int),
            'Float!' => new NonNullTypeDefinition($float),
            'Boolean!' => new NonNullTypeDefinition($bool),
            'String!' => new NonNullTypeDefinition($string),
            'DateTime!' => new NonNullTypeDefinition($datetime),
            '[Int]' => new ListTypeDefinition($int),
            '[Float]' => new ListTypeDefinition($float),
            '[Boolean]' => new ListTypeDefinition($bool),
            '[String]' => new ListTypeDefinition($string),
            '[DateTime]' => new ListTypeDefinition($datetime),
            'SearchOperator' => $searchOperator,
            'SearchFilterInput' => new InputTypeDefinition('SearchFilterInput', '', ['operator' => ['type' => $searchOperator], 'value' => ['type' => $string]]),
            'SearchFilter' => new ObjectTypeDefinition('SearchFilter', '', ['operator' => ['type' => $searchOperator], 'value' => ['type' => $string]]),
            'SortingOrientation' => new EnumTypeDefinition('SortingOrientation', '', ['DESC' => ['value' => 'desc'], 'ASC' => ['value' => 'asc']]),
        ];
        $this->queries = [];
        $this->mutations = [];
    }
    /**
     * {@inheritDoc}
     */
    public function addType(TypeDefinition $typeDef): RegistryInterface
    {
        $this->types[$typeDef->getName()] = $typeDef;

        return $this;
    }
    /**
     * {@inheritDoc}
     */
    public function getType(string $name): Maybe
    {
        if(isset($this->types[$name])) {
            $type = $this->types[$name];
            return Maybe::Some($type);
        }

        return Maybe::None();
    }
    /**
     * {@inheritDoc}
     */
    public function mapDoctrineType(string $name, bool $isNullable, bool $isList): Maybe
    {
        $actual = null;
        switch($name) {
            case 'integer':
                $actual = 'Int';
                break;
            case 'bigint':
                $actual = 'Int';
                break;
            case 'smallint':
                $actual = 'Int';
                break;
            case 'float':
                $actual = 'Float';
                break;
            case 'decimal':
                $actual = 'Float';
                break;
            case 'boolean':
                $actual = 'Boolean';
                break;
            case 'uuid':
                $actual = 'String';
                break;
            case 'string':
                $actual = 'String';
                break;
            case 'text':
                $actual = 'String';
                break;
            case 'date':
                $actual = 'DateTime';
                break;
            case 'Time':
                $actual = 'DateTime';
                break;
            case 'datetime':
                $actual = 'DateTime';
                break;
            case 'dateTimez':
                $actual = 'DateTime';
                break;
        }
        if($actual === null) {
            return Maybe::None();
        }
        if($isNullable === false) {
            $type = $this->types["{$actual}!"];

            return Maybe::Some($type);
        }
        if($isList === true) {
            $type = $this->types["[{$actual}]"];

            return Maybe::Some($type);
        }

        return isset($this->types[$actual])
            ? Maybe::Some($this->types[$actual])
            : Maybe::None();
    }
    /**
     * {@inheritDoc}
     */
    public function addQuery(QueryDefinition $query): RegistryInterface
    {
        $this->queries[$query->getName()] = $query;

        return $this;
    }
    /**
     * {@inheritDoc}
     */
    public function addMutation(MutationDefinition $mutation): RegistryInterface
    {
        $this->mutations[$mutation->getName()] = $mutation;

        return $this;
    }
    /**
     * {@inheritDoc}
     */
    public function buildSchema(): Schema
    {
        $types = [];
        $wrappedTypes = [];
        $typeVisitor = [];
        $associationVisitor = [];
        $queries = [];
        $mutations = [];
        foreach($this->types as $name=>$type) {
            $type->buildType($types, $wrappedTypes, $typeVisitor);
        }
        $relationalVisitor = [];
        foreach($this->types as $name=>$type) {
            $type->buildRelations($types, $wrappedTypes, $relationalVisitor);
        }
        foreach($this->queries as $type) {
            $queries[$type->getName()] = $type->buildType($types, $wrappedTypes, $typeVisitor);
        }
        foreach($this->mutations as $type) {
            $mutations[$type->getName()] = $type->buildType($types, $wrappedTypes, $typeVisitor);
        }
        $schema = new Schema([
            'types' => array_values($types),
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => $queries,
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => $mutations,
            ]),
        ]);

        return $schema;
    }
}
