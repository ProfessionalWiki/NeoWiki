# Dependent Subjects

Date: 2026-10-02

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

A Schema's Subjects are standalone unless the Schema declares them dependent. The declaration is made when the Schema
is created and cannot be changed later; to switch, create a new Schema.

A Dependent Subject is part of exactly one Host Subject, the Subject whose Relation points at it, as a Birth is part
of its Person. 

Each Dependent Subject

- Is referenced by exactly one Relation. Additional Relations to it are refused.
- Can only be referenced by a relation property that targets Schemas with dependent Subjects.
- Anything other Subjects need to point at, such as a Place, is a standalone Subject.
- Is created in the same save that makes its Host Subject point at it. A relation property that targets Schemas with
  dependent Subjects can only point at a Subject on the same page; a save that breaks this is refused, so such a
  Relation never points at a missing Subject.
- Is stored on its Host Subject's page, in page-first and subject-first wikis alike
  ([ADR 33](033-page-first-and-subject-first-wikis.md)).
- Is removed with its Host Subject, or with the Relation to it: any save after which no standalone Subject on the
  page reaches it, directly or through other Dependent Subjects, removes it. Deleting it on its own also removes the
  Relation to it.
- Has no label.
- Can be the Host Subject of further Dependent Subjects.

These rules apply even on wikis with validation enforcement off ([ADR 26](026-validation-severity-levels.md)).

A Property Definition of type Relation can not mix Schemas with Dependent Subjects and Schemas with standalone ones
in its target schemas list.

The Relation is stored on the Host Subject and points at the Dependent Subject: the Person points at its Birth. An
ontology may draw the link the other way. CIDOC-CRM does: a Birth "brought into life" a Person. A
[Mapping](../authoring/mapping-format.md) to such an ontology then uses the inverse property, here "was born", which
runs from the Person to the Birth.

A relationship with no natural Host Subject, such as a marriage or an exhibition, is either a standalone Subject that
points at its participants, or a Dependent Subject of one side that points at the others. In the second case, the
others find it under "Referenced by" on their `Special:Subject` page.

Not taken:

- Nesting records inside a Schema, such as a group of birth fields in the Person Schema. Such a record would have no
  id or IRI of its own, other Schemas could not reuse its definition, and Mappings would still have to create a node
  for it.
- Deriving dependent or standalone from where a Subject was created. A Birth and a Place can both be created from a
  Person's editor, yet other Subjects point at the Place.
- Letting other Subjects point at a Dependent Subject. Their Relations would break when it is removed with its Host
  Subject.

## Consequences

- Editing: the Host Subject's editor shows a Dependent Subject's fields inline instead of a picker. If the relation
  property targets several Schemas, adding a Dependent Subject first asks which one. No picker offers a Dependent
  Subject.
- Display: a Dependent Subject is shown as part of its Host Subject. It is named after its Schema and its Host
  Subject, as in "Birth of Pablo Picasso", not by the bracketed id [ADR 31](031-optional-subject-labels.md) would
  give it.
- A Dependent Subject keeps its own id, IRI and graph node. `Special:Subject` and the Data tab show it and can open it
  for editing on its own, and queries and exports include it.
- Search indexes a Dependent Subject as part of the standalone Subject at the top of its chain of Host Subjects, so a
  search that matches a Birth finds the Person.
- A Dependent Subject is never a Main Subject, and its Schema is not offered where a Subject is created without a Host
  Subject.
- Moving a Subject to another page takes its Dependent Subjects along. A Dependent Subject cannot be moved on its own.
- The schema format gains the declaration, and page saves enforce the rules above.
- Tracked in [#1554](https://github.com/ProfessionalWiki/NeoWiki/issues/1554).

## Related

- [ADR 28: Relations Model](028-relations-model.md) — the relations model this refines.
- [Glossary: Dependent Subject](../glossary.md#dependent-subject), [Schema Format](../api/schema-format.md),
  [ADR 7: Multiple Subjects Per Page](007-multiple-subjects-per-page.md).
