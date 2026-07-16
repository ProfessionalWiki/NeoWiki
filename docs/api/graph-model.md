---
title: Graph Model
order: 6
---
# Graph Model

NeoWiki stores a query-optimized projection of its data in a Neo4j graph database ([ADR 3](../adr/003-neo4j-as-graph-database.md)).
The source of truth for all data remains in MediaWiki revision slots ([ADR 4](../adr/004-use-dedicated-slot.md));
the graph is a secondary store that enables efficient querying and relationship traversal.

For definitions of domain terms like Subject, Statement, and Schema, see the [Glossary](../glossary.md).

## Overview

The graph consists of two node types and two relationship categories:

```
(:Page)-[:HasSubject {isMain}]->(:Subject:SchemaName)
(:Subject)-[:RelationType {id, ...}]->(:Subject)
```

Page nodes represent MediaWiki pages and carry page-level metadata. Subject nodes represent structured data entities.
`HasSubject` relationships connect pages to their Subjects. Typed relationships connect Subjects to other Subjects
via Relations.

## Page Nodes

Every page that contains structured data has a corresponding `:Page` node. These nodes make page metadata available
for graph queries.

Page identity is scoped per wiki. A shared graph can hold pages from multiple wikis (e.g. a wiki farm), and MediaWiki
page ids are only unique within a single wiki. Each page node therefore carries a `wiki_id` and is identified by the
`(wiki_id, id)` pair ([ADR 22](../adr/022-multi-wiki-node-identity.md)). In a single-wiki install there is one
`wiki_id` value and behaviour is unchanged.

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `wiki_id` | string | [MediaWiki Wiki ID](https://www.mediawiki.org/wiki/Manual:Wiki_ID) (database name + table prefix) of the owning wiki |
| `id` | integer | MediaWiki page ID (unique per wiki) |
| `name` | string | Full page title, including the namespace prefix (e.g. `Help:Installation`) |
| `namespaceId` | integer | MediaWiki namespace ID of the page (e.g. `0` for the main namespace, `12` for Help) |
| `creationTime` | datetime | When the page was created |
| `lastUpdated` | datetime | When the page was last modified |
| `lastEditor` | string | Username of the last editor |
| `categories` | string[] | MediaWiki categories the page belongs to |

`creationTime` and `lastUpdated` are stored as Neo4j datetime values (ISO 8601), converted from MediaWiki's
`YmdHis` timestamp format.

`namespaceId` holds MediaWiki's canonical namespace ID. Built-in namespaces (e.g. `0` main, `12` Help, `14`
Category) have the same ID on every wiki, so filtering by `namespaceId` behaves consistently across a graph
shared by multiple wikis. Custom namespaces defined via `$wgExtraNamespaces` get per-wiki IDs that may differ in
meaning between wikis, so pair `namespaceId` with `wiki_id` to filter those unambiguously.

## Subject Nodes

Each Subject stored on a page gets a `:Subject` node. Subject nodes carry two labels: `Subject` and the name of
their Schema (e.g., `:Subject:Person`, `:Subject:Company`). The Schema label changes if a Subject's type changes.

### Fixed properties

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `id` | string | Subject ID, 15 characters starting with `s` (unique) |
| `name` | string | Subject label |
| `wiki_id` | string | [MediaWiki Wiki ID](https://www.mediawiki.org/wiki/Manual:Wiki_ID) of the wiki that owns the Subject |

Unlike page ids, Subject ids are globally unique nanoids ([ADR 14](../adr/014-improved-id-format.md)), so a Subject's
identity is its `id` alone. The `wiki_id` is carried only for per-wiki query filtering in a shared graph; Subject-id
namespacing is deferred ([ADR 22](../adr/022-multi-wiki-node-identity.md)).

### Dynamic properties

Each Statement on a Subject becomes a node property, keyed by Property Name. The value is converted to a
Neo4j-compatible format by the corresponding PropertyType implementation. For example, a Statement with
Property Name "Founded at" and a number value of `2019` results in a node property `Founded at: 2019`.

Relation-type Statements are not stored as node properties. They are stored as relationships between Subject
nodes (see below).

## Relationships

### HasSubject

Connects a Page node to each of its Subject nodes.

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `isMain` | boolean | `true` for the Main Subject, `false` for Child Subjects |

A page can have at most one Main Subject and any number of Child Subjects
([ADR 7](../adr/007-multiple-subjects-per-page.md)).

### Typed Relations

Subject-to-Subject relationships represent Relations. The relationship type in Neo4j is the Relation Type defined
in the Property Definition (e.g., `Has author`, `Has product`). Names that are not valid Cypher identifiers are
backtick-escaped.

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `id` | string | Relation ID, 15 characters starting with `r` |
| *(additional)* | scalar | Any properties from the Relation's property map |

When a Subject is deleted but still has incoming relations from other Subjects, its outgoing relationships and
`HasSubject` relationship are removed, but the node itself is kept so that the incoming references remain valid.

## Constraints

Two uniqueness constraints are intended for the graph — the `(wiki_id, id)` pair is unique on `:Page` nodes
([ADR 22](../adr/022-multi-wiki-node-identity.md)), and `Subject.id` is unique. These are **not** created
automatically yet ([#874](https://github.com/ProfessionalWiki/NeoWiki/issues/874)).

## Related Documentation

- [ADR 3: Neo4j as Graph Database](../adr/003-neo4j-as-graph-database.md)
- [ADR 4: Use Dedicated Slot](../adr/004-use-dedicated-slot.md) — primary storage in MediaWiki revision slots
- [ADR 13: Restrict Neo4j Access](../adr/013-restrict-neo4j-access.md) — backend-only access to Neo4j
- [ADR 22: Multi-wiki Graph Node Identity](../adr/022-multi-wiki-node-identity.md) — per-wiki page identity in a shared graph
- [Subject Format](subject-format.md) — JSON format for Subject data in revision slots
- [Schema Format](schema-format.md) — JSON format for Schema definitions
