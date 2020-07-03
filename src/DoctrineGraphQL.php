<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use LLA\DoctrineGraphQL\Mutation\MutationListenerInterface;
use LLA\DoctrineGraphQL\Mutation\MutationManager;
use LLA\DoctrineGraphQL\Naming\GeneratorInterface;
use LLA\DoctrineGraphQL\Naming\SimpleNameGenerator;
use LLA\DoctrineGraphQL\Query\QueryListenerInterface;
use LLA\DoctrineGraphQL\Query\QueryManager;
use LLA\DoctrineGraphQL\Resolver\FieldResolver;
use LLA\DoctrineGraphQL\Type\Definition\InputTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ListTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\MutationTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ObjectTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\QueryTypeDefinition;
use LLA\DoctrineGraphQL\Type\RegistryInterface;

class DoctrineGraphQL
{
    /**
     * @var RegistryInterface
     */
    private $registry;
    /**
     * @var GeneratorInterface
     */
    private $nameGenerator;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var MutationListenerInterface
     */
    private $mutationListener;
    /**
     * @var QueryListenerInterface
     */
    private $queryListener;

    public function __construct(
        RegistryInterface $registry,
        EntityManagerInterface $em,
        GeneratorInterface $nameGenerator = null,
        MutationListenerInterface $mutationListener = null ,
        QueryListenerInterface $queryListener = null)
    {
        $this->registry = $registry;
        $this->entityManager = $em;
        $this->mutationListener = $mutationListener;
        $this->queryListener = $queryListener;
        $this->nameGenerator = $nameGenerator ?: new SimpleNameGenerator();
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
        $listType = new ListTypeDefinition($type);
        $search = new ObjectTypeDefinition($name."Search", "Entity {$cm->name} pagination search type");
        $sort = new ObjectTypeDefinition($name."Sort", "Entity {$cm->name} pagination search type");
        $page = new ObjectTypeDefinition($name."Page", "Entity {$cm->name} paginated list result");
        $input = new InputTypeDefinition($name."Input", "Entity {$cm->name} input");
        $listInput = new ListTypeDefinition($input);
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
            $type->addField($fieldName, $fieldType, ['resolve' => [FieldResolver::class, 'resolve']]);
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
        $this->registry->addType($listType);
        $this->registry->addType($input);
        $this->registry->addType($listInput);
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
                if(isset($joinColumn['nullable']) && !$joinColumn['nullable']) {
                    $isNullable = false;
                    break;
                }
            }
            $fieldType = $maybeFieldType->value();
            $fieldConfig = ['description' => '', 'resolve' => [FieldResolver::class, 'resolve']];
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
                $fieldInput = $maybeFieldInput->value();
                $fields = [];
                $targetCm = $this->entityManager->getClassMetadata($fieldDef['targetEntity']);
                $joinTypeFields = $fieldInput->getFields();
                foreach($fieldDef['targetToSourceKeyColumns'] as $colName => $_) {
                    $joinFieldName = $targetCm->getFieldName($colName);
                    $joinTypeField = $joinTypeFields[$targetCm->getFieldName($colName)]['type'];
                    $fields[$joinFieldName] = ['type' => $joinTypeField];
                }
                $fieldInput = new InputTypeDefinition("{$name}_{$fieldName}Input", "{$name} {$fieldName} specific input type", $fields);
                $this->registry->addType($fieldInput);

                if($cm->isCollectionValuedAssociation($fieldName)) {
                    $listField = new ListTypeDefinition($fieldInput);
                    $this->registry->addType($listField);
                    $maybeInput->value()->addField($fieldName, $listField, []);
                } else {
                    $maybeInput->value()->addField($fieldName, $fieldInput, []);
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
            $mutationManager = new MutationManager($cm, $em, $this->mutationListener);
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
                [$mutationManager, 'createMutation'],
                "Creates new $name"
            ));
            $this->registry->addMutation(new MutationTypeDefinition(
                "update".$name,
                $type->value(),
                ['input' => ['type' => $inputType->value()]],
                [$mutationManager, 'updateMutation'],
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
                [$mutationManager, 'deleteMutation'],
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
            $idsArgs = [];
            foreach($cm->getIdentifierFieldNames() as $idField) {
                if($cm->hasAssociation($idField)) {
                    $targetName = $this->nameGenerator->generate($cm->getAssociationTargetClass($idField));
                    $inputType = $this->registry->getType($targetName."Input");
                    if(!$inputType->isEmpty()) {
                        $idArgs[$idField] = ['type' => $inputType->value()];
                        $idsArgs[$idField] = ['type' => new ListTypeDefinition($inputType->value())];
                    }
                } else {
                    $idArgs[$idField] = ['type' => $this->registry->mapDoctrineType($cm->getTypeOfField($idField), false, false)->value()];
                    $idsArgs[$idField] = ['type' => $this->registry->mapDoctrineType($cm->getTypeOfField($idField), true, true)->value()];
                }
            }
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
            $queryManager = new QueryManager($cm, $em, $pageArgs, $this->queryListener);
            $this->registry->addQuery(new QueryTypeDefinition(
                "get".$name,
                $type->value(),
                $idArgs,
                [$queryManager, 'get'],
                "Get single $name"
            ));
            $this->registry->addQuery(new QueryTypeDefinition(
                "getMany".$name,
                new ListTypeDefinition($type->value()),
                $idsArgs,
                [$queryManager, 'getMany'],
                "Get list of $name by ids"
            ));
            $this->registry->addQuery(new QueryTypeDefinition(
                "get".$name."Page",
                $this->registry->getType($name."Page")->value(),
                $pageArgs,
                [$queryManager, 'getPage'],
                "Get paginate list of $name matching arguments"
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
