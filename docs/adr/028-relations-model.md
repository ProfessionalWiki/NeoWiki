# Relations Model

Date: 2026-10-11

Status: Accepted

## Context

Relations are the values that point to a Subject: Pablo Picasso's "Birth place" points to Málaga. The Relations epic
([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)) collected questions about them that NeoWiki's initial
model left open:

- Can a Relation hold data of its own, such as a source for Picasso's birth place?
- Can a relation Property Definition list several target Schemas, such as Person and Group for a Creator?
- What happens when a Relation points to a Subject that does not exist?
- Does a relation Property Definition need a second name for its graph edges?
- Are Subjects that share a page related ([ADR 7](007-multiple-subjects-per-page.md))?

## Decision

### 1. Relations hold no data of their own

A Relation is just an ID and a target. Its property-value pairs are removed
([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)): they were a second way to add context, only for
links, and had no Schema and no editor.

Context on a link goes into a Subject in between, as context on any other value already does
([Qualifiers and References](../qualifiers-and-references.md)). If Picasso's birth place needs a source, Picasso points
to a Birth that holds the place and the source. The Birth's Schema can declare its Subjects dependent, so that the Birth
is edited, stored and removed with Picasso ([ADR 35](035-dependent-subjects.md)).

The ID stays, so that the graph and Mappings can keep track of a link across edits
([ADR 10](010-add-guids-to-relations.md)).

### 2. A relation Property Definition lists one or more target Schemas

A relation Property Definition lists one or more target Schemas
([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)). A Relation to a Subject of another Schema is
[`relation-target-schema-mismatch`](../api/validation-codes.md#relation-target-schema-mismatch), an error that can block
the save ([ADR 26](026-validation-severity-levels.md)).

### 3. Missing targets are red links

A Relation may point to a Subject that does not exist, as a wiki link may point to a page nobody has written. Imports
rely on this to create Subjects that point to each other
([#1100](https://github.com/ProfessionalWiki/NeoWiki/issues/1100)). The server reports
[`relation-target-not-found`](../api/validation-codes.md#relation-target-not-found) as a warning, and the UI shows a red
link that offers to create the Subject ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)). Relations to
Dependent Subjects are the exception ([ADR 35](035-dependent-subjects.md)).

### 4. A relation Property Definition has one name

A relation Property Definition's `relation` field is removed: graph edges and RDF predicates take the property name, as
other properties' RDF predicates already do, so a link has the same name everywhere
([#1553](https://github.com/ProfessionalWiki/NeoWiki/issues/1553)).

### 5. Subjects on one page are not related automatically

Subjects that share a page are related only through Relations, as Subjects on different pages are
([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)).

## Consequences

- Qualification requires additional Subjects.
- NeoWiki's data model remains simple.
- A relation Property Definition can accept Subjects of several Schemas, such as Person and Group; a set used by many
  Property Definitions is repeated on each ([#1627](https://github.com/ProfessionalWiki/NeoWiki/issues/1627)).
- Cypher queries match Relations by property name, such as `` `Birth place` ``, not by verb phrases such as `BORN_IN`.
- Renaming a relation Property Definition renames its graph edges and RDF predicates, as renaming any other Property
  Definition already does ([ADR 17](017-names-as-identifiers.md)).
- The Schema and Subject formats change incompatibly. NeoWiki is not in production, so that is acceptable.

## Out of Scope

- Targets narrower than a Schema, such as only the Persons who are painters
- Relations to any Subject regardless of its Schema
- Schema hierarchies, such as Person and Group below an Actor Schema
  ([#1627](https://github.com/ProfessionalWiki/NeoWiki/issues/1627))
- Cardinalities other than one or many
- "No value" and "unknown value" markers ([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937))
- Showing referencing Subjects on pages and Views ([#1528](https://github.com/ProfessionalWiki/NeoWiki/issues/1528)),
  and adding a referencing Subject from its target's editor
  ([#1530](https://github.com/ProfessionalWiki/NeoWiki/issues/1530))

## Related

- [ADR 7: Multiple Subjects Per Page](007-multiple-subjects-per-page.md)
- [ADR 10: Add GUIDs to Relations](010-add-guids-to-relations.md)
- [ADR 35: Dependent Subjects](035-dependent-subjects.md)
- [Qualifiers and References](../qualifiers-and-references.md)
