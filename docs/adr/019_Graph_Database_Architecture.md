# Graph Database Architecture

Date: 2026-02-13

Status: Draft

## Context

NeoWiki currently uses Neo4j as its sole graph database ([ADR 3](003_Neo4j_as_Graph_Database.md)). The graph is a
query-optimized projection of data whose source of truth lives in MediaWiki revision slots
([ADR 4](004_Use_Dedicated_Slot.md)). This projection is kept in sync via MediaWiki hooks
(`RevisionFromEditComplete`, `PageDeleteComplete`, etc.).

We want to support RDF and SPARQL for interoperability, especially with the cultural heritage and Linked Open
Data ecosystems. At the same time, the existing Neo4j/Cypher stack remains valuable for enterprise users.

## Decision

### Multiple graph databases as plugins

NeoWiki supports multiple graph database backends simultaneously. Each backend is a plugin that:

1. **Receives domain events** (Subject created, updated, deleted; page deleted, moved) and maintains its own
   projection of the data.
2. **Executes user queries** in its native query language and returns tabular results.
3. **Validates user queries** if needed to ensure they are read-only, using backend-appropriate validation
   (extending [ADR 13](013_Restrict_Neo4j_Access.md)'s approach).

### SPARQL as the triple store abstraction

The SPARQL plugin targets any SPARQL 1.1 conformant triple store via the standard
[SPARQL Protocol](https://www.w3.org/TR/sparql11-protocol/) and
[Graph Store HTTP Protocol](https://www.w3.org/TR/sparql11-http-rdf-update/). This means a single plugin
implementation works with QLever, Oxigraph, Virtuoso, Jena/Fuseki, and others. Store-specific differences are
limited to deployment configuration and optional bulk-load optimizations.

### Each plugin owns its data model mapping

The Neo4j plugin maps NeoWiki data to a property graph (as documented in [GraphModel.md](../GraphModel.md)).
The SPARQL plugin maps the same data to RDF triples. These mappings are deliberately separate: attempting to
force one abstraction over both property graphs and RDF would be artificial and constraining. The shared
contract is at the domain event level, not the graph model level.

### User-facing query language is per-backend

Users write queries in whatever language their wiki's configured backends support. `{{#cypher:...}}` for Neo4j,
`{{#sparql:...}}` for a triple store. NeoWiki does not provide a query abstraction language. This keeps things
simple, avoids reinventing SMW's `#ask`, and lets users leverage the full power of each query language.

### Graph databases are optional

NeoWiki works without any graph database configured. Features that require a graph backend (parser functions for
queries, query result visualizations) are gracefully unavailable when no backend is present. Features that
currently use Neo4j for convenience (e.g., subject label autocomplete for Relation targets) should have fallback
implementations or degrade gracefully.

This is not a near-term priority. The goal is to avoid architectural decisions that make it impossible later.

## Consequences

* NeoWiki gains support for QLever, SPARQL, and RDF.
* Extensions can register additional graph database plugins.
* A new SPARQL plugin needs: an RDF mapping layer (Subject/Schema/Statement to RDF triples), a SPARQL Protocol
  HTTP client, a sync mechanism analogous to `Neo4jQueryStore`, and a read-only query validator.
* The current `QueryEngine`/`WriteQueryEngine` interfaces and `Neo4jQueryStore` remain as the Neo4j plugin.
  They do not need to be generalized into a shared abstraction for all backends.
* The RDF mapping layer requires design decisions on URI schemes, class/predicate naming, and relation
  representation. This is the bulk of the work for the SPARQL plugin and is independent of which triple store
  is used. It warrants its own design document.
* The RDF mapping also enables RDF export (bulk dumps) at near-zero marginal cost, since the same mapping can
  serialize to a file instead of sending SPARQL Update requests.
* User-facing query parser functions (`#cypher`, `#sparql`) go through the MediaWiki backend, which validates
  queries and proxies them to the appropriate backend.
* SPARQL endpoints can optionally be exposed directly to external consumers, which is standard practice in the
  LOD world (public SPARQL endpoints). Whether this is safe depends on the store: QLever supports access tokens
  that restrict writes while allowing public reads; stores without per-request access control (like Oxigraph)
  need proxy-level protection or network isolation. This is a deployment concern, not an architectural one.
  Unlike Neo4j ([ADR 13](013_Restrict_Neo4j_Access.md)), this direct exposure is feasible and expected for
  interoperability use cases.

## Alternatives Considered

### Stay with Neo4j only

Do not make any changes.

Rejected because we have strong demand for SPARQL and want to be able to expose the graph database directly for
read-only queries and federation.

### Replace Neo4j with a triple store

Switch entirely to RDF/SPARQL and drop Cypher support.

Rejected because Neo4j/Cypher is already deeply integrated, has a larger user community for our enterprise use
case, and offers a more intuitive query syntax for non-RDF users. The property-graph model is also a more natural
fit for NeoWiki's data model (Subjects with properties and typed relationships).

### Build a NeoWiki-specific query language

Create an abstraction like SMW's `#ask` that works across backends.

Rejected because this is a large ongoing maintenance burden, limits what users can express, and was one of
the pain points of Semantic MediaWiki. Exposing the native query language of each backend is simpler and more
powerful.
