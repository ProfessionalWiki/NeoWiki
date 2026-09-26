# Dependent Subjects

Date: 2026-09-26

Status: Draft. Amends [ADR 31](031-optional-subject-labels.md) and [ADR 33](033-page-first-and-subject-first-wikis.md).

## Context

Partners modelling CIDOC-CRM data built a dozen main Schemas and two dozen small ones — names, identifiers, births,
dimensions — hung off the main ones by relations, and testers modelling their own data asked for a source on a date
and for helper Subjects that do not surface as things of their own. [ADR 28](028-relations-model.md) makes such
structure a Subject of its own, but nothing said which Subjects are structure of another: the editor rendered a Name
like a Person, the picker offered births, every helper Subject wanted a label, and where a Subject created inside
another one's editor was stored followed from the creation path rather than from what it is.

## Decision

A Schema's Subjects are standalone unless it declares them dependent. The declaration is made when the Schema is
created; a save that changes it is refused, and a Schema with the other declaration is a new Schema. A Dependent
Subject — a name, an identifier, a birth, a sourced date — is part of exactly one Host Subject: the Subject whose
relation statement holds it. A Dependent Subject:

- Is entered and shown as part of its Host Subject. The relation field holds its fields, not a picker; with several
  target Schemas, adding one chooses the Schema.
- Is created by the write that makes its Host Subject point at it; a relation property whose targets are dependent
  never points at a Subject the write does not hold. It is removed by any write after which no standalone Subject on
  the page reaches it, and deleting it on its own also removes the Host Subject's relation to it.
- Lives on its Host Subject's page in both wiki modes ([ADR 33](033-page-first-and-subject-first-wikis.md)), moves
  with it and is deleted with it.
- Is referenced by exactly one relation statement, from a property whose targets are dependent. It is not a picker
  candidate, and any other statement targeting it is refused. What other Subjects need to point at is standalone.
- Has no label. Its name is its Schema name with its Host Subject's name: "Birth of Pablo Picasso".
- Has its values indexed under the standalone Subject at the top of its host chain, which is the search hit. It is
  never a Main Subject, and its Schema is not offered where a Subject is created without a Host Subject.
- Keeps its id, IRI and graph node, so `Special:Subject`, the Data tab, queries and exports reach it, and it can be
  edited there on its own.
- Can be the Host Subject of further Dependent Subjects.

These rules hold whatever the validation enforcement setting. A relation property's target Schemas all have
dependent Subjects or all have standalone ones, never both, checked when the Schema holding the property is saved.
Which way an ontology draws the relation — a birth that points at the person — is the mapping's choice of predicate
([Mapping Format](../authoring/mapping-format.md)). A relationship with no natural Host Subject — a marriage, an
exhibition — is a standalone Subject with participants, or is held by one side; the other side sees it among its
referencing Subjects on `Special:Subject`.

Not taken: nesting records inside a Schema, which makes the intermediate node unaddressable and its definition
unreusable across Schemas, while mappings must still synthesize it. Not taken: deriving the kind from where a Subject
was created, which cannot tell a reusable type from a birth. Not taken: references to a Dependent Subject from other
Subjects, which would dangle when its Host Subject goes.

## Consequences

- [ADR 31](031-optional-subject-labels.md): a Dependent Subject has no stand-in marker; its name is derived from a
  Host Subject on the same page, so it never waits on another page's reprojection.
- [ADR 33](033-page-first-and-subject-first-wikis.md): the editor-target row applies to standalone targets.
- The schema format gains the declaration. The page write checks the single referencing statement, the same-write
  rule, the absent label and the Main Subject rule, and removes what no standalone Subject on the page reaches. Move
  carries Dependent Subjects along and does not move one alone.
- Relaxation that breaks nothing should demand appear: declaring an existing Schema dependent while it has no
  Subjects and no relation property targets it, once the subject-page index can tell
  ([ADR 32](032-subject-page-index.md)).
- Tracked in [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630).

## Related

- [ADR 28](028-relations-model.md) — the relations model this refines.
- [Glossary: Dependent Subject](../glossary.md#dependent-subject), [Schema Format](../api/schema-format.md),
  [ADR 7](007-multiple-subjects-per-page.md).
