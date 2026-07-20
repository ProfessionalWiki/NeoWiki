---
title: Graph Model
order: 6
---
# Graph Model

NeoWiki projects its data into a Neo4j graph for querying ([ADR 3](../adr/003-neo4j-as-graph-database.md)). The graph
is a secondary store; the source of truth is the MediaWiki revision slots it is built from
([ADR 4](../adr/004-use-dedicated-slot.md)).

For definitions of domain terms like Subject, Statement, and Schema, see the [Glossary](../glossary.md).

## Overview

Two node types and two relationship categories:

```
(:Page)-[:HasSubject {isMain}]->(:Subject:SchemaName)
(:Subject)-[:RelationType {id, ...}]->(:Subject)
```

## Page Nodes

Every page that contains structured data has a `:Page` node.

A shared graph can hold pages from multiple wikis (a wiki farm), and MediaWiki page ids are unique only within a wiki,
so a page is identified by the `(wiki_id, id)` pair, not `id` alone ([ADR 22](../adr/022-multi-wiki-node-identity.md)).
A single-wiki install has just one `wiki_id`.

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

Built-in namespaces have the same ID on every wiki, so `namespaceId` filters consistently across a shared graph.
Custom namespaces (`$wgExtraNamespaces`) get per-wiki IDs whose meaning can differ between wikis; pair `namespaceId`
with `wiki_id` to filter those unambiguously.

## Subject Nodes

Each Subject stored on a page gets a `:Subject` node. Subject nodes carry two labels: `Subject` and the name of their
Schema (e.g. `:Subject:Person`, `:Subject:Company`). The Schema label changes if a Subject's type changes.

### Fixed properties

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `id` | string | Subject ID, 15 characters starting with `s` (unique) |
| `name` | string | Subject label |
| `wiki_id` | string | [MediaWiki Wiki ID](https://www.mediawiki.org/wiki/Manual:Wiki_ID) of the wiki that owns the Subject |

Unlike page ids, Subject ids are globally unique nanoids ([ADR 14](../adr/014-improved-id-format.md)), so a Subject's
identity is its `id` alone. The `wiki_id` is carried only for per-wiki query filtering in a shared graph.

### Dynamic properties

Each non-relation Statement becomes a node property keyed by its Property Name. A `number` Statement with Property
Name "Founded at" and value `2019` becomes the node property `Founded at: 2019`. A key that is not a valid Cypher
identifier must be backtick-escaped when read (`` s.`Founded at` ``).

The PropertyType determines the value's Neo4j type:

| PropertyType | Neo4j value |
|--------------|-------------|
| `text`, `url`, `select` | list of strings |
| `number` | integer or float |
| `boolean` | boolean |
| `date` | list of dates |
| `dateTime` | list of datetimes |
| `relation` | stored as a [relationship](#typed-relations), not a node property |

A Property Name that collides with a fixed property (`id`, `name`, `wiki_id`) does not override it: the fixed value
wins and the Statement's value is not projected.

Values a PropertyType cannot represent are dropped from the projection: a property whose type has no registered
PropertyType produces no node property, and non-ISO 8601 `date`/`dateTime` parts are omitted from the stored value.
The revision slot stays authoritative.

## Relationships

### HasSubject

Connects a Page node to each of its Subject nodes.

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `isMain` | boolean | `true` for the Main Subject, `false` for Child Subjects |

A page can have at most one Main Subject and any number of Child Subjects
([ADR 7](../adr/007-multiple-subjects-per-page.md)).

### Typed Relations

Subject-to-Subject relationships represent Relations. The relationship type in Neo4j is the Relation Type defined in
the Property Definition (e.g. `Has author`, `Has product`). Names that are not valid Cypher identifiers are
backtick-escaped.

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `id` | string | Relation ID, 15 characters starting with `r` |
| *(additional)* | scalar | Any properties from the Relation's property map |

When a Subject is deleted but still has incoming relations from other Subjects, its outgoing relationships and
`HasSubject` relationship are removed, but the node itself is kept so incoming references stay valid. Such a retained
node keeps its Schema labels and dynamic properties; the absence of an incoming `HasSubject` relationship distinguishes
it from a live Subject.

## Constraints

Two node uniqueness constraints apply: `(wiki_id, id)` on `:Page` nodes
([ADR 22](../adr/022-multi-wiki-node-identity.md)) and `id` on `:Subject` nodes. A graph that has never been rebuilt
with `RebuildGraphDatabases.php` lacks them: the incremental per-edit projection does not create them.

Relation (edge) `id` values carry no uniqueness constraint
([#351](https://github.com/ProfessionalWiki/NeoWiki/issues/351)).

## Related Documentation

- [ADR 3: Neo4j as Graph Database](../adr/003-neo4j-as-graph-database.md)
- [ADR 4: Use Dedicated Slot](../adr/004-use-dedicated-slot.md) — primary storage in MediaWiki revision slots
- [ADR 13: Restrict Neo4j Access](../adr/013-restrict-neo4j-access.md) — backend-only access to Neo4j
- [ADR 22: Multi-wiki Graph Node Identity](../adr/022-multi-wiki-node-identity.md) — per-wiki page identity in a shared graph
- [Subject Format](subject-format.md) — JSON format for Subject data in revision slots
- [Schema Format](schema-format.md) — JSON format for Schema definitions
