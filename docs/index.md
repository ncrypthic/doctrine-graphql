# doctrine-graphql

[Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/) to [PHP GraphQL](https://webonyx.github.io/graphql-php/) bridge. 

## How it works

This library provides a [DoctrineGraphQL](https://github.com/ncrypthic/doctrine-graphql/blob/master/src/DoctrineGraphQL.php) builder class to build all Doctrine entities as GraphQL types by following a [naming convention](types.md#naming-conventions).

For every entity class mapped to GraphQL object type it will create basic GraphQL Query and Mutations.

## Installation

```
composer require ncrypthic/doctrine-graphql
```

## Usage

```php
use LLA\DoctrineGraphQL\DoctrineGraphQL;
use GraphQL\Server\Helper;
use GraphQL\Server\ServerConfig;

/* @var EntityManager $em */
// Get Doctrine's entity manager

$builder = new DoctrineGraphQL();
$builder
    ->buildTypes($em)
    ->buildQueries($em)
    ->buildMutations($em);
    
$schema = $builder->toGraphQLSchema();
$config = ServerConfig::create(['schema'=>$schema]);

$helper = new Helper();
$req = $helper->parseHttpRequest();

$res = is_array($req)
  ? $helper->executeBatch($config, $req)
  : $helper->executeOperation($config, $req);
```

## Queries

See [Queries](queries.md)

## Mutations

See [Mutations](mutations.md)
