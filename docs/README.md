# NeoWiki Documentation

New to NeoWiki? Try the live sandbox at [neowiki.dev](https://neowiki.dev), or
[install it locally](operations/installation.md).

## Use NeoWiki

* [Getting started](guide/getting-started.md) — your first Schema, Subject, View, and query
* [Author an ontology mapping](guide/author-an-ontology-mapping.md) — publish Subjects in EDM, CIDOC-CRM, or another
  vocabulary
* [Keep your wiki current](guide/keep-your-wiki-current.md) — upgrade an evaluation wiki

## Learn the model

* [Glossary](glossary.md) — the concepts (Subject, Schema, Statement, View, Layout, Page Property) used
  across the UI, the code, and these docs. Start here.
* [Qualifiers and References](qualifiers-and-references.md) — how NeoWiki models qualifiers, references,
  and rank (for people coming from Wikibase)

## Developer reference

### Build on your wiki

Add and display structured data with wikitext and Lua.

* [Parser Functions](authoring/parser-functions.md) — `{{#view}}`, `{{#neowiki_value}}`, and `{{#cypher_raw}}`
* [Lua API](authoring/lua-api.md) — the `mw.neowiki` Scribunto library, including `nw.query()` for Cypher

### Integrate over HTTP

The REST and query APIs, and the JSON formats they exchange.

* [REST API](api/rest-api.md) — the `/neowiki/v0/*` endpoints, plus the generated OpenAPI spec
* [Schema Format](api/schema-format.md) — JSON format for Schema definitions
* [Subject Format](api/subject-format.md) — JSON format for Subject data
* [Validation Codes](api/validation-codes.md) — stable `code` strings returned by backend validation
* [Query API](api/query-api.md) — read-only Cypher endpoint over the graph backend
* [Graph Model](api/graph-model.md) — Neo4j node and relationship structure

### Publish as RDF

Project Subjects to RDF, natively or mapped onto standard ontologies.

* [RDF Export](rdf/rdf-export.md) — native RDF projection: config, IRI scheme, endpoint, bulk dump
* [Ontology Mapping](rdf/ontology-mapping.md) — projecting into EDM, Dublin Core, … via Mapping pages
* [Worked example: Person to EDM](rdf/person-to-edm.md) — end-to-end mapping walkthrough

### Extend NeoWiki

* [Extending NeoWiki](extending/extending.md) — add property types and view types, contribute graph and RDF data,
  and reuse NeoWiki's UI from another extension (with the RedHerb example extension as a starting point)

### Run NeoWiki

* [Installation](operations/installation.md) — the Docker demo, or adding NeoWiki to an existing MediaWiki
* [Upgrading](operations/upgrading.md) — moving your wiki to the latest NeoWiki
* [Maintenance](operations/maintenance.md) — rebuilding the graph, Neo4j outage behavior, and backups
* [Performance](operations/performance.md) — measured write throughput

### Understand the architecture

* [Architecture](architecture.md) — how the parts fit together, followed by the numbered list of Architecture
  Decision Records
* [Planning docs](https://github.com/ProfessionalWiki/NeoWiki/tree/master/docs/planning) — work-in-progress
  exploration (not published to the website)
