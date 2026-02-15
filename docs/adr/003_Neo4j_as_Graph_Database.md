# Neo4j as Graph Database

Date: March 2023

Status: Accepted. Extended by [ADR 19](019_Graph_Database_Architecture.md).

## Context

We need to store our structured data in a database that allows efficient querying.

Semantic MediaWiki has its own implementation on top of SQL and its own query language.
This implementation is complex. The query language, while simple, is specific to the software,
and not widely supported.

Wikibase uses Blazegraph, which is an abandoned triple store. It is queried by SPARQL, which is a
semantic web standard. Amazon Neptune, a commercial graph database, can be used instead of Blazegraph.

Neo4j is the most popular graph database. It is open source and has a large community. It supports
our use case well. Neo4j uses Cypher as query language, which is much more popular than the Semantic
MediaWiki query language. It is more concise than SPARQL and has a more intuitive syntax.

## Decision

Use Neo4j as initial graph database.

Consider neo4j as part of NeoWiki's public API. This means consumers can directly read from the graph database.

## Consequences

* A Neo4j instance will be needed to run NeoWiki.
* We do not need to develop and maintain our own persistence solution.
* We can use existing tools and libraries to work with Neo4j.
* Users will be able to use Cypher queries.
* Various parts of the software will bind to Cypher and Neo4j. Additional implementation and
  abstractions will need to be created to support other graph databases.
* Potential out-of-the-box visualization options that can be integrated into NeoWiki.
