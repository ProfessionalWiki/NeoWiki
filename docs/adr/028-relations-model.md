# Relations Model

Date: 2026-10-02

Status: Draft

## Context

Relations are the values that point to a Subject: Pablo Picasso's "Birth place" points to Málaga. The Relations epic
([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)) collected questions about them that the model left
open:

- How does a value get context, such as the source of a birth date?
- Does a Relation need an ID ([ADR 10](010-add-guids-to-relations.md)) if it carries no data of its own?
- Can a property point to Subjects of several Schemas, such as a Creator that is a Person or a Group?
- What happens when a Relation points to a Subject that does not exist?
- Are Subjects that share a page related ([ADR 7](007-multiple-subjects-per-page.md))?
- Does a relation property need a second name for its graph edges?

## Decision

### 1. Context on a value is a Subject, not an edge property

A value that needs context becomes a Subject with its own Schema
([Qualifiers and References](../qualifiers-and-references.md)). If Picasso's birth date needs a source, his Subject
points to a Birth that holds the date and the source. The Birth's Schema can declare its Subjects dependent, so that the
Birth is edited, stored and removed with its Person ([ADR 35](035-dependent-subjects.md)).

Edge properties, the extra fields a Relation can carry, are removed: they add context only to a link, never to a date,
and have no Schema and no editor. A Relation is an ID and a target
([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)).

### 2. Each Relation keeps its ID

The ID gives a Relation a stable identity across edits. [Mappings](../authoring/mapping-format.md) use it to name the
nodes they create for individual Relations.

### 3. A relation property targets one or more Schemas

A relation property lists one or more target Schemas ([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)). A
Relation to a Subject of another Schema is
[`relation-target-schema-mismatch`](../api/validation-codes.md#relation-target-schema-mismatch), an error that can block
the save ([ADR 26](026-validation-severity-levels.md)).

### 4. Missing targets are red links

A Relation may point to a Subject that does not exist, as a wiki link may point to a page nobody has written. Imports
rely on this to create Subjects that point to each other
([#1100](https://github.com/ProfessionalWiki/NeoWiki/issues/1100)). The server reports
[`relation-target-not-found`](../api/validation-codes.md#relation-target-not-found) as a warning, and the UI shows a red
link that offers to create the Subject ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)). Relations to
Dependent Subjects are the exception ([ADR 35](035-dependent-subjects.md)).

### 5. Sharing a page does not relate Subjects

Subjects that share a page are related only through relation properties, as Subjects on different pages are
([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)).

The Main Subject stays: it says which Subject the page is about, and the page shows it automatically.

### 6. A relation property has one name

A relation property's `relation` field is removed: graph edges and RDF predicates take the property name, as other
properties' RDF predicates already do, so a link has the same name everywhere
([#1553](https://github.com/ProfessionalWiki/NeoWiki/issues/1553)).

## Consequences

- Each value with context adds a Subject, which counts toward [ADR 29](029-scalability-targets.md)'s scalability
  targets.
- Cypher queries match Relations by property name, such as `` `Birth place` ``, not by verb phrases such as `BORN_IN`.
- The Schema and Subject formats change incompatibly. NeoWiki is not in production, so that is acceptable.
- Out of scope:
  - targets narrower than a Schema, such as only the Persons who are painters;
  - Relations to any Subject regardless of its Schema;
  - cardinalities other than one or many;
  - Schema hierarchies, such as Person and Group below an Actor Schema;
  - "no value" and "unknown value" markers ([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937));
  - showing referencing Subjects on pages and Views ([#1528](https://github.com/ProfessionalWiki/NeoWiki/issues/1528)),
    and adding a referencing Subject from its target's editor
    ([#1530](https://github.com/ProfessionalWiki/NeoWiki/issues/1530)).

## Related

- [ADR 7: Multiple Subjects Per Page](007-multiple-subjects-per-page.md)
- [ADR 10: Add GUIDs to Relations](010-add-guids-to-relations.md)
- [ADR 35: Dependent Subjects](035-dependent-subjects.md)
- [Qualifiers and References](../qualifiers-and-references.md)
