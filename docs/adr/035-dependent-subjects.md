# Dependent Subjects

Date: 2026-09-26

Status: Draft

## Context

NeoWiki has a simple data model: Subjects contain Statements, each holding one Value. A value that needs context,
such as a date with its source, becomes a Subject of its own, referenced via a Relation from the Subject it describes
([Qualifiers and References](../qualifiers-and-references.md)).

Wikis that use CIDOC-CRM as their in-wiki model need many more small Subjects like these: names, identifiers, and
births, each hanging off a bigger Subject such as a Person. One partner's model has 12 Schemas for things like people
and objects, and 24 for such small Subjects.

The data model supports both, but nothing in it says that a Birth is part of its Person. So NeoWiki treats every
Subject as a thing of its own: the Person's editor makes you pick from all Births in the wiki rather than fill in a
date and place, a subject-first wiki gives each new Birth a page of its own, and deleting a Person leaves its Births
behind. NeoWiki needs to know which Subjects are part of another.

## Decision

A Schema's Subjects are standalone unless it declares them dependent. The declaration is made when the Schema is
created and cannot change; to switch, create a new Schema.

A Dependent Subject is part of exactly one Host Subject, the Subject whose relation statement holds it:

- It is referenced by exactly one Relation, from a property whose targets are dependent. Any other Relation targeting
  it is refused. What other Subjects need to point at is standalone.
- It is created by the write that makes its Host Subject point at it. A write in which such a relation points at
  anything else is refused, so a relation to a Dependent Subject is never missing.
- It lives on its Host Subject's page in both wiki modes ([ADR 33](033-page-first-and-subject-first-wikis.md)). Any
  write after which no standalone Subject on the page reaches it removes it, and deleting it on its own also removes
  the Host Subject's relation to it.
- It has no label.
- It can be the Host Subject of further Dependent Subjects.

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

- Editing: the relation field shows the Dependent Subject's fields inline, not a picker; with several target Schemas,
  adding one asks which Schema. No picker offers a Dependent Subject.
- Display: a Dependent Subject is shown as part of its Host Subject, named by its Schema name with its Host Subject's
  name — "Birth of Pablo Picasso" — with no stand-in marker ([ADR 31](031-optional-subject-labels.md)).
- It keeps its id, IRI and graph node. `Special:Subject` and the Data tab show it and open it for editing on its
  own; queries and exports include it.
- Search indexes it under the standalone Subject at the top of its host chain, which is the hit.
- It is never a Main Subject, and its Schema is not offered where a Subject is created without a Host Subject.
- Moving a Subject to another page carries its Dependent Subjects along; a Dependent Subject cannot be moved on its
  own.
- The schema format gains the declaration, and the page write enforces the rules above.
- Tracked in [#1554](https://github.com/ProfessionalWiki/NeoWiki/issues/1554).

## Related

- [ADR 28: Relations Model](028-relations-model.md) — the relations model this refines.
- [Glossary: Dependent Subject](../glossary.md#dependent-subject), [Schema Format](../api/schema-format.md),
  [ADR 7: Multiple Subjects Per Page](007-multiple-subjects-per-page.md).
