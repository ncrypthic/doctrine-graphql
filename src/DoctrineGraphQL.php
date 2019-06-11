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
use LLA\DoctrineGraphQL\Util\QueryUtil;
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
    /**
     * @var EntityTypeNameGenerator
     */
    private $nameGenerator;

    public function __construct(EntityTypeNameGenerator $nameGenerator)
    {
        $this->nameGenerator = $nameGenerator;
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
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addOutputType(string $name, array $config): \LLA\DoctrineGraphQL\DoctrineGraphQL
    {
        $config['name'] = $name;
        $this->outputTypes[$name] = new ObjectType($config);

        return $this;
    }
    /**
     * Get output type named $name
     *
     * @param string $name
     * @return Maybe
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
     * @return \LLA\DoctrineGraphQL\Util\Maybe
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
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addInputType(string $name, array $config): \LLA\DoctrineGraphQL\DoctrineGraphQL
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
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addQuery(string $name, Type $type, array $args, callable $resolver, $desc = null): \LLA\DoctrineGraphQL\DoctrineGraphQL
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
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addQueryWithFieldResolver(string $name, Type $type, array $args, callable $fieldResolver, $desc = null): \LLA\DoctrineGraphQL\DoctrineGraphQL
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
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function addMutation(string $name, Type $type, array $args, callable $resolver, $desc = null): \LLA\DoctrineGraphQL\DoctrineGraphQL
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
     * @param \Doctrine\ORM\EntityManager $em Doctrine ORM entity manager
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    private function registerObjects(EntityManager $em): \LLA\DoctrineGraphQL\DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $name = $this->nameGenerator->generate($cm->name);
            $type = ['name' => $name, 'fields' => []];
            $inputType = ['name' => $name."Input" ,'fields' => []];
            $searchType = ['name' => $name."Search", 'fields' => []];
            $searchInputType = ['name' => $name."SearchInput", 'fields' => []];
            $sortType = ['name' => $name."Sort", 'fields' => []];
            $sortInputType = ['name' => $name."SortInput", 'fields' => []];
            foreach($cm->getFieldNames() as $fieldName) {
                $fieldDef = $cm->getFieldMapping($fieldName);
                $maybeType = SchemaUtil::mapTypeToGraphqlType($fieldDef['type'], $fieldDef['nullable'], false);
                if (!$maybeType->isEmpty()) {
                    $fieldType = $maybeType->value();
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
                        'sort'   => $this->outputTypes[$name."Sort"],
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
     * @param \Doctrine\ORM\EntityManager $em
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    private function registerRelationships(EntityManager $em): \LLA\DoctrineGraphQL\DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        $modifiedTypes = [];
        $modifiedSearchTypes = [];
        foreach($cmf->getAllMetadata() as $cm) {
            $name = $this->nameGenerator->generate($cm->name);
            $maybeType = $this->getOutputType($name);
            if($maybeType->isEmpty()) {
                continue;
            }
            $type = $maybeType->value()->config;
            $fields = $type['fields'];
            $searchInputType = $this->getInputType($name."SearchInput")->value()->config;
            $searchFields = $searchInputType['fields'];
            foreach($cm->getAssociationMappings() as $fieldDef) {
                $fieldName  = $fieldDef['fieldName'];
                $typeName   = $this->nameGenerator->generate($fieldDef['targetEntity']);
                $isNullable = true;
                if(!$fieldDef['isOwningSide']) {
                    /* @var Doctrine\Orm\Mapping\ClassMetadata $owningSide */
                    $owningSide = $cmf->getMetadataFor($fieldDef['targetEntity']);

                    $joinFieldMetadata = $owningSide->getAssociationMapping($fieldDef['mappedBy']);
                } else {
                    $joinFieldMetadata = $fieldDef;
                }

                if (!array_key_exists('joinColumns', $joinFieldMetadata)) {
                    $joinColumns = $joinFieldMetadata['joinTable']['joinColumns'];
                } else {
                    $joinColumns = $joinFieldMetadata['joinColumns'];
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
                $fieldSearchType = $this->getInputType($typeName."SearchInput")->value();
                $searchFields[$fieldName] = [
                    'type' => $fieldSearchType,
                    'description' => '',
                    'resolve' => function($rootValue, $ctx, $args, ResolveInfo $resolveInfo) {
                        if($ctx['queryBuilder']) {
                            $qb = $ctx['queryBuilder'];
                        }
                    }
                ];
            }
            $this->outputTypes[$name] = $modifiedTypes[$name] = new ObjectType(['name' => $name, 'fields' => $fields]);
            $this->inputTypes[$name."SearchInput"] = $modifiedSearchTypes[$name."SearchInput"] = new InputObjectType(['name' => $name."SearchInput", 'fields' => $searchFields]);
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
        foreach($modifiedSearchTypes as $typeName => $type) {
            foreach($this->inputTypes as $existingName=>&$existingType) {
                foreach($existingType->config['fields'] as &$existingTypeFieldDef) {
                    if(!is_array($existingTypeFieldDef) ) {
                        continue;
                    }
                    if($existingTypeFieldDef['type']->name === $type->name){
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
     * @param \Doctrine\ORM\EntityManager $em
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function buildTypes(EntityManager $em): \LLA\DoctrineGraphQL\DoctrineGraphQL
    {
        $this->registerObjects($em)->registerRelationships($em);
        $this->types = $this->outputTypes + $this->inputTypes;

        return $this;
    }
    /**
     * Create built-in mutations
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function buildMutations(EntityManager $em): \LLA\DoctrineGraphQL\DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $name = $this->nameGenerator->generate($cm->name);
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
                    $targetName = $this->nameGenerator->generate($cm->getAssociationTargetClass($idField));
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
     * @param \Doctrine\ORM\EntityManager $em
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    public function buildQueries(EntityManager $em): \LLA\DoctrineGraphQL\DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $name = $this->nameGenerator->generate($cm->name);
            $type = $this->getType($name);
            if($type->isEmpty()) {
                continue;
            }
            $idArgs = [];
            foreach($cm->getIdentifierFieldNames() as $idField) {
                if($cm->hasAssociation($idField)) {
                    $targetName = $this->nameGenerator->generate($cm->getAssociationTargetClass($idField));
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
                function($rootValue, $args, $ctx, ResolveInfo $resolveInfo) use($em, $cm, $pageArgs){
                    $selectedFields = $resolveInfo->getFieldSelection();
                    $total  = 0;
                    $filter = [];
                    $match  = [];
                    $sort   = [];
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
                        QueryUtil::walkFilters($qb, $pageArgs['filter'], $args['filter'], 'e', function($exprs, $params) use($qb) {
                            if(count($exprs) > 0) {
                                $qb->where($qb->expr()->andX(...$exprs));
                            }
                            if(count($params) > 0) {
                                $qb->setParameters($params);
                            }
                        });
                        QueryUtil::walkFilters($qbTotal, $pageArgs['filter'], $args['filter'], 'e', function($exprs, $params) use($qbTotal) {
                            if(count($exprs) > 0) {
                                $qbTotal->where($qbTotal->expr()->andX(...$exprs));
                            }
                            if(count($params) > 0) {
                                $qbTotal->setParameters($params);
                            }
                        });
                    }
                    if(isset($args['match'])) {
                        QueryUtil::walkFilters($qb, $pageArgs['filter'], $args['filter'], 'e', function($exprs, $params) use($qb) {
                            if(count($exprs) > 0) {
                                $qb->andWhere($qb->expr()->orX(...$exprs));
                            }
                            if(count($params) > 0) {
                                $qb->setParameters($params);
                            }
                        });
                        QueryUtil::walkFilters($qbTotal, $pageArgs['filter'], $args['filter'], 'e', function($exprs, $params) use($qbTotal) {
                            if(count($exprs) > 0) {
                                $qbTotal->andWhere($qbTotal->expr()->orX(...$exprs));
                            }
                            if(count($params) > 0) {
                                $qbTotal->setParameters($params);
                            }
                        });
                    }
                    if(isset($args['sort'])) {
                        foreach($args['sort'] as $fieldName=>$direction) {
                            $qb->orderBy("e.".$fieldName, $direction);
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
                        'sort'   => $sort,
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
