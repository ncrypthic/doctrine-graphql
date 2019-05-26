<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use LLA\DoctrineGraphQL\Type\BuiltInTypes;
use LLA\DoctrineGraphQL\Util\Maybe;
use LLA\DoctrineGraphQL\Util\SchemaUtil;

class DoctrineGraphQL
{
    /**
     * @var array
     */
    private $outputTypes;
    /**
     * @var array
     */
    private $inputTypes;
    /**
     * @var array
     */
    private $types;
    /**
     * @var array
     */
    private $queries;
    /**
     * @var array
     */
    private $mutations;

    public function __construct()
    {
        $this->types = [];
        $this->inputTypes = [];
        $this->outputTypes = [];
        $this->queries = [];
        $this->mutations = [];
    }
    /**
     * Add output object type
     *
     * @param string $name Output object type name
     * @param array $config Ouput object type configuration
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addOutputType(string $name, array $config): DoctrineGraphQL
    {
        $config['name'] = $name;
        $this->outputTypes[$name] = new ObjectType($config);

        return $this;
    }
    /**
     * Get output type named $name
     *
     * @param string $name
     * @return LLA\DoctrineGraphQL\Util\Maybe
     */
    public function getOutputType(string $name): Maybe
    {
        if(isset($this->outputTypes[$name])) {
            $res = $this->outputTypes[$name];
            return Maybe::Some($res);
        }

        return Maybe::None();
    }
    /**
     * Get type named $name
     *
     * @param string $name
     * @return LLA\DoctrineGraphQL\Util\Maybe
     */
    public function getType(string $name): Maybe
    {
        if(isset($this->types[$name])) {
            $res = $this->types[$name];
            return Maybe::Some($res);
        }

        return Maybe::None();
    }
    /**
     * Get all input & output types
     *
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }
    /**
     * Add input object type
     *
     * @param string $name Input object type name
     * @param array $config Input object type configuration
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addInputType(string $name, array $config): DoctrineGraphQL
    {
        $config['name'] = $name;
        $this->inputTypes[$name] = new InputObjectType($config);

        return $this;
    }
    /**
     * Get a maybe input type
     *
     * @param string $name
     * @return Maybe
     */
    public function getInputType(string $name): Maybe
    {
        if(isset($this->inputTypes[$name])) {
            $res = $this->inputTypes[$name];
            return Maybe::Some($res);
        }

        return Maybe::None();
    }
    /**
     * Add a GraphQL query
     *
     * @param string $name Query name
     * @param GraphQL\Type\Definition\Type $type Return type ;
     * @param array $args Query arguments
     * @param callable $nesolver Resolver function
     * @param string|null $desc Query description
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addQuery(string $name, Type $type, array $args, callable $resolver, $desc = null): DoctrineGraphQL
    {
        $this->queries[$name] = [
            'type' => $type,
            'args' => $args,
            'description' => $desc,
            'resolve' => $resolver
        ];

        return $this;
    }
    /**
     * @param string $name Query name
     * @param GraphQL\Type\Definition\Type $type Return type ;
     * @param array $args Query arguments
     * @param callable $fieldResolver Resolver function
     * @param string|null $desc Query description
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addQueryWithFieldResolver(string $name, Type $type, array $args, callable $fieldResolver, $desc = null): DoctrineGraphQL
    {
        $this->queries[$name] = [
            'type' => $type,
            'args' => $args,
            'description' => $desc,
            'resolveField' => $fieldResolver
        ];

        return $this;
    }
    /**
     * @param string $name Mutation name
     * @param GraphQL\Type\Definition\Type $type Return tyoe
     * @param array $args Arguments
     * @param callable $resolver Resolver function
     * @param string|null $desc Mutation description
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addMutation(string $name, Type $type, array $args, callable $resolver, $desc = null): DoctrineGraphQL
    {
        $this->mutations[$name] = [
            'type' => $type,
            'args' => $args,
            'description' => $desc,
            'resolve' => $resolver,
        ];

        return $this;
    }
    /**
     * Register doctrine entities as graphql types
     *
     * @param Doctrine\ORM\EntityManager $em Doctrine ORM entity manager
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    private function registerObjects(EntityManager $em): DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $name = SchemaUtil::mkObjectName($cm->name);
            $type = ['name' => $name, 'fields' => []];
            $inputType = ['name' => $name."Input" ,'fields' => []];
            $searchType = ['name' => $name."Search", 'fields' => []];
            $searchInputType = ['name' => $name."SearchInput", 'fields' => []];
            $sortType = ['name' => $name."Sort", 'fields' => []];
            $sortInputType = ['name' => $name."SortInput", 'fields' => []];
            foreach($cm->getFieldNames() as $fieldName) {
                $fieldDef = $cm->getFieldMapping($fieldName);
                $fieldType = SchemaUtil::mapTypeToGraphqlType($fieldDef['type'], $fieldDef['nullable'], false)->value();
                $type['fields'][$fieldName] = ['type' => $fieldType, 'resolve' => function($data, $args, $context, ResolveInfo $resolveInfo) use($cm, $fieldName, $fieldDef, $name) {
                    if($data instanceof Collection) {
                        return array_map(function($elmt) use($resolveInfo){
                            return call_user_func([$elmt, 'get'.ucfirst($resolveInfo->fieldName)]);
                        }, $data->toArray());
                    } else {
                        return call_user_func([$data, 'get'.ucfirst($resolveInfo->fieldName)]);
                    }
                }];
                $searchType['fields'][$fieldName] = ['type' => Type::listOf(BuiltInTypes::searchFilter())];
                $sortType['fields'][$fieldName] = ['type' => BuiltInTypes::sortingOrientation()];
                $inputType['fields'][$fieldName] = ['type' => $fieldType];
                $searchInputType['fields'][$fieldName] = ['type' => Type::listOf(BuiltInTypes::searchFilterInput())];
                $sortInputType['fields'][$fieldName] = ['type' => BuiltInTypes::sortingOrientation()];
            }
            if(count($type['fields']) > 0) {
                $this->addOutputType($name, $type);
                $this->addOutputType($name."Search", $searchType);
                $this->addOutputType($name."Sort", $sortType);
                $pageType = [
                    'name' => $name."Page",
                    'fields' => [
                        'total'  => Type::int(),
                        'page'   => Type::int(),
                        'limit'  => Type::int(),
                        'filter' => $this->outputTypes[$name."Search"],
                        'match'  => $this->outputTypes[$name."Search"],
                        'sort'  => $this->outputTypes[$name."Sort"],
                        'items'  => ['type' => Type::listOf($this->outputTypes[$name])],
                    ]
                ];
                $this->addOutputType($name."Page", $pageType);
            }
            if(count($inputType['fields']) > 0) {
                $this->addInputType($name."Input", $inputType);
                $this->addInputType($name."SearchInput", $searchInputType);
                $this->addInputType($name."SortInput", $sortInputType);
            }
        }

        return $this;
    }
    /**
     * Register doctrine entities relationships fields as graphql
     * resolvable fields
     *
     * @param Doctrine\ORM\EntityManager $em
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    private function registerRelationships(EntityManager $em): DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        $modifiedTypes = [];
        foreach($cmf->getAllMetadata() as $cm) {
            $name = SchemaUtil::mkObjectName($cm->name);
            $maybeType = $this->getOutputType($name);
            if($maybeType->isEmpty()) {
                continue;
            }
            $type = $maybeType->value()->config;
            $fields = $type['fields'];
            foreach($cm->getAssociationMappings() as $fieldDef) {
                $fieldName  = $fieldDef['fieldName'];
                $typeName   = SchemaUtil::mkObjectName($fieldDef['targetEntity']);
                $isNullable = true;
                if(!$fieldDef['isOwningSide']) {
                    /* @var Doctrine\Orm\Mapping\ClassMetadata $owningSide */
                    $owningSide = $cmf->getMetadataFor($fieldDef['targetEntity']);
                    $joinColumns = $owningSide->getAssociationMapping($fieldDef['mappedBy'])['joinColumns'];
                } else {
                    $joinColumns = $fieldDef['joinColumns'];
                }
                foreach($joinColumns as $joinColumn) {
                    if(!$joinColumn['nullable']) {
                        $isNullable = false;
                        break;
                    }
                }
                $fieldType = $this->getOutputType($typeName)->value();
                $fields[$fieldName] = [
                    'type' => $fieldDef['type'] === ClassMetadataInfo::ONE_TO_MANY ? Type::listOf($fieldType) : $fieldType,
                    'description' => '',
                    'resolve' => function($data, $args, $context, ResolveInfo $resolveInfo) use($cm, $fieldName){
                        if($data instanceof Collection) {
                            return array_map(function($elmt) use($resolveInfo){
                                return call_user_func([$elmt, 'get'.ucfirst($resolveInfo->fieldName)]);
                            }, $data->toArray());
                        } else {
                            return call_user_func([$data, 'get'.ucfirst($resolveInfo->fieldName)]);
                        }
                    }
                ];
            }
            $this->outputTypes[$name] = $modifiedTypes[$name] = new ObjectType(['name' => $name, 'fields' => $fields]);
        }
        foreach($modifiedTypes as $typeName => $type) {
            foreach($this->outputTypes as $existingName=>&$existingType) {
                foreach($existingType->config['fields'] as &$existingTypeFieldDef) {
                    if(!is_array($existingTypeFieldDef) ) {
                        continue;
                    }
                    if($existingTypeFieldDef['type'] instanceof ListOfType && $existingTypeFieldDef['type']->getWrappedType()->name === $type->name) {
                        $existingTypeFieldDef['type'] = Type::listOf($type);
                    } else if($existingTypeFieldDef['type']->name === $type->name){
                        $existingTypeFieldDef['type'] = $type;
                    }
                }
            }
            $this->outputTypes[$typeName] = $type;
        }

        return $this;
    }
    /**
     * Compile types
     *
     * @param Doctrine\ORM\EntityManager $em
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function buildTypes(EntityManager $em): DoctrineGraphQL
    {
        $this->registerObjects($em)->registerRelationships($em);
        $this->types = $this->outputTypes + $this->inputTypes;

        return $this;
    }
    /**
     * Create built-in mutations
     *
     * @param Doctrine\ORM\EntityManager $em
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function buildMutations(EntityManager $em): DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $name = SchemaUtil::mkObjectName($cm->name);
            $type = $this->getType($name);
            if($type->isEmpty()) {
                continue;
            }
            $inputType = $this->getInputType($name."Input");
            if($inputType->isEmpty()) {
                continue;
            }
            $this->addMutation(
                "create".$name,
                $type->value(),
                ['input' => $inputType->value()],
                function($val, $args) use($cm, $em){
                    $reflect = new \ReflectionClass($cm->name);
                    $entity = $reflect->newInstance();
                    foreach($args['input'] as $field=>$value) {
                        call_user_func([$entity, 'set'.ucfirst($field)], $value);
                    }
                    $em->persist($entity);
                    $em->flush();
                    return $entity;
                },
                "Creates new $name"
            );
            $this->addMutation(
                "update".$name,
                $type->value(),
                ['input' => $inputType->value()],
                function($val, $args) use($cm, $em){
                    $input = $args['input'];
                    $identifiers = $cm->getIdentifierFieldNames();
                    $idFields = [];
                    $values = [];
                    foreach($input as $field=>$value) {
                        if(in_array($field, $identifiers)) {
                            $idFields[$field] = $value;
                        } else {
                            $values[$field] = $value;
                        }
                    }
                    $repository = $em->getRepository($cm->name);
                    $entity = $repository->findOneBy($idFields);
                    if(empty($entity)) {
                        throw new \Error('Cannot find data with '.json_encode($idFields, false));
                    }
                    foreach($values as $field=>$value) {
                        call_user_func([$entity, 'set'.ucfirst($field)], $value);
                    }
                    $em->persist($entity);
                    $em->flush();
                    return $entity;
                },
                "Updates $name"
            );
            $idArgs = [];
            foreach($cm->getIdentifierFieldNames() as $idField) {
                if($cm->hasAssociation($idField)) {
                    $targetName = SchemaUtil::mkObjectName($cm->getAssociationTargetClass($idField));
                    $maybeInputType = $this->getInputType($targetName);
                    if($maybeInputType->isEmpty()) {
                        continue;
                    }
                    $idArgs[$idField] = $maybeInputType->value();
                } else {
                    $idArgs[$idField] = SchemaUtil::mapTypeToGraphqlType($cm->getTypeOfField($idField), false, false)->value();
                }
            }
            $this->addMutation(
                "delete".$name,
                $type->value(),
                $idArgs,
                function($val, $args) use($em, $cm) {
                    $reflect = new \ReflectionClass($cm->name);
                    $entity = $repository->findOneBy($val);
                    if(!empty($entity)) {
                        $em->remove($entity);
                        $em->flush();
                    }
                },
                "Delete a $name"
            );
        }
        return $this;
    }
    /**
     * Create built-in query
     *
     * @param Doctrine\ORM\EntityManager $em
     * @return LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function buildQueries(EntityManager $em): DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $name = SchemaUtil::mkObjectName($cm->name);
            $type = $this->getType($name);
            if($type->isEmpty()) {
                continue;
            }
            $idArgs = [];
            foreach($cm->getIdentifierFieldNames() as $idField) {
                if($cm->hasAssociation($idField)) {
                    $targetName = SchemaUtil::mkObjectName($cm->getAssociationTargetClass($idField));
                    $idArgs[$idField] = $this->getInputType($targetName."Input")->value();
                } else {
                    $idArgs[$idField] = SchemaUtil::mapTypeToGraphqlType($cm->getTypeOfField($idField), false, false)->value();
                }
            }
            $this->addQuery(
                "get".$name,
                $this->getType($name)->value(),
                $idArgs,
                function($rootValue, $args) use($em, $cm){
                    /* @var \Doctrine\ORM\EntityRepository $repository */
                    $repository = $em->getRepository($cm->name);
                    return $repository->findOneBy($args);
                },
                "Get single $name"
            );
            $pageArgs = [
                'page'   => Type::int(),
                'limit'  => Type::int(),
                'match'  => $this->getInputType($name."SearchInput")->value(),
                'filter' => $this->getInputType($name."SearchInput")->value(),
                'sort'   => $this->getInputType($name."SortInput")->value(),
            ];
            $this->addQuery(
                "get".$name."Page",
                $this->getType($name."Page")->value(),
                $pageArgs,
                function($rootValue, $args, $ctx, ResolveInfo $resolveInfo) use($em, $cm){
                    $selectedFields = $resolveInfo->getFieldSelection();
                    $total  = 0;
                    $filter = [];
                    $match  = [];
                    /* @var \Doctrine\ORM\EntityRepository $repo */
                    /* @var \Doctrine\ORM\QueryBuilder $qb */
                    $repo    = $em->getRepository($cm->name);
                    $qb      = $em->createQueryBuilder()->select('e')->from($cm->name, 'e');
                    $qbTotal = $em->createQueryBuilder()->select('count(e) total')->from($cm->name, 'e');
                    $criteria = array_filter($args, function($key) {
                        return !in_array($key, ['page', 'limit', 'match', 'filter']);
                    }, ARRAY_FILTER_USE_KEY);
                    $parameters = ['filter' => [], 'match' => []];
                    if(isset($args['filter'])) {
                        $filter = $args['filter'];
                        $filterExprs = [];
                        foreach($args['filter'] as $fieldName=>$predicates) {
                            foreach($predicates as $predicate) {
                                $expr = $qb->expr();
                                $paramName = ":$fieldName";
                                $parameters['filter'][$fieldName] = $predicate['value'];
                                switch($predicate['operator']) {
                                case BuiltInTypes::FILTER_OP_LESS_THAN:
                                    $filterExprs[] = $expr->lt("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_LESS_THAN_EQUAL:
                                    $filterExprs[] = $expr->lte("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_EQUAL:
                                    $filterExprs[] = $expr->eq("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_GREATER_THAN:
                                    $filterExprs[] = $expr->gt("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_GREATER_THAN_EQUAL:
                                    $filterExprs[] = $expr->gte("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_NOT_EQUAL:
                                    $filterExprs[] = $expr->neq("e.".$fieldName, $paramName);
                                    break;
                                }
                            }
                        }
                        $qb->where($qb->expr()->andX(...$filterExprs))->setParameters($parameters['filter']);
                        $qbTotal->where($qb->expr()->andX(...$filterExprs))->setParameters($parameters['filter']);
                    }
                    if(isset($args['match'])) {
                        $match = $args['match'];
                        $matchExprs = [];
                        foreach($args['match'] as $fieldName=>$predicates) {
                            foreach($predicates as $predicate) {
                                $expr = $qb->expr();
                                $paramName = ":$fieldName";
                                $parameters['match'][$fieldName] = $predicate['value'];
                                switch($predicate['operator']) {
                                case BuiltInTypes::FILTER_OP_LESS_THAN:
                                    $matchExprs[] = $expr->lt("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_LESS_THAN_EQUAL:
                                    $matchExprs[] = $expr->lte("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_EQUAL:
                                    $matchExprs[] = $expr->eq("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_GREATER_THAN:
                                    $matchExprs[] = $expr->gt("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_GREATER_THAN_EQUAL:
                                    $matchExprs[] = $expr->gte("e.".$fieldName, $paramName);
                                    break;
                                case BuiltInTypes::FILTER_OP_NOT_EQUAL:
                                    $matchExprs[] = $expr->neq("e.".$fieldName, $paramName);
                                    break;
                                }
                            }
                        }
                        $qb->andWhere($qb->expr()->orX(...$matchExprs))->setParameters($parameters['match']);
                        $qbTotal->where($qb->expr()->orX(...$matchExprs))->setParameters($parameters['match']);
                    }
                    if(isset($args['sort'])) {
                        foreach($args['sort'] as $fieldName=>$direction) {
                            $qb->orderBy($fieldName, $direction);
                        }
                    }
                    $page = $args['page'] > 0 ? $args['page'] - 1: 0;
                    if(in_array('total', $selectedFields)) {
                        foreach($parameters['filter'] as $key=>$value) {
                            $qbTotal->setParameter($key, $value);
                        }
                        foreach($parameters['match'] as $key=>$value) {
                            $qbTotal->setParameter($key, $value);
                        }
                        $res = $qbTotal->setMaxResults(1)->getQuery()->getArrayResult();
                        $total = $res[0]['total'];
                    }
                    return [
                        'total'  => $total,
                        'page'   => $args['page'],
                        'limit'  => $args['limit'],
                        'filter' => $filter,
                        'match'  => $match,
                        'sort'   => $args['sort'],
                        'items'  => $qb->setMaxResults($args['limit'])->setFirstResult($page * $args['limit'])->getQuery()->getResult()
                    ];
                },
                "Get single $name"
            );
        }

        return $this;
    }
    /**
     * Generate GraphQL schema
     * @return \GraphQL\Type\Schema
     */
    public function toGraphqlSchema(): \GraphQL\Type\Schema
    {
        /* @var GraphQL\Type\Definition\ObjectType[] $types */
        /* @var GraphQL\Type\Definition\InputObjectType[] $types */
        $query = ['name'=>'Query', 'fields' => []];
        $mutations = ['name'=>'Mutation' ,'fields' => []];
        foreach($this->queries as $name=>$config) {
            $query['fields'][$name] = $config;
        }
        foreach($this->mutations as $name=>$config) {
            $mutations['fields'][$name] = $config;
        }
        return new Schema([
            'types' => array_values($this->types),
            'query' => new ObjectType($query),
            'mutation' => new ObjectType($mutations),
        ]);
    }
}
