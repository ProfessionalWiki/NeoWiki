# Graph Model

NeoWiki stores a query-optimized projection of its data in a Neo4j graph database ([ADR 3](adr/003_Neo4j.md)).
The source of truth for all data remains in MediaWiki revision slots ([ADR 4](adr/004_Use_Dedicated_Slot.md));
the graph is a secondary store that enables efficient querying and relationship traversal.

For definitions of domain terms like Subject, Statement, and Schema, see the [Glossary](Glossary.md).

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

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `id` | integer | MediaWiki page ID (unique) |
| `name` | string | Page title |
| `creationTime` | datetime | When the page was created |
| `lastUpdated` | datetime | When the page was last modified |
| `lastEditor` | string | Username of the last editor |
| `categories` | string[] | MediaWiki categories the page belongs to |

`creationTime` and `lastUpdated` are stored as Neo4j datetime values (ISO 8601), converted from MediaWiki's
`YmdHis` timestamp format.

## Subject Nodes

Each Subject stored on a page gets a `:Subject` node. Subject nodes carry two labels: `Subject` and the name of
their Schema (e.g., `:Subject:Person`, `:Subject:Company`). The Schema label changes if a Subject's type changes.

### Fixed properties

| Property | Neo4j Type | Description |
|----------|------------|-------------|
| `id` | string | Subject ID, 15 characters starting with `s` (unique) |
| `name` | string | Subject label |

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
([ADR 7](adr/007_Multiple_Subjects_Per_Page.md)).

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

Two uniqueness constraints are created on initialization:

- `Page.id` is unique
- `Subject.id` is unique

## Related Documentation

- [ADR 3: Neo4j as Graph Database](adr/003_Neo4j.md)
- [ADR 4: Use Dedicated Slot](adr/004_Use_Dedicated_Slot.md) — primary storage in MediaWiki revision slots
- [ADR 13: Restrict Neo4j Access](adr/013_Restrict_Neo4j_Access.md) — backend-only access to Neo4j
- [SubjectFormat.md](SubjectFormat.md) — JSON format for Subject data in revision slots
- [SchemaFormat.md](SchemaFormat.md) — JSON format for Schema definitions
