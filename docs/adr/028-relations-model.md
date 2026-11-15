# Relations Model

Date: 2026-10-11

Status: Accepted

## Context

Relations are the values that point to a Subject: Pablo Picasso's "Birth place" points to Málaga. The Relations epic
([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)) collected questions about them that NeoWiki's initial
model left open:

- Can a Relation hold data of its own, such as a source for Picasso's birthplace?
- Can a relation Property Definition list several target Schemas, such as Person and Group for a Creator?
- What happens when a Relation points to a Subject that does not exist?
- Does a relation Property Definition need a second name for its graph edges?
- Are Subjects that share a page related ([ADR 7](007-multiple-subjects-per-page.md))?

## Decision

### 1. We remove property-value pairs from Relations

A Relation is just an ID and a target ([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)). Its
property-value pairs were a second way to add context, only for links, with no Schema and no editor.

Context on a link goes into an intermediate Subject, as context on any other value does
([Qualifiers and References](../qualifiers-and-references.md)). If Picasso's birthplace needs a source, Picasso points
to a Birth that holds the place and the source. The Birth can be a Dependent Subject of Picasso
([ADR 35](035-dependent-subjects.md)).

The ID lets the graph and Mappings keep track of a link across edits ([ADR 10](010-add-guids-to-relations.md)).

### 2. We add support for multiple target Schemas

A relation Property Definition can accept Subjects of several Schemas, such as Person and Group
([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)).

### 3. Missing targets are red links

A Relation may point to a Subject that does not exist, as a wiki link may point to a page nobody has written. Imports
rely on this to create Subjects that point to each other
([#1100](https://github.com/ProfessionalWiki/NeoWiki/issues/1100)). The UI shows a red link that offers to create the
Subject ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)). Relations to Dependent Subjects are the
exception ([ADR 35](035-dependent-subjects.md)).

### 4. A relation Property Definition has one name

The `relation` field is removed: graph edges and RDF predicates take the property name, so a link has the same name
everywhere ([#1553](https://github.com/ProfessionalWiki/NeoWiki/issues/1553)).

### 5. Subjects on one page are not automatically related

Subjects that share a page are related only through Relations, as Subjects on different pages are
([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)).

## Consequences

- Qualification requires additional Subjects.
- NeoWiki's data model remains simple.
- A set of target Schemas used by many relation Property Definitions is repeated on each
  ([#1627](https://github.com/ProfessionalWiki/NeoWiki/issues/1627)).
- Cypher queries match Relations by property name, such as `` `Birth place` ``, not by verb phrases such as `BORN_IN`.
- The Schema and Subject formats change incompatibly. NeoWiki is not in production, so that is acceptable.

## Out of Scope

- Targets narrower than a Schema, such as only the Persons who are painters
- Cardinalities other than one or many
- "No value" and "unknown value" markers ([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937))
- Showing referencing Subjects on pages and Views ([#1528](https://github.com/ProfessionalWiki/NeoWiki/issues/1528)),
  and adding a referencing Subject from its target's editor
  ([#1530](https://github.com/ProfessionalWiki/NeoWiki/issues/1530))

## Alternatives Considered

- **A Schema hierarchy**, such as Person and Group below an Actor Schema that a relation Property Definition targets.
  Not chosen for now: a list covers the cases raised, while a hierarchy raises open questions of its own, tracked in
  [#1627](https://github.com/ProfessionalWiki/NeoWiki/issues/1627).
- **Relations to any Subject**, whatever its Schema. Not chosen: no case calls for it.

## Related

- [ADR 7: Multiple Subjects Per Page](007-multiple-subjects-per-page.md)
- [ADR 10: Add GUIDs to Relations](010-add-guids-to-relations.md)
- [ADR 35: Dependent Subjects](035-dependent-subjects.md)
- [Qualifiers and References](../qualifiers-and-references.md)
