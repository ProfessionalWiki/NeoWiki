# Restrict Neo4j Access

Date: 2024-09-16

Status: Accepted

## Context

Historically, the NeoWiki frontend interacted directly with Neo4j through Cypher queries.

We discovered that Neo4j does not support restricting write permissions outside of the Enterprise
Edition. We did not know this when choosing Neo4j (ADR 3). Users should not be able to do arbitrary
writes to the data in Neo4j. Those with technical knowhow could do so in our old implementation.

## Decision

Neo4j access can only be done by the backend. It is now seen as an implementation detail of the
backend's persistence layer. The frontend will get data, including Cypher query results, by
making requests to our own REST API.

## Consequences

* The security issue is solved
* The frontend gets information about Subjects via the REST API rather than via Cypher queries to Neo4j
* Custom Cypher queries will continue to be handled via the backend, as in the old implementation's
  {{#cypher:}} parser function. We have to add a `CypherQueryFilter` service to filter out write queries.
* Future requirements for frontend-provided queries will use a new dedicated REST API.
* Cypher queries will be slower and scale less because they need to go through the MediaWiki stack.
* Enterprise users with a Neo4j Enterprise license can still restrict permissions on the Neo4j level as an added
  layer of security.

## Alternatives Considered

### Better Graph Database

We looked for graph databases that support permission management, are open source, have a good query language, and
active community.

We evaluated ArangoDB, Dgraph, Memgraph, JanusGraph, AWS Neptune, and ArcadeDB.

We found all alternatives wanting and not clearly superior to Neo4j. We thus decided to continue with Neo4j.
