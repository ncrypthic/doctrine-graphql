# Mutations

DoctrineGraphQL will create basic mutations (create, update and delete) for every entity object type.

## Create mutation

Create mutation is used to create a new data for an entity mapped to object type. It will return the created entity data. The create mutation is defined as below:

```
type Mutation {
  createVendorPackageEntityUser(input: VendorPackageEntityUserInput): VendorPackageEntityUser
}
```

## Update mutation

Update mutation is used to update an entity data. Every identity fields (Doctrine entity ID) will be used as identified filter, while other value, will be used as update source. It will returned updated entity data. The update mutation is defined as below:

```
type Mutation {
  updateVendorPackageEntityUser(input: VendorPackageEntityUserInput): VendorPackageEntityUser
}
```

## Delete mutation

Delete mutation is used to delete an entity data. This mutation arguments will vary depending on what doctrine identity fields defined on the source entity. It will returned the deleted entity. The delete mutation is defined as below:

```
type Mutation {
  deleteVendorPackageEntityUser(id: String!): VendorPackageEntityUser
}
```
