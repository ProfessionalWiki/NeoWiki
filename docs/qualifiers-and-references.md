---
title: Qualifiers and References
description: How NeoWiki models Wikibase-style qualifiers, references, and rank with Subjects, Relations, and Schemas.
order: 2
---
# Qualifiers and References

NeoWiki Statements have no qualifiers, references, or rank the way Wikibase Statements do. NeoWiki uses a different
approach, which still allows you to express qualified and referenced data, and comes with notable benefits like
unlimited depth and Schemas (validation, UIs).

This page explains how NeoWiki models the same needs, and why it follows the
[Property Graph model](https://en.wikipedia.org/wiki/Property_graph).

For the underlying concepts (Subject, Statement, Relation, Schema), see the [Glossary](glossary.md).

## The short version

NeoWiki's Statements are flat: one property and its Value. They have no inner structure.

You model what would be a qualified Statement in Wikibase as a NeoWiki Subject.

Example: In Wikibase, you might have an Item about Berlin, with Statements about its population, qualified by year of
measurement. In NeoWiki you instead have one Subject for Berlin, and then an additional Subject per population figure.
The Berlin Subject links to these population Subjects via Relations.

Because your population Subjects have their own Schema, your users get a form-like experience when adding a new
population: the right properties are shown, and the validation rules you specified are applied. In Wikibase terms, you
get *schemas for your qualifiers*.

NeoWiki allows storing an arbitrary number of Subjects on a single page, so there is no explosion of Subject pages,
which a Wikibase user might otherwise expect.

## Qualifying a value: model it as its own Subject

When a value needs context, promote it into a dedicated Subject and link it from the main Subject with a Relation. This
is the same move as a Semantic MediaWiki subobject or an Item-valued Statement in Wikidata, but the intermediate node
has a Schema.

Take a museum's yearly attendance. You define an `Attendance` Schema and link one Attendance Subject per year:

```text
Museum Schema
  Attendance figures: relation → Attendance (multiple)

Attendance Schema
  Year:     number
  Visitors: number
  Source:   url
```

Each Attendance Subject (`{ Year: 2024, Visitors: 2500000, Source: "https://…" }`) carries what Wikibase would express
as the qualifier (`Year`) and the reference (`Source`) on a single population-style Statement. Here they are real,
typed properties governed by the `Attendance` Schema, so they validate and render like any other data. There is no
depth limit: a linked Subject can link to further Subjects.

The [Subject Format](api/subject-format.md#complete-example) reference shows this exact pattern in JSON: a city whose
population Subjects carry a `Date` and a `References` property. The Attendance Subjects can live on their own pages or
as [Child Subjects](glossary.md#page) on the museum's page.

## Qualifying a relationship: relation properties

When the thing being qualified is a link between two Subjects, the Relation itself carries the qualifier. A "Has CEO"
relationship can hold `role` and `since`:

```text
Company Schema
  Has CEO: relation → Person (with properties: role, since)
```

This is the closest direct analogue of a Wikibase qualifier. Each Relation also has a stable ID
([ADR 10](adr/010-add-guids-to-relations.md)), so — unlike a Statement — an individual relationship is
addressable.

## References

A reference is provenance, so it is modelled as a normal property: add a `Source` (or similar) property to the Schema
of the linked Subject, or put one on the Relation. The `Source` in the Attendance example above is exactly this.

## Rank

NeoWiki has no rank. What rank encodes is ordinary data, modelled with the standard mechanisms as the data modeller
sees fit — typically a date property for current vs. historical values, or a status property for deprecated ones.

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

## Why this approach

NeoWiki follows the Property Graph model: Subjects are nodes with typed properties, and Relations are edges that can
carry properties of their own, so qualifying a relationship needs no extra machinery.

- **Simplicity.** A Statement stays a property/value pair and a Schema stays two-dimensional — easy to reason about,
  edit, and render.
- **Schemas for everything.** Qualifiers and references live on Subjects and Relations that have Schemas, so they are
  typed and validated instead of being free-form key/value bags.
- **One mechanism.** There is a single way to add structure — link a Subject — usable at any depth, rather than a
  separate qualifier, reference, and rank mechanism bolted onto each Statement.
- **Clean projection.** Linked Subjects are graph nodes and Relations are graph edges, so the graph and RDF
  projections fall out naturally (below).

The trade-off is more Subjects to create. This downside is partially mitigated by NeoWiki's support for multiple
Subjects per page.

## In the graph and in RDF

In Neo4j, a Relation is an edge and a linked Subject is another node; see the
[Graph Model](api/graph-model.md).

In RDF, the projection uses Wikibase-style reification: a direct triple for simple queries, plus a Relation node that
preserves the Relation's ID and its properties. A linked Subject is simply its own resource with its own triples, so a
"qualified value" round-trips without loss. See [RDF Export](rdf/rdf-export.md) for the native projection, and
[Ontology Mapping](rdf/ontology-mapping.md) for projecting into standard ontologies such as EDM.

## Related metadata and display

Two things Wikibase veterans sometimes group with qualifiers are handled separately in NeoWiki:

- **Per-Statement metadata.** Each Statement does record the property's type at write time — the "writer's schema"
  ([ADR 11](adr/011-include-writers-schema.md)) — so historical Statements stay interpretable after a Schema
  changes.
- **Presentation.** How a property renders is configured by Display Attributes and [Layouts](glossary.md#layout), not
  by fields on the Statement.

## Further reading

- [Glossary](glossary.md) — Subject, Statement, Relation, Schema, Layout
- [ADR 7: Multiple Subjects Per Page](adr/007-multiple-subjects-per-page.md)
- [ADR 10: Add GUIDs to Relations](adr/010-add-guids-to-relations.md)
- [Subject Format](api/subject-format.md) and [Graph Model](api/graph-model.md)
- [Worked example: Person to EDM](examples/person-to-edm.md) — ontology mapping end to end; its CIDOC-CRM tier
  revisits intermediate-node modelling at the RDF level
- Open design: [#630 (Relations design)](https://github.com/ProfessionalWiki/NeoWiki/issues/630),
  [#959 (Main/Child Subject semantics)](https://github.com/ProfessionalWiki/NeoWiki/issues/959)
