# Queries

In [Types](types.md) section, we already know how DoctrineGraphQL will automatically create GraphQL type for every Doctrine entity class. But those types cannot be used without graphql queries. DoctrineGraphQL will create 2 queries for each doctrine entity class.

## Single query

The first query, is to get a single entity data. The query name followed the [naming convention] prefixed with `get`.

For example, supposed we have an entity like below:

```php
<?php
namespace Vendor\Package\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class User
{
    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     */
    private $userId;
    /**
     * @ORM\Column(type="string", nullable=false)
     * @ORM\Id
     */
    private $username;
    // ... rest of the code
}
```

The query will need to have at least 1 argument, depending on how many scalar properties mapped as primary key on the associate entity. DoctrineGraphQL will create graphql schema equivalent to:

```
type Query {
  getVendorPackageEntityUser(userId: String!): VendorPackageEntityUser
}
```

## Paginated list query

DoctrineGraphQL will also provide a query to get paginated list data of an entity ObjectType. For example, using the `Vendor\Package\Entity\User` entity above, there will also another graphql query like below:

```
type Query {
  getVendorPackageEntityUserPage(
    page: Int!,
    limit: Int!,
    filter: VendorPackageEntityUserSearchInput,
    match: VendorPackageEntityUserSearchInput,
    sort: VendorPackageEntityUserSort
  ): VendorPackageEntityUserPage
}
```

Whoaaa, whats with all those arguments. We'll discuss more about it. In order to provide basic functions to get list of data of an entity, we need at least the following functionality:

1. Specify how many rows to be returned (limit)
2. Specify how many rows to be skipped before getting no. of rows we expect (page)
3. Select the rows we want to get matching all of specified filters (Logical AND)
4. Select the rows we want to get matching any of specified filters (Logical OR)
5. Get the rows match all above sorted with specified fields value in certain sort orientation (asc, desc)

From above requirements, no.1 & 2 is trivial, we just need to add `page` & `limit` arguments to be used by doctrine to set the `LIMIT` and `OFFSET`.

To implement no. 3 & no.4 functional the solution is to add specific GraphQL `InputObjectType` as filtering input. The filter object type must contain all scalar fields defined in the source entity. More over, every filter fields must accept more than 1 filter condition. So, for every entity object type we need to create a 'SearchInput' to be used on functionallity no.3 & 4. The SearchInput type follows the same [naming convention](types.md#naming-convention) as entity object type name, with suffix `Input`. Using example entity `Vendor\Package\Entity\User` above, the filter input object type will be named `VendorPackageEntityUserSearchInput`.

To implement no. 5, the sorting object type must contains all scalar fields defined in the source entity, as for the field value it MUST contain only 2 option `ASC` and `DESC`. Since sorting orientation may only have two value, DoctrineGraphQL creates an enum for `SortingOrientation`. But for the sort input object type, must be created for every entity object type using the object type [naming convention](types.md#naming-convention) suffixed with `SortInput`. Using the example entity `Vendor\Package\Entity\User` above, the sort input object type will be named `VendorPackageEntityUserSortInput`.

So to implement the functionality, the following types must be specified in the GraphQL schema.

```
enum SearchOperator {
  "LESS_THAN"
  LT

  "LESS_THAN_EQUAL"
  LTE

  "EQUAL"
  EQ

  "GREATER_THAN_EQUAL"
  GTE

  "GREATER_THAN"
  GT

  "NOT_EQUAL"
  NEQ
}

enum SortingOrientation {
  "ASC"
  ASC

  "DESC"
  DESC
}

input SearchFilterInput {
  operator: SearchOperator!,
  value: String
}

input VendorPackageEntityUserSearchInput {
  id: [SearchFilterInput]
  username: [SearchFilterInput]
}

input VendorPackageEntityUserSortInput {
  id: SortingOrientation!
  username: SortingOrientation!
}

type Query {
  getVendorPackageEntityUserPage(
    page: Int!,
    limit: Int!,
    filter: VendorPackageEntityUserSearchInput,
    match: VendorPackageEntityUserSearchInput,
    sort: VendorPackageEntityUserSortInput
  ): VendorPackageEntityUserPage
}
```
