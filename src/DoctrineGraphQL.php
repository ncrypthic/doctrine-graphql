<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use LLA\DoctrineGraphQL\Type\BuiltInTypes;
use LLA\DoctrineGraphQL\Type\Definition\InputTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ListTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\MutationTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ObjectTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\QueryTypeDefinition;
use LLA\DoctrineGraphQL\Type\RegistryInterface;
use LLA\DoctrineGraphQL\Util\Maybe;
use LLA\DoctrineGraphQL\Util\MutationUtil;
use LLA\DoctrineGraphQL\Util\QueryUtil;
use LLA\DoctrineGraphQL\Util\ResolverUtil;

class DoctrineGraphQL
{
    /**
     * @var RegistryInterface
     */
    private $registry;
    /**
     * @var EntityTypeNameGenerator
     */
    private $nameGenerator;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(RegistryInterface $registry, EntityManagerInterface $em, EntityTypeNameGenerator $nameGenerator = null)
    {
        $this->registry = $registry;
        $this->entityManager = $em;
        $this->nameGenerator = $nameGenerator ?: new SimpleEntityTypeNameGenerator();
    }
    /**
     * @param ClassMetadata $cm
     * @return DoctrineGraphQL
     */
    private function registerEntityType(ClassMetadata $cm): DoctrineGraphQL
    {
        if($cm->getReflectionClass()->isAbstract()) {
            return $this;
        }
        $name = $this->nameGenerator->generate($cm->name);
        $type = new ObjectTypeDefinition($name, "Entity {$cm->name} type");
        $search = new ObjectTypeDefinition($name."Search", "Entity {$cm->name} pagination search type");
        $sort = new ObjectTypeDefinition($name."Sort", "Entity {$cm->name} pagination search type");
        $page = new ObjectTypeDefinition($name."Page", "Entity {$cm->name} paginated list result");
        $input = new InputTypeDefinition($name."Input", "Entity {$cm->name} input");
        $searchInput = new InputTypeDefinition($name."SearchInput", "Entity {$cm->name} search input");
        $sortInput = new InputTypeDefinition($name."SortInput", "Entity {$cm->name} sort input");
        $searchFilter = $this->registry->getType('SearchFilter')->value();
        $searchFilterInput = $this->registry->getType('SearchFilterInput')->value();
        $sortOrientation = $this->registry->getType('SortingOrientation')->value();
        $listSearchFilter = new ListTypeDefinition($searchFilter);
        $listSearchFilterInput = new ListTypeDefinition($searchFilterInput);
        $this->registry->addType($listSearchFilter);
        foreach($cm->getFieldNames() as $fieldName) {
            $fieldDef = $cm->getFieldMapping($fieldName);
            if($cm->hasAssociation($fieldName)) {
                continue;
            }
            $fieldType = $this->registry->mapDoctrineType($fieldDef['type'], $fieldDef['nullable'], false)->value();
            $type->addField($fieldName, $fieldType, ['resolve' => [ResolverUtil::class, 'fieldResolver']]);
            $input->addField($fieldName, $fieldType, []);
            $search->addField($fieldName, $listSearchFilter, []);
            $searchInput->addField($fieldName, $listSearchFilterInput, []);
            $sort->addField($fieldName, $sortOrientation, []);
            $sortInput->addField($fieldName, $sortOrientation, []);
        }
        $listItems = new ListTypeDefinition($type);
        $this->registry->addType($listItems);
        $page->addField('total', $this->registry->getType('Int')->value(), []);
        $page->addField('page', $this->registry->getType('Int!')->value(), []);
        $page->addField('limit', $this->registry->getType('Int!')->value(), []);
        $page->addField('sort', $sort, []);
        $page->addField('filter', $search, []);
        $page->addField('match',  $search, []);
        $page->addField('items', $listItems, []);
        $this->registry->addType($type);
        $this->registry->addType($input);
        $this->registry->addType($search);
        $this->registry->addType($searchInput);
        $this->registry->addType($sort);
        $this->registry->addType($sortInput);
        $this->registry->addType($page);

        return $this;
    }
    /**
     * Register doctrine entities as graphql types
     *
     * @param \Doctrine\ORM\EntityManagerInterface $em Doctrine ORM entity manager
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    private function registerEntitiesType(EntityManagerInterface $em): DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $this->registerEntityType($cm);
        }

        return $this;
    }
    /**
     * Register doctrine entities relationships fields as graphql
     * resolvable fields
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $cm
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    private function registerRelationshipsType(ClassMetadata $cm)
    {
        $name = $this->nameGenerator->generate($cm->name);
        $maybeType = $this->registry->getType($name);
        if($maybeType->isEmpty()) {
            return;
        }
        $maybeInput = $this->registry->getType($name."Input");
        $maybeSearchType = $this->registry->getType($name."Search");
        $maybeSearchInput = $this->registry->getType($name."SearchInput");
        $maybeSort = $this->registry->getType($name."Sort");
        $maybeSortInput = $this->registry->getType($name."SortInput");
        foreach($cm->getAssociationMappings() as $fieldDef) {
            $fieldName  = $fieldDef['fieldName'];
            $typeName   = $this->nameGenerator->generate($fieldDef['targetEntity']);
            $maybeFieldType = $this->registry->getType($typeName);
            if($maybeFieldType->isEmpty()) {
                continue;
            }
            $isNullable = true;
            if(!$fieldDef['isOwningSide']) {
                $owningSide = $this->entityManager->getClassMetadata($fieldDef['targetEntity']);
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
            $fieldType = $maybeFieldType->value();
            $fieldConfig = ['description' => '', 'resolve' => [ResolverUtil::class, 'fieldResolver']];
            if($fieldDef['type'] === ClassMetadata::ONE_TO_MANY) {
                $listField = new ListTypeDefinition($fieldType);
                $this->registry->addType($listField);
                $maybeType->value()->addField($fieldName, $listField, $fieldConfig);
            } else {
                $maybeType->value()->addField($fieldName, $fieldType, $fieldConfig);
            }
            $maybeFieldSearchInput = $this->registry->getType("{$typeName}SearchInput");
            if(!$maybeFieldSearchInput->isEmpty() && !$maybeSearchInput->isEmpty()) {
                $maybeSearchInput->value()->addField($fieldName, $maybeFieldSearchInput->value(), ['description' => '']);
            }
            $maybeFieldInput = $this->registry->getType("{$typeName}Input");
            if(!$maybeFieldInput->isEmpty() && $fieldDef['isOwningSide']) {
                if($cm->isCollectionValuedAssociation($fieldName)) {
                    $listField = new ListTypeDefinition($maybeFieldInput->value());
                    $this->registry->addType($listField);
                    $maybeInput->value()->addField($fieldName, $listField, []);
                } else {
                    $maybeInput->value()->addField($fieldName, $maybeFieldInput->value(), []);
                }
            }
        }
    }
    /**
     * Register doctrine entities relationships fields as graphql
     * resolvable fields
     *
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @return \LLA\DoctrineGraphQL\DoctrineGraphQL
     */
    private function registerRelationships(EntityManagerInterface $em): \LLA\DoctrineGraphQL\DoctrineGraphQL
    {
        $cmf = $em->getMetadataFactory();
        foreach($cmf->getAllMetadata() as $cm) {
            $this->registerRelationshipsType($cm);
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
        $this->registerEntitiesType($em)->registerRelationships($em);

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
            $type = $this->registry->getType($name);
            if($type->isEmpty()) {
                continue;
            }
            $inputType = $this->registry->getType($name."Input");
            if($inputType->isEmpty()) {
                continue;
            }
            $this->registry->addMutation(new MutationTypeDefinition(
                "create".$name,
                $type->value(),
                ['input' => ['type' => $inputType->value()]],
                MutationUtil::createMutation($cm, $em),
                "Creates new $name"
            ));
            $this->registry->addMutation(new MutationTypeDefinition(
                "update".$name,
                $type->value(),
                ['input' => ['type' => $inputType->value()]],
                MutationUtil::updateMutation($cm, $em),
                "Updates $name"
            ));
            $idArgs = [];
            foreach($cm->getIdentifierFieldNames() as $idField) {
                if($cm->hasAssociation($idField)) {
                    $targetName = $this->nameGenerator->generate($cm->getAssociationTargetClass($idField));
                    $maybeInputType = $this->registry->getType($targetName."Input");
                    if($maybeInputType->isEmpty()) {
                        continue;
                    }
                    $idArgs[$idField] = ['type' => $maybeInputType->value()];
                } else {
                    $idArgs[$idField] = ['type' => $this->registry->mapDoctrineType($cm->getTypeOfField($idField), false, false)->value()];
                }
            }
            $this->registry->addMutation(new MutationTypeDefinition(
                "delete".$name,
                $type->value(),
                $idArgs,
                MutationUtil::deleteMutation($cm, $em),
                "Delete a $name"
            ));
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
            $type = $this->registry->getType($name);
            if($type->isEmpty()) {
                continue;
            }
            $idArgs = [];
            foreach($cm->getIdentifierFieldNames() as $idField) {
                if($cm->hasAssociation($idField)) {
                    $targetName = $this->nameGenerator->generate($cm->getAssociationTargetClass($idField));
                    $inputType = $this->registry->getType($targetName."Input");
                    if(!$inputType->isEmpty()) {
                        $idArgs[$idField] = ['type' => $inputType->value()];
                    }
                } else {
                    $idArgs[$idField] = ['type' => $this->registry->mapDoctrineType($cm->getTypeOfField($idField), false, false)->value()];
                }
            }
            $this->registry->addQuery(new QueryTypeDefinition(
                "get".$name,
                $this->registry->getType($name)->value(),
                $idArgs,
                function($rootValue, $args) use($em, $cm){
                    /* @var \Doctrine\ORM\EntityRepository $repository */
                    $repository = $em->getRepository($cm->name);
                    return $repository->findOneBy($args);
                },
                "Get single $name"
            ));
            $maybeSearchInput = $this->registry->getType($name."SearchInput");
            $pageArgs = [
                'page'   => ['type' => $this->registry->getType('Int!')->value()],
                'limit'  => ['type' => $this->registry->getType('Int!')->value()],
                'sort'   => ['type' => $this->registry->getType($name."SortInput")->value()],
            ];
            if(!$maybeSearchInput->isEmpty()) {
                $pageArgs['match'] = ['type' => $maybeSearchInput->value()];
                $pageArgs['filter'] = ['type' => $maybeSearchInput->value()];
            }
            $this->registry->addQuery(new QueryTypeDefinition(
                "get".$name."Page",
                $this->registry->getType($name."Page")->value(),
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
                        QueryUtil::walkFilters($qb, $pageArgs['filter']['type'], $args['filter'], 'e', function($exprs, $params) use($qb) {
                            if(count($exprs) > 0) {
                                $qb->andWhere($qb->expr()->andX(...$exprs));
                            }
                            if(count($params) > 0) {
                                foreach($params as $field=>$value) {
                                    $qb->setParameter($field, $value);
                                }
                            }
                        });
                        QueryUtil::walkFilters($qbTotal, $pageArgs['filter']['type'], $args['filter'], 'e', function($exprs, $params) use($qbTotal) {
                            if(count($exprs) > 0) {
                                $qbTotal->andWhere($qbTotal->expr()->andX(...$exprs));
                            }
                            if(count($params) > 0) {
                                foreach($params as $field=>$value) {
                                    $qbTotal->setParameter($field, $value);
                                }
                            }
                        });
                    }
                    if(isset($args['match'])) {
                        QueryUtil::walkFilters($qb, $pageArgs['match']['type'], $args['match'], 'e', function($exprs, $params) use($qb) {
                            if(count($exprs) > 0) {
                                $qb->orWhere($qb->expr()->orX(...$exprs));
                            }
                            if(count($params) > 0) {
                                foreach($params as $field=>$value) {
                                    $qb->setParameter($field, $value);
                                }
                            }
                        });
                        QueryUtil::walkFilters($qbTotal, $pageArgs['match']['type'], $args['match'], 'e', function($exprs, $params) use($qbTotal) {
                            if(count($exprs) > 0) {
                                $qbTotal->orWhere($qbTotal->expr()->orX(...$exprs));
                            }
                            if(count($params) > 0) {
                                foreach($params as $field=>$value) {
                                    $qbTotal->setParameter($field, $value);
                                }
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
            ));
        }

        return $this;
    }
    /**
     * Generate GraphQL schema
     * @return \GraphQL\Type\Schema
     */
    public function toGraphqlSchema(): \GraphQL\Type\Schema
    {
        return $this->registry->buildSchema();
    }
}
