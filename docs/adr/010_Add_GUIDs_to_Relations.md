# Add GUIDs to Relations

Date: 2023-06-17

Status: Accepted, ID format superseded by [ADR 014](014_Improved_ID_Format.md)

## Context

In our domain model, relations do not currently have an ID.

Early versions of the NeoWiki prototype had relation IDs to identify them for updates in Neo4j. However, we removed
them because we figured we could just use the relation's target subject ID and the relation type to identify a relation.
This turns out to be insufficient for our use cases.

We need to be able to have multiple relations of the same type to a single target subject. Example:

```cypher
(someEmployee)-[:WORKS_FOR { hiredOn: date("2021-01-01"), position: 'Junior Developer' }]->(someCompany)
(someEmployee)-[:WORKS_FOR { hiredOn: date("2022-01-01"), position: 'Senior Developer' }]->(someCompany)
```

We do not currently support specifying properties on relations via the UI, though this is something on the roadmap,
and already supported by the domain model and Neo4j backend services.

We can avoid using a unique ID by always deleting and reinserting all relations, at the cost of unnecessary writes.

## Decision

We add GUIDs to relations. We use the UUID version 7 format like we do for subjects.

https://uuid.ramsey.dev/en/stable/rfc4122/version7.html#rfc4122-version7

## Consequences

* Our backend code interacting with Neo4j can be simplified.
* We do not need to delete and reinsert all relations when updating a subject.
* Users can track relations across updates via simple Cypher.
* We need to generate GUIDs for relations on the backend.
* We need to reintroduce relation IDs in the domain model.
* There is potential for human confusion between subject IDs and relation IDs since they look the same.
* The size of relations without properties roughly doubles, though this is likely of little consequence.
