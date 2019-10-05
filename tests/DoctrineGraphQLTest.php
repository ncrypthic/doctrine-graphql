<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQLTest;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use LLA\DoctrineGraphQL\DoctrineGraphQL;
use LLA\DoctrineGraphQLTest\Entity\User;
use LLA\DoctrineGraphQL\SimpleEntityTypeNameGenerator;
use LLA\DoctrineGraphQL\Type\DateTimeType;
use LLA\DoctrineGraphQL\Type\Definition\MutationTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\ObjectTypeDefinition;
use LLA\DoctrineGraphQL\Type\Definition\QueryDefinition;
use LLA\DoctrineGraphQL\Type\Definition\QueryTypeDefinition;
use LLA\DoctrineGraphQL\Type\Registry;
use PHPUnit\Framework\TestCase;

final class DoctrineGraphQLTests extends TestCase
{
    /**
     * @var \GraphQL\Type\Schema
     */
    protected $graphqlSchema;
    /**
     * @var \LLA\DoctrineGraphQL\Type\Registry
     */
    protected $registry;
    /**
     * @var \GraphQL\Type\Schema
     */
    protected $graphqlSchemaWithCustomNameStrategy;
    /**
     * @var \GraphQL\Type\Schema
     */
    protected $graphqlSchemaWithCustomType;

    public function setUp(): void
    {
        $registry = new Registry();
        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/Entity"), true, null, null, false);
        $em = EntityManager::create(['driver'=>'pdo_mysql'], $config);
        $doctrineGraphql = new DoctrineGraphQL($registry, $em);
        $this->graphqlSchema = $doctrineGraphql
            ->buildTypes($em)
            ->buildQueries($em)
            ->buildMutations($em)
            ->toGraphqlSchema();
        $doctrineGraphqlWithNamingStrategy = new DoctrineGraphQL($registry, $em, new CustomEntityTypeNameGenerator());
        $this->graphqlSchemaWithCustomNameStrategy = $doctrineGraphqlWithNamingStrategy
            ->buildTypes($em)
            ->buildQueries($em)
            ->buildMutations($em)
            ->toGraphqlSchema();
        $customTypeRegistry = new Registry();
        $doctrineCustomTypeGraphql = new DoctrineGraphQL($customTypeRegistry, $em);
        $doctrineCustomTypeGraphql
            ->buildTypes($em)
            ->buildQueries($em)
            ->buildMutations($em);
        $customTypeRegistry->addType(new ObjectTypeDefinition('XXXType', '', ['name' => ['type' => $registry->getType('LLADoctrineGraphQLTestEntityUser')->value()]]));
        $customTypeRegistry->addQuery(new QueryTypeDefinition('getXXXType', $registry->getType('LLADoctrineGraphQLTestEntityUser')->value(), ['name' => ['type' => $registry->getType('String!')->value()]], function(){}));
        $customTypeRegistry->addMutation(new MutationTypeDefinition('doXXXType', $registry->getType('LLADoctrineGraphQLTestEntityUser')->value(), ['name' => ['type' => $registry->getType('String!')->value()]], function(){}));
        $this->graphqlSchemaWithCustomType = $doctrineCustomTypeGraphql->toGraphqlSchema();
    }

    public function testGraphqlTypes(): void
    {
        $this->assertTrue($this->graphqlSchema->getType('DateTime') instanceof DateTimeType);
        $this->assertTrue($this->graphqlSchema->getType('SearchFilter') instanceof ObjectType);
        $this->assertTrue($this->graphqlSchema->getType('SearchOperator') instanceof EnumType);
        $this->assertTrue($this->graphqlSchema->getType('SortingOrientation') instanceof EnumType);
        $this->assertTrue($this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUser') instanceof ObjectType);
        $this->assertTrue($this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserPage') instanceof ObjectType);
        $this->assertTrue($this->graphqlSchema->getType("LLADoctrineGraphQLTestEntityUserInput") instanceof InputObjectType);
        $this->assertTrue($this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserSearchInput') instanceof InputObjectType);
        $this->assertTrue($this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserSortInput') instanceof InputObjectType);
        $pageType = $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserPage');
        $this->assertEquals(
            $pageType->getField('items')->getType()->getWrappedType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUser')
        );
    }

    public function testGraphqlSchemaQueries(): void
    {
        $this->assertTrue($this->graphqlSchema->getType('Query') instanceof ObjectType);
        /* @var \GraphQL\Type\Definition\ObjectType $queryType */
        $queryType = $this->graphqlSchema->getQueryType();
        $this->assertTrue($queryType->getField('getLLADoctrineGraphQLTestEntityUser')->getType() instanceof ObjectType);
        $this->assertEquals(
            $queryType->getField('getLLADoctrineGraphQLTestEntityUser')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUser')
        );
        $this->assertTrue($queryType->getField('getLLADoctrineGraphQLTestEntityUserPage')->getType() instanceof ObjectType);
        $this->assertEquals(
            $queryType->getField('getLLADoctrineGraphQLTestEntityUserPage')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserPage')
        );
        $this->assertEquals(
            $queryType->getField('getLLADoctrineGraphQLTestEntityUserPage')->getArg('page')->getType(),
            Type::nonNull(Type::int())
        );
        $this->assertEquals(
            $queryType->getField('getLLADoctrineGraphQLTestEntityUserPage')->getArg('limit')->getType(),
            Type::nonNull(Type::int())
        );
        $this->assertEquals(
            $queryType->getField('getLLADoctrineGraphQLTestEntityUserPage')->getArg('match')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserSearchInput')
        );
        $this->assertEquals(
            $queryType->getField('getLLADoctrineGraphQLTestEntityUserPage')->getArg('filter')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserSearchInput')
        );
    }

    public function testGraphqlSchemaMutations(): void
    {
        $this->assertTrue($this->graphqlSchema->getType('Mutation') instanceof ObjectType);
        $this->assertEquals(
            $this->graphqlSchema->getMutationType()->getField('createLLADoctrineGraphQLTestEntityUser')->getArg('input')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserInput')
        );
        $this->assertEquals(
            $this->graphqlSchema->getMutationType()->getField('createLLADoctrineGraphQLTestEntityUser')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUser')
        );
        $this->assertEquals(
            $this->graphqlSchema->getMutationType()->getField('updateLLADoctrineGraphQLTestEntityUser')->getArg('input')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserInput')
        );
        $this->assertEquals(
            $this->graphqlSchema->getMutationType()->getField('updateLLADoctrineGraphQLTestEntityUser')->getArg('input')->getType(),
            $this->graphqlSchema->getType('LLADoctrineGraphQLTestEntityUserInput')
        );
    }

    public function testGraphqlCustomNamingTypes(): void
    {
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('DateTime') instanceof DateTimeType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('SearchFilter') instanceof ObjectType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('SearchOperator') instanceof EnumType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('SortingOrientation') instanceof EnumType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('EntityUser') instanceof ObjectType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserPage') instanceof ObjectType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType("EntityUserInput") instanceof InputObjectType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserSearchInput') instanceof InputObjectType);
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserSortInput') instanceof InputObjectType);
        $pageType = $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserPage');
        $this->assertEquals(
            $pageType->getField('items')->getType()->getWrappedType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUser')
        );
    }

    public function testGraphqlSchemaCustomNamingQueries(): void
    {
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('Query') instanceof ObjectType);
        /* @var \GraphQL\Type\Definition\ObjectType $queryType */
        $queryType = $this->graphqlSchemaWithCustomNameStrategy->getQueryType();
        $this->assertTrue($queryType->getField('getEntityUser')->getType() instanceof ObjectType);
        $this->assertEquals(
            $queryType->getField('getEntityUser')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUser')
        );
        $this->assertTrue($queryType->getField('getEntityUserPage')->getType() instanceof ObjectType);
        $this->assertEquals(
            $queryType->getField('getEntityUserPage')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserPage')
        );
        $this->assertEquals(
            $queryType->getField('getEntityUserPage')->getArg('page')->getType(),
            Type::nonNull(Type::int())
        );
        $this->assertEquals(
            $queryType->getField('getEntityUserPage')->getArg('limit')->getType(),
            Type::nonNull(Type::int())
        );
        $this->assertEquals(
            $queryType->getField('getEntityUserPage')->getArg('match')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserSearchInput')
        );
        $this->assertEquals(
            $queryType->getField('getEntityUserPage')->getArg('filter')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserSearchInput')
        );
    }

    public function testGraphqlSchemaCustomNamingMutations(): void
    {
        $this->assertTrue($this->graphqlSchemaWithCustomNameStrategy->getType('Mutation') instanceof ObjectType);
        $this->assertEquals(
            $this->graphqlSchemaWithCustomNameStrategy->getMutationType()->getField('createEntityUser')->getArg('input')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserInput')
        );
        $this->assertEquals(
            $this->graphqlSchemaWithCustomNameStrategy->getMutationType()->getField('createEntityUser')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUser')
        );
        $this->assertEquals(
            $this->graphqlSchemaWithCustomNameStrategy->getMutationType()->getField('updateEntityUser')->getArg('input')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserInput')
        );
        $this->assertEquals(
            $this->graphqlSchemaWithCustomNameStrategy->getMutationType()->getField('updateEntityUser')->getArg('input')->getType(),
            $this->graphqlSchemaWithCustomNameStrategy->getType('EntityUserInput')
        );
    }

    public function testGraphqlCustomType(): void
    {
        $this->assertTrue(
            $this->graphqlSchemaWithCustomType->hasType('XXXType')
        );
    }

    public function testGraphqlCustomQuery(): void
    {
        $this->assertFalse(
            empty($this->graphqlSchemaWithCustomType->getQueryType('getXXXType'))
        );
    }

    public function testGraphqlCustomMutation(): void
    {
        $this->assertFalse(
            empty($this->graphqlSchemaWithCustomType->getMutationType('insertLLADoctrineGraphQLTestEntityUser'))
        );
    }
}
