## Technical Documentation

These docs are aimed at developers working on or interfacing with NeoWiki.

Key docs:
* [Glossary](concepts/glossary.md) - Definitions of NeoWiki concepts. We use these as Ubiquitous Language (UI, code, docs, etc)
* [Parser Functions](reference/parser-functions.md) - Reference for `{{#view}}`, `{{#neowiki_value}}`, and `{{#cypher_raw}}`
* [Lua API](reference/lua-api.md) - Reference for the `mw.neowiki` Scribunto library
* [Schema Format](reference/schema-format.md) - JSON format for Schema definitions
* [Subject Format](reference/subject-format.md) - JSON format for Subject data
* [Graph Model](reference/graph-model.md) - Neo4j node and relationship structure
* [Query API](reference/query-api.md) - REST endpoint for read-only Cypher queries against the graph backend
* [REST API](reference/rest-api.md) - The complete `/neowiki/v0/*` endpoint reference, plus the generated OpenAPI spec served at `/rest.php/specs/v0/module/-`
* [RDF Export](reference/rdf-export.md) - Native RDF projection: config, IRI scheme, the per-page export endpoint, and the bulk dump script
* [Ontology Mapping](reference/ontology-mapping.md) - Projecting Subjects into standard ontologies (EDM, Dublin Core, …) via Mapping pages
* [Extending NeoWiki](reference/extending.md) - How other extensions add property types, contribute graph data, and reuse NeoWiki's UI
* [Installation & Maintenance](operations/installation.md) - Sysadmin guide to installing and maintaining NeoWiki
* [Architecture Decision Records](adr/001-domain-centric-architecture.md) - Numbered, dated architectural decisions
* [Planning docs](https://github.com/ProfessionalWiki/NeoWiki/tree/master/docs/planning) - Work-in-progress exploration and discussion documents

## Organising these docs

* Explains a domain idea or model → `concepts/`
* A precise contract (an API or a data format) → `reference/`
* A numbered, dated decision → `adr/`
* Work-in-progress exploration → `planning/` (not published to the website)
* Sysadmin install, maintenance, or deployment guide → `operations/`
