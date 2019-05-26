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
use LLA\DoctrineGraphQL\Type\DateTimeType;
use PHPUnit\Framework\TestCase;

final class DoctrineGraphQLTests extends TestCase
{
    /**
     * @var \GraphQL\Type\Schema
     */
    protected $graphqlSchema;

    public function setUp(): void
    {
        $doctrineGraphql = new DoctrineGraphQL();
        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/Entity"), true, null, null, false);
        $em = EntityManager::create(['driver'=>'pdo_mysql'], $config);
        /* @var \GraphQL\Type\Schema $graphqlSchema */
        $this->graphqlSchema = $doctrineGraphql
            ->buildTypes($em)
            ->buildQueries($em)
            ->buildMutations($em)
            ->toGraphqlSchema();
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
            Type::int()
        );
        $this->assertEquals(
            $queryType->getField('getLLADoctrineGraphQLTestEntityUserPage')->getArg('limit')->getType(),
            Type::int()
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
}
