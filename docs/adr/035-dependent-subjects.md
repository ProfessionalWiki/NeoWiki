# Dependent Subjects

Date: 2026-09-26

Status: Draft. Amends [ADR 31](031-optional-subject-labels.md) and [ADR 33](033-page-first-and-subject-first-wikis.md).

## Context

Partners modelling CIDOC-CRM data built a dozen main Schemas and two dozen small ones — names, identifiers, births,
dimensions — hung off the main ones by relations. Testers modelling their own data asked for a source on a date and
for helper Subjects that do not surface as things of their own. [ADR 28](028-relations-model.md) makes such structure
a Subject of its own, but nothing said which Subjects are structure of another: the editor rendered a Name like a
Person, the picker offered births, every helper Subject wanted a label, and a Subject created inside another one's
editor landed on whichever page that editor was opened from.

## Decision

A Schema's Subjects are standalone unless it declares them dependent. The declaration is made when the Schema is
created and cannot change; to switch, create a new Schema. A Dependent Subject is part of exactly one Host Subject:
the Subject whose relation statement holds it. A Dependent Subject:

- Can be the Host Subject of further Dependent Subjects.
- Is entered and shown as part of its Host Subject. The relation field shows its fields inline, not a picker; with
  several target Schemas, adding one asks which Schema.
- Is created by the write that makes its Host Subject point at it. A write in which a relation property whose targets
  are dependent points at anything else is refused, so a relation to a Dependent Subject is never missing.
- Is removed by any write after which no standalone Subject on the page reaches it. Deleting it on its own also
  removes the Host Subject's relation to it.
- Lives on its Host Subject's page in both wiki modes ([ADR 33](033-page-first-and-subject-first-wikis.md)) and moves
  with it.
- Is referenced by exactly one Relation, from a property whose targets are dependent. Any other Relation targeting it
  is refused, and no picker offers it. What other Subjects need to point at is standalone.
- Has no label. Its name is its Schema name with its Host Subject's name: "Birth of Pablo Picasso".
- Is indexed for search under the standalone Subject at the top of its host chain, which is the hit.
- Is never a Main Subject, and its Schema is not offered where a Subject is created without a Host Subject.
- Keeps its id, IRI and graph node. `Special:Subject` and the Data tab show it and open it for editing on its own;
  queries and exports include it.

These rules hold whatever the validation enforcement setting.

A relation property's target Schemas all have dependent Subjects or all have standalone ones, never both, checked
when the Schema holding the property is saved.

The stored relation runs from Host Subject to Dependent Subject. Which way an ontology draws it — a birth that points
at the person — is the mapping's choice of predicate ([Mapping Format](../authoring/mapping-format.md)).

A relationship with no natural Host Subject — a marriage, an exhibition — is a standalone Subject with participants,
or is held by one side; the other side sees it among the referencing Subjects `Special:Subject` lists.

Not taken:

- Nesting records inside a Schema: the intermediate node becomes unaddressable, its definition unreusable across
  Schemas, and mappings must still synthesize it.
- Deriving dependent or standalone from where a Subject was created: it cannot tell a Subject others point at from a
  birth.
- References to a Dependent Subject from other Subjects: they would dangle when its Host Subject goes.

## Consequences

- [ADR 31](031-optional-subject-labels.md): Dependent Subjects have no label and no stand-in marker.
- [ADR 33](033-page-first-and-subject-first-wikis.md): the wiki mode decides where a target created inside the editor
  is stored for standalone targets only.
- The schema format gains the declaration, and the page write enforces the rules above. Moving a Subject to another
  page carries its Dependent Subjects along; a Dependent Subject cannot be moved on its own.
- Tracked in [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630).

## Related

- [ADR 28](028-relations-model.md) — the relations model this refines.
- [Glossary: Dependent Subject](../glossary.md#dependent-subject), [Schema Format](../api/schema-format.md),
  [ADR 7](007-multiple-subjects-per-page.md).
