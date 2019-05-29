# doctrine-graphql
[Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/) to [PHP GraphQL](https://webonyx.github.io/graphql-php/) bridge. 

## How it works

This library provides a [DoctrineGraphQL](https://github.com/ncrypthic/doctrine-graphql/blob/master/src/DoctrineGraphQL.php) builder class to build all Doctrine entities as GraphQL types. It also will  for every GraphQL type. For the mapping to works, this library made some assumptions:

1. Entity's fully qualified class name will be used as GraphQL type without the `\` character (e.g. `Some\Namespace\Entity\User` will be mapped to `SomeNamespaceEntityUser` GraphQL type.
2. Associated fields will be available as GraphQL type property when that entity is the association owner.
3. For every entity that were mapped as GraphQL type, this library can create default CRUD queries & mutations (more on this later).

## Installation

```
composer require ncrypthic/doctrine-graphql
```

## Usage

```php
use LLA\DoctrineGraphQL\DoctrineGraphQL;
use GraphQL\Server\Helper;

/* @var EntityManager $em */
// Get Doctrine's entity manager

$builder = new DoctrineGraphQL();
$builder
    ->buildTypes($em)
    ->buildQueries($em)
    ->buildMutations($em);
    
$schema = $builder->toGraphQLSchema();
$config = ['schema'=>$schema];

$helper = new Helper();
$req = $helper->parseHttpRequest();

$res = is_array($req)
  ? $helper->executeBatch($config, $req)
  : $helper->executeOperation($config, $req);
```

## Queries

For every Doctrine entity, DoctrineGraphQL will generate 2 graphql queries:

1. To get a single entity record `get<GraphQLTypeName>`
2. To get paginated list of entity records `get<GraphQLTypeName>Page`

See more detail on [Queries](queries.md) section.

## Mutations

For every Doctrine entity, DoctrineGraphQL will generate 3 graphql mutations:

1. To insert a single entity record `create<GraphQLTypeName>`
2. To update a single entity record `update<GraphQLTypeName>`
3. To delete a single entity record `delete<GraphQLTypeName>`

See more detail on [Mutations](mutations.md) section.

