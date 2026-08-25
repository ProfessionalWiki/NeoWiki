# NeoWiki Documentation

New to NeoWiki? Try the live sandbox at [neowiki.dev](https://neowiki.dev), or
[install it locally](operations/installation.md).

## User guide

For people working in a wiki that runs NeoWiki.

* [Getting started](guide/getting-started.md) — your first Schema, Subject, View, and query
* [Author an ontology mapping](guide/author-an-ontology-mapping.md) — publish Subjects in EDM, CIDOC-CRM, or another
  vocabulary
* [Keep your wiki current](guide/keep-your-wiki-current.md) — upgrade an evaluation wiki

Reference for wikitext and Lua authors:

* [Parser Functions](authoring/parser-functions.md) — `{{#view}}`, `{{#neowiki_value}}`, and `{{#cypher_raw}}`
* [Lua API](authoring/lua-api.md) — the `mw.neowiki` Scribunto library, including `nw.query()` for Cypher
* [Edit Notices](authoring/edit-notices.md) — messages shown in the Subject editor, the Subject creator, and Manage
  subjects

## Concepts

* [Glossary](glossary.md) — the concepts (Subject, Schema, Statement, View, Layout, Page Property) used
  across the UI, the code, and these docs. Start here.
* [Qualifiers and References](qualifiers-and-references.md) — how NeoWiki models qualifiers, references,
  and rank (for people coming from Wikibase)
* [Architecture](architecture.md) — how the parts fit together, followed by the numbered list of Architecture
  Decision Records
* [Planning docs](https://github.com/ProfessionalWiki/NeoWiki/tree/master/docs/planning) — work-in-progress
  exploration (not published to the website)

## Integration

For developers building on NeoWiki from outside the wiki.

Over HTTP:

* [REST API](api/rest-api.md) — the `/neowiki/v0/*` endpoints, plus the generated OpenAPI spec
* [Schema Format](api/schema-format.md) — JSON format for Schema definitions
* [Subject Format](api/subject-format.md) — JSON format for Subject data
* [Validation Codes](api/validation-codes.md) — stable `code` strings returned by backend validation
* [Query API](api/query-api.md) — read-only Cypher endpoint over the graph backend
* [Graph Model](api/graph-model.md) — Neo4j node and relationship structure

As RDF:

* [RDF Export](rdf/rdf-export.md) — native RDF projection: config, IRI scheme, endpoint, bulk dump
* [Ontology Mapping](rdf/ontology-mapping.md) — projecting into EDM, Dublin Core, … via Mapping pages
* [Worked example: Person to EDM](rdf/person-to-edm.md) — end-to-end mapping walkthrough

## Extending

For developers of MediaWiki extensions that build on NeoWiki.

* [Extending NeoWiki](extending/extending.md) — the extension points, how to hook in, and the RedHerb example
  extension to start from
* [Property Types](extending/property-types.md) — a new kind of value, from PHP validation to Vue components
* [View Types](extending/view-types.md) — a new visual format for rendering a Subject
* [Page Property Providers](extending/page-properties.md) — page metadata in the graph, and refreshing it without
  an edit
* [Revision policy](extending/revision-policy.md) — which revision NeoWiki publishes, for approval extensions
* [Edit notices](extending/edit-notices.md) — a message before a user edits a Subject
* [Subject Sources](extending/subject-sources.md) — Subjects and Schemas supplied from outside this wiki's
  revision slots
* [Graph Database Backends](extending/graph-database-backends.md) — a store of your own that NeoWiki keeps in sync
* [Using NeoWiki from PHP](extending/php.md) — read Subjects and run Cypher from hooks and special pages
* [Using NeoWiki from JavaScript](extending/javascript.md) — the public JS API, displaying values, mounting Vue
  features

## Install & run

For the people who operate the server.

* [Installation](operations/installation.md) — the Docker demo, or adding NeoWiki to an existing MediaWiki
* [Upgrading](operations/upgrading.md) — moving your wiki to the latest NeoWiki
* [Maintenance](operations/maintenance.md) — rebuilding the graph, Neo4j outage behavior, and backups
* [Performance](operations/performance.md) — measured write throughput
