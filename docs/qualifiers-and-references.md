---
title: Qualifiers and References
description: How NeoWiki models Wikibase-style qualifiers, references, and rank with Subjects, Relations, and Schemas.
order: 2
---
# Qualifiers and References

NeoWiki Statements have no qualifiers, references, or rank, unlike Wikibase Statements. A Statement is flat: one
property and its Value, with no inner structure. You express qualified and referenced data by reifying it — the value
that needs context becomes its own Subject with its own Schema, linked from the main Subject by a Relation. A page can
hold any number of Subjects ([ADR 7](adr/007-multiple-subjects-per-page.md)), so this adds Subjects, not a page per
value.

NeoWiki is a [Property Graph](https://en.wikipedia.org/wiki/Property_graph): Subjects are nodes with typed properties
and Relations are edges that also carry properties.

For the underlying concepts (Subject, Statement, Relation, Schema), see the [Glossary](glossary.md).

## Qualifying a value: model it as its own Subject

Promoting a value into its own Subject is the same move as a Semantic MediaWiki subobject, except the intermediate node
has a Schema.

Take a museum's yearly attendance. Define an `Attendance` Schema and link one Attendance Subject per year:

```text
Museum Schema
  Attendance figures: relation → Attendance (multiple)

Attendance Schema
  Year:     number
  Visitors: number
  Source:   url
```

Each Attendance Subject (`{ Year: 2024, Visitors: 2500000, Source: "https://…" }`) holds what Wikibase would put as a
qualifier (`Year`) and a reference (`Source`) on one population Statement. Here they are typed properties governed by
the `Attendance` Schema, so they validate and render like any other data. A linked Subject can link to further
Subjects, with no depth limit.

The Attendance Subjects can live on their own pages or as [Child Subjects](glossary.md#page) on the museum's page. The
[Subject Format](api/subject-format.md#complete-example) shows the same pattern in JSON.

## Qualifying a relationship: relation properties

When what needs qualifying is the link between two Subjects, the Relation carries the qualifier. A `Has CEO` Relation
to a Person holds `role` and `since` as its own properties:

```text
Company Schema
  Has CEO: relation → Person
```

Relation properties are free-form key/values on the Relation; unlike a linked Subject's properties they are not declared
in a Schema and not validated.

This is the closest analogue of a Wikibase qualifier. Each Relation has a stable ID
([ADR 10](adr/010-add-guids-to-relations.md)), so an individual relationship is addressable where a Statement is not.

## References

A reference is provenance, modelled as an ordinary property: add a `Source` (or similar) property to the linked
Subject's Schema, or put one on the Relation.

## Rank

NeoWiki has no rank. What rank encodes is ordinary data: model it explicitly — typically a date property for current
vs. historical values, or a status property for deprecated ones.

## Mapping from Wikibase

| Wikibase | NeoWiki |
|---|---|
| Statement | A Subject's Statement (property + value), or a Relation |
| Qualifier on a literal value | A property on a linked Subject (reify the value) |
| Qualifier on a relationship | A property on the Relation |
| Reference | An ordinary property (e.g. `Source`) on the linked Subject or Relation |
| Rank | No equivalent; model explicitly (dated Subjects, status properties) |
| Statement ID | Statements have none; Subjects and Relations both have stable IDs |
| `novalue` / `somevalue` | No equivalent |

## In RDF

A qualified value round-trips without loss: a linked Subject is its own resource, and a Relation keeps its ID and its
properties ([Graph Model](api/graph-model.md)). See [RDF Export](rdf/rdf-export.md) for the native projection and
[Ontology Mapping](rdf/ontology-mapping.md) for projecting into standard ontologies such as EDM.

## Presentation

How a property renders is set by Display Attributes and [Layouts](glossary.md#layout), not by fields on the Statement.

## Further reading

- [Glossary](glossary.md) — Subject, Statement, Relation, Schema, Layout
- [Subject Format](api/subject-format.md) and [Graph Model](api/graph-model.md)
- [Worked example: Person to EDM](examples/person-to-edm.md) — ontology mapping end to end; its CIDOC-CRM tier
  revisits intermediate-node modelling at the RDF level
