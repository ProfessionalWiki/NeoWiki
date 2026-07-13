# NeoWiki Documentation

Technical documentation for developers building on, integrating with, or running NeoWiki. New to NeoWiki? Try the
live sandbox at [neowiki.dev](https://neowiki.dev), or [install it locally](operations/installation.md).

## Learn the model

* [Glossary](concepts/glossary.md) — the concepts (Subject, Schema, Statement, View, Layout, Page Property) used
  across the UI, the code, and these docs. Start here.

## Build on your wiki

Add and display structured data with wikitext and Lua.

* [Parser Functions](authoring/parser-functions.md) — `{{#view}}`, `{{#neowiki_value}}`, and `{{#cypher_raw}}`
* [Lua API](authoring/lua-api.md) — the `mw.neowiki` Scribunto library, including `nw.query()` for Cypher

## Integrate over HTTP

The REST and query APIs, and the JSON formats they exchange.

* [REST API](api/rest-api.md) — the `/neowiki/v0/*` endpoints, plus the generated OpenAPI spec
* [Schema Format](api/schema-format.md) — JSON format for Schema definitions
* [Subject Format](api/subject-format.md) — JSON format for Subject data
* [Validation Codes](api/validation-codes.md) — stable `code` strings returned by backend validation
* [Query API](api/query-api.md) — read-only Cypher endpoint over the graph backend
* [Graph Model](api/graph-model.md) — Neo4j node and relationship structure

## Publish as RDF

Project Subjects to RDF, natively or mapped onto standard ontologies.

* [RDF Export](rdf/rdf-export.md) — native RDF projection: config, IRI scheme, endpoint, bulk dump
* [Ontology Mapping](rdf/ontology-mapping.md) — projecting into EDM, Dublin Core, … via Mapping pages
* [Worked example: Person to EDM](examples/person-to-edm.md) — end-to-end mapping walkthrough with findings

## Extend NeoWiki

* [Extending NeoWiki](extending/extending.md) — add property types and view types, contribute graph and RDF data,
  and reuse NeoWiki's UI from another extension (with the RedHerb example extension as a starting point)

## Run NeoWiki

* [Installation](operations/installation.md) — the Docker demo, or adding NeoWiki to an existing MediaWiki
* [Maintenance](operations/maintenance.md) — rebuilding the graph, upgrades, and current limitations

## Understand the architecture

* [Architecture Decision Records](adr/001-domain-centric-architecture.md) — numbered, dated architectural decisions
* [Planning docs](https://github.com/ProfessionalWiki/NeoWiki/tree/master/docs/planning) — work-in-progress
  exploration (not published to the website)

---

## Where each kind of doc lives

For contributors adding to these docs:

* Explains a domain idea or model → `concepts/`
* Wikitext or Lua authoring on the wiki → `authoring/`
* An HTTP API or a JSON data format → `api/`
* RDF projection or ontology mapping → `rdf/`
* Extending NeoWiki from another extension → `extending/`
* A worked, end-to-end example → `examples/`
* A sysadmin install, maintenance, or deployment guide → `operations/`
* A numbered, dated decision → `adr/`
* Work-in-progress exploration → `planning/` (not published to the website)
