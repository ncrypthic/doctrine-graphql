# Types

Both [GraphQL](https://graphql.github.io/graphql-spec/June2018/#sec-Types) and [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/reference/basic-mapping.html) have their own type systems. Most of the primitive types can be associated one to another. Like Doctrine columns type `string` can be mapped to GraphQL `String` type, also Doctrine `integer`, `smallint`, and `bigint` all can be mapped with GraphQL `Int` type.

In this sense, it is possible to map a Doctrine's entity class as GraphQL [ObjectType](https://graphql.github.io/graphql-spec/June2018/#sec-Objects) and its properties having primitive type to GraphQL's [ScalarType](https://graphql.github.io/graphql-spec/June2018/#sec-Scalars).

## Naming convention

Cool, since we already know that this is possible then we need to make some conventions on how to map the entity class to GraphQL named type. But there's a catch here, since graphql schema doesn't support namespace we need a way to merge all doctrine entity classes into a single collection.

DoctrineGraphQL currently take the easiest way to do that, **replace all the `\` character from the entity fully qualified class name**. For example, supposed that we have an entity `Vendor\Package\Enity\User` in the generated GraphQL schema the entity type will be named `VendorPackageEntityUser`.

The primitive type mapping between Doctrine types to GraphQL shown on table below.

| Doctrine Type | GraphQL Type |
| ------------- | ------------ |
| string        | String       |
| text          | String       |
| integer       | Int          |
| smallint      | Int          |
| bigint        | Int          |
| boolean       | Boolean      |
| decimal       | float        |
| float         | float        |
| guid          | String       |
| date          | DateTime     |
| time          | DateTime     |
| datetime      | DateTime     |
| datetimetz    | DateTime     |
| object        | -            |
| array         | -            |
| simple_array  | -            |
| json_array    | -            |
| blob          | -            |

That's pretty neat, but how about associations of Doctrine entities (one-to-many, many-to-many, many-to-one and one-to-one)? Well, since GraphQL was essentially a collection of 'instructions' on how to fetch data and re-present it as a graph structure. We can map all those associations in two distinct type, list of objects (one-to-many, many-to-many) amd and single object (many-to-one, one-to-one).

The only problem is we first need populate all Doctrine entities as GraphQL named ObjectType and then we add the associations fields on every defined ObjectType based of the entity association configuration. DoctrineGraphQL already handle [this](#associations) for you.

## Built in types

[DoctrineGraphQL](https://github.com/ncrypthic/doctrine-graphql) also comes with other [built-in types](https://github.com/ncrypthic/doctrine-graphql/blob/master/src/Type/BuiltInTypes.php).

### DateTime

This type is not GraphQL standard type. DateTime time is mapped with Doctrine type `date`, `time`, `datetime` and `datetimetz`.
All those doctrine type will be formatted as as [ISO8601](https://en.wikipedia.org/wiki/ISO_8601) datetime string, and any input
using this type must also be [ISO8601](https://en.wikipedia.org/wiki/ISO_8601) formatted.
