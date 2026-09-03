---
title: Architecture
order: 3
---
# Architecture

NeoWiki is a MediaWiki extension. It adds structured data to ordinary wiki pages, keeps that data on the page it
describes, and projects it into graph stores so it can be queried.

## Data lives on the page

A page's structured data sits in a dedicated content slot on the page itself, stored as JSON and versioned with the
page exactly like its wikitext ([ADR 2](adr/002-store-data-as-json.md), [ADR 4](adr/004-use-dedicated-slot.md)).
That slot content is the source of truth.

The slot holds Subjects, each carrying Statements and shaped by a Schema. The [Glossary](glossary.md) defines this
vocabulary, which the UI and the code share.

## Graph stores hold projections

A wiki can connect to one or more graph stores: Neo4j, and any SPARQL 1.1 store. Each holds a Projection, a derived,
query-optimized copy of the wiki's data, written on every save and rebuildable from page content at any time
([ADR 19](adr/019-graph-database-architecture.md)). A Projection is either the built-in native one or an ontology
projection defined by a [Mapping](rdf/ontology-mapping.md), which expresses Subjects in a vocabulary such as EDM or
CIDOC-CRM.

Queries are written in each backend's own language, Cypher for Neo4j and SPARQL for SPARQL stores, and run from
parser functions, Lua, and the REST API. NeoWiki deliberately has no query language of its own
([ADR 19](adr/019-graph-database-architecture.md)).

The subject-to-page index is authoritative in a MediaWiki table, so a wiki can also run with no graph store at all
([ADR 32](adr/032-subject-page-index.md)): graph queries are then unavailable, and the rest works.

## Frontend and extension points

The editing and display interfaces are a TypeScript and Vue application built on Wikimedia Codex and embedded into
wiki pages ([ADR 15](adr/015-dedicated-editors.md), [ADR 16](adr/016-frontend-state-management.md),
[ADR 20](adr/020-codex-styling-policy.md)). The PHP behind them follows a domain-centric architecture
([ADR 1](adr/001-domain-centric-architecture.md)).

Other MediaWiki extensions can add Property Types, View Types, Page Properties, graph backends, and frontend
components ([Extending NeoWiki](extending/extending.md)).

## Architecture Decision Records

Every architectural decision on record, in the order it was made.

* [ADR 1: Domain Centric Architecture](adr/001-domain-centric-architecture.md)
* [ADR 2: Store Data as JSON](adr/002-store-data-as-json.md)
* [ADR 3: Neo4j as Graph Database](adr/003-neo4j-as-graph-database.md)
* [ADR 4: Use a Dedicated MediaWiki Revision Slot](adr/004-use-dedicated-slot.md)
* [ADR 5: Subject GUIDs](adr/005-subject-guids.md)
* [ADR 6: Schemas](adr/006-schemas.md)
* [ADR 7: Multiple Subjects Per Page](adr/007-multiple-subjects-per-page.md)
* [ADR 8: One Schema per Subject](adr/008-one-schema-per-subject.md)
* [ADR 9: Move Away from JSON Schema](adr/009-move-away-from-json-schema.md)
* [ADR 10: Add GUIDs to Relations](adr/010-add-guids-to-relations.md)
* [ADR 11: Include Writer's Schema in Subjects](adr/011-include-writers-schema.md)
* [ADR 12: Backend Validation](adr/012-backend-validation.md)
* [ADR 13: Restrict Neo4j Access](adr/013-restrict-neo4j-access.md)
* [ADR 14: Improved ID Format](adr/014-improved-id-format.md)
* [ADR 15: Dedicated Editors](adr/015-dedicated-editors.md)
* [ADR 16: Frontend State Management](adr/016-frontend-state-management.md)
* [ADR 17: Names as Identifiers](adr/017-names-as-identifiers.md)
* [ADR 18: Views and Layouts](adr/018-views.md)
* [ADR 19: Graph Database Architecture](adr/019-graph-database-architecture.md)
* [ADR 20: Codex Styling Policy](adr/020-codex-styling-policy.md)
* [ADR 21: Add Backend Validation](adr/021-add-backend-validation.md)
* [ADR 22: Multi-wiki Graph Node Identity](adr/022-multi-wiki-node-identity.md)
* [ADR 23: Subject Sources](adr/023-subject-sources.md)
* [ADR 24: Frontend Extension Mechanism](adr/024-frontend-extension-mechanism.md)
* [ADR 25: Backend-driven Frontend Validation](adr/025-backend-driven-frontend-validation.md)
* [ADR 26: Validation Severity Levels](adr/026-validation-severity-levels.md)
* [ADR 27: Access Control](adr/027-access-control.md)
* [ADR 28: Relations Model](adr/028-relations-model.md)
* [ADR 29: Scalability Targets](adr/029-scalability-targets.md)
* [ADR 30: Frontend Stores Are Registries, Not Caches](adr/030-frontend-store-registry-semantics.md)
* [ADR 31: Optional Subject Labels](adr/031-optional-subject-labels.md)
* [ADR 32: Subject-to-Page Index](adr/032-subject-page-index.md)
