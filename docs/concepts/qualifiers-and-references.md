---
title: Qualifiers and References
order: 2
---
# Qualifiers and References

NeoWiki Statements have no qualifiers, references, or ranks the way Wikibase Statements do. This is deliberate, and
it is the question we hear most often from people coming from Wikibase. This page explains how NeoWiki models the same
needs, and why it takes a different shape.

For the underlying concepts (Subject, Statement, Relation, Schema), see the [Glossary](glossary.md).

## The short version

A NeoWiki Statement is one property and its value — a single row in an infobox. It has no inner structure. Instead of
nesting qualifiers and references inside a Statement, you model the extra structure by linking Subjects with Relations:

- **Qualifying a value** (e.g. "population, *as of* this year, *from* this source") → promote the value into its own
  Subject with its own Schema.
- **Qualifying a relationship** (e.g. "*Has CEO* Jane, *since* 2019") → put the qualifier on the Relation.
- **A reference** → an ordinary, typed property on that Subject or Relation. References are not special.

Because the things you link have Schemas, the "qualifiers" and "references" are themselves typed and validated. In
Wikibase terms, you get *schemas for your qualifiers* — something Wikibase does not offer.

## Flat Statements by design

A [Statement](glossary.md#statement) is a property name, a value, and the property's type. That is all. There is no
qualifier list, no reference list, no rank, and no per-Statement ID.

Richer structure is expressed by linking [Subjects](glossary.md#subject) through
[Relations](glossary.md#value) ([ADR 7](../adr/007-multiple-subjects-per-page.md),
[ADR 10](../adr/010-add-guids-to-relations.md)), not by adding fields inside a Statement. This keeps Schemas
two-dimensional and predictable, keeps the editing UIs simple, and makes the surrounding context schema-defined rather
than a free-form bag.

## Qualifying a value: model it as its own Subject

When a value needs context, promote it into a dedicated Subject and link it from the main Subject with a Relation. This
is the same move as a Semantic MediaWiki subobject or a Wikidata value node, but the intermediate node has a Schema.

Take a museum's yearly attendance. You define an `Attendance` Schema and link one Attendance Subject per year:

```text
Museum Schema
  Attendance figures: relation → Attendance (multiple)

Attendance Schema
  Year:     number
  Visitors: number
  Source:   url
```

```json
{
  "label": "Rijksmuseum",
  "schema": "Museum",
  "statements": {
    "Attendance figures": {
      "type": "relation",
      "value": [ { "target": "s…2024" }, { "target": "s…2023" } ]
    }
  }
}
```

Each Attendance Subject (`{ Year: 2024, Visitors: 2500000, Source: "https://…" }`) carries what Wikibase would express
as the qualifier (`Year`) and the reference (`Source`) on a single population-style Statement. Here they are real,
typed properties governed by the `Attendance` Schema, so they validate and render like any other data. There is no
depth limit: a linked Subject can link to further Subjects.

The full JSON shape is in the [Subject Format](../reference/subject-format.md) reference. The Attendance Subjects can
live on their own pages or as [Child Subjects](glossary.md#page) on the museum's page; whether Child Subjects should
*also* be linked to the page's Main Subject automatically is an open question
([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)).

## Qualifying a relationship: relation properties

When the thing being qualified is a link between two Subjects, the Relation itself carries the qualifier. A "Has CEO"
relationship can hold `role` and `since`:

```text
Company Schema
  Has CEO: relation → Person (with properties: role, since)
```

This is the closest direct analogue of a Wikibase qualifier. Each Relation also has a stable ID
([ADR 10](../adr/010-add-guids-to-relations.md)), so — unlike a Statement — an individual relationship is
addressable.

Relation properties exist in the data model and the graph backend today. Giving them their own Schema definitions, so
they are typed and validated like everything else, is being designed in
[#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630).

## References

A reference is provenance, so it is modelled as a normal property: add a `Source` (or similar) property to the Schema
of the linked Subject, or as a property on the Relation. The `Source` in the Attendance example above is exactly this.
Nothing about a reference is a special kind of thing in NeoWiki — it is a typed property like any other.

## Rank

NeoWiki has no rank. The cases Wikibase solves with rank are modelled explicitly:

- **Current vs. historical values** → separate Subjects that carry a date (the attendance pattern), then query or sort
  for the one you want.
- **Deprecated or wrong values** → do not store them, or add an explicit status property.

This is more verbose than a rank flag, but explicit and directly queryable.

## Why this shape

- **Simplicity.** A Statement stays a property/value pair and a Schema stays two-dimensional — easy to reason about,
  edit, and render.
- **Schemas for everything.** Qualifiers and references live on Subjects and Relations that have Schemas, so they are
  typed and validated instead of being free-form key/value bags.
- **One mechanism.** There is a single way to add structure — link a Subject — usable at any depth, rather than a
  separate qualifier, reference, and rank mechanism bolted onto each Statement.
- **Clean projection.** Linked Subjects are graph nodes and Relations are graph edges, so the graph and RDF
  projections fall out naturally (below).

The trade-off is more nodes to create, and inline editing of nested structures is an active UX design area.

## In the graph and in RDF

In Neo4j, a Relation is an edge and a linked Subject is another node; see the
[Graph Model](../reference/graph-model.md).

In RDF, the projection uses Wikibase-style reification: a direct triple for simple queries, plus a Relation node that
preserves the Relation's ID and its properties. A linked Subject is simply its own resource with its own triples, so a
"qualified value" round-trips without loss. The mapping is being worked out in the
[RDF Mapping planning doc](https://github.com/ProfessionalWiki/NeoWiki/blob/master/docs/planning/RdfMapping.md).

## Mapping from Wikibase

| Wikibase | NeoWiki |
|---|---|
| Statement | A Subject's Statement (property + value), or a Relation |
| Qualifier on a literal value | A property on a linked Subject (reify the value) |
| Qualifier on a relationship | A property on the Relation |
| Reference | An ordinary property (e.g. `Source`) on the linked Subject or Relation |
| Rank | No equivalent; model explicitly (dated Subjects, status properties) |
| Statement ID | Statements have none; Subjects and Relations both have stable IDs |
| `novalue` / `somevalue` | Not currently modelled (open: [#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937)) |

## Related metadata and display

Two things Wikibase veterans sometimes group with qualifiers are handled separately in NeoWiki:

- **Per-Statement metadata.** Each Statement does record the property's type at write time — the "writer's schema"
  ([ADR 11](../adr/011-include-writers-schema.md)) — so historical Statements stay interpretable after a Schema
  changes.
- **Presentation.** How a property renders is configured by Display Attributes and [Layouts](glossary.md#layout), not
  by fields on the Statement.

## Further reading

- [Glossary](glossary.md) — Subject, Statement, Relation, Schema, Layout
- [ADR 7: Multiple Subjects Per Page](../adr/007-multiple-subjects-per-page.md)
- [ADR 10: Add GUIDs to Relations](../adr/010-add-guids-to-relations.md)
- [Subject Format](../reference/subject-format.md) and [Graph Model](../reference/graph-model.md)
- Open design: [#630 (relation property schemas)](https://github.com/ProfessionalWiki/NeoWiki/issues/630),
  [#959 (Main/Child Subject semantics)](https://github.com/ProfessionalWiki/NeoWiki/issues/959)
