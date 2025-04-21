# Backend Validation

Date: 2024-09-10
Status: Accepted

## Context

The backend can get Subject creation or patch requests, that while structurally sound, contain values that are invalid
according to the schemas. Values can be invalid because they violate their properties constraints or because their type
does not match that of their property.

Should the backend validate Values according to the Schema?

## Decision

We do not reject invalid values on the backend.

Invalid values are still stored, be it invalid due to the wrong type or due to constraint violations. Both in the
JSON slot and in Neo4j, though we might revisit neo4j.

## Consequences

* We do not need to implement and maintain backend validation logic
* The frontend is always responsible for validation
* Validation can be bypassed by making requests directly to the REST API

## Alternatives Considered

Rejecting invalid values on the backend:

* We cannot avoid invalid values. Even if we reject invalid values, we can still end up with persisted values that
  become invalid due to schema changes. We thus do not avoid needing to support invalid values in the frontend.
* We need to implement and maintain backend validation logic, keeping it in sync with the frontend.
* Product wise, we wish to keep invalid values and show they are invalid. They might become invalid due to an
  incorrect and soon-reverted change to the Property Definition. Such changes should not remove Subject data.
