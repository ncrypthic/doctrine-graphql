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

| Query name | Arguments | ReturnType | Description |
| ---------- | --------- | ---------- | ----------- |
| get`<Type>` | List of `Entity`'s `@ID` fields that was mapped to `Type` | `<Type>` | Get a single object of `entity` by `<identity>` field |
| get`<Type>`Page | page: Int!, limit: Int!, filter: [], match: [], sort: [] | `<Type>`Page | Get paginated data of `Entity` that was mapped to `Type` |

## Mutations

| Mutation name | Arguments | Return Type | Description |
| ------------- | --------- | ----------- | ----------- |
| create`<Type>` | input: `Entity`Input | `Type` | Creates new `Entity` data mapped to `Type` |
| update`<Type>` | input: `Entity`Input | `Type` | Update an `Entity` data mapped to `Type`. All identity fields on `input` arguments is used to match the data, other args will be used as update values |
| delete`<Type>` | List of `Entity`'s `@ID` fields | `Type` | Delete an `Entity` mapped as `Type` which match all the arguments and return deleted entity data |

## License
MIT
