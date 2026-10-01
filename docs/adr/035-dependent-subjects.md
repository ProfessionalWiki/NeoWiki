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

Each Dependent Subject:

- Is referenced by exactly one Relation. Additional Relations to it are refused.
- Can only be referenced by a relation property that targets Schemas with Dependent Subjects.
- Is created in the same save that makes its Host Subject point at it. A relation property that targets Schemas with
  Dependent Subjects can only point at a Subject on the same page; a save that breaks this is refused, so such a
  Relation never points at a missing Subject.
- Is stored on its Host Subject's page, in page-first and subject-first wikis alike
  ([ADR 33](033-page-first-and-subject-first-wikis.md)).
- Is removed with its Host Subject, or with the Relation to it: any save after which no standalone Subject on the
  page reaches it, directly or through other Dependent Subjects, removes it. Deleting it on its own also removes the
  Relation to it.
- Has no label.
- Is never a page's Main Subject.
- Can be the Host Subject of further Dependent Subjects.

These rules apply even on wikis with validation enforcement off ([ADR 26](026-validation-severity-levels.md)).

Anything other Subjects need to point at, such as a Place, is a standalone Subject.

A Property Definition of type Relation cannot mix Schemas with Dependent Subjects and Schemas with standalone ones in
its list of target Schemas ([ADR 28](028-relations-model.md)).

The Relation is stored on the Host Subject and points at the Dependent Subject: the Person points at its Birth. An
ontology may draw the link the other way. CIDOC-CRM does: a Birth "brought into life" a Person. A
[Mapping](../authoring/mapping-format.md) to such an ontology then uses the inverse property, here "was born", which
runs from the Person to the Birth.

A relationship with no natural Host Subject, such as a marriage or an exhibition, is either a standalone Subject that
points at its participants, or a Dependent Subject of one side that points at the others. In the second case, the
others find it under "Referenced by" on their `Special:Subject` page.

## Consequences

- Editing: the Host Subject's editor shows a Dependent Subject's fields inline instead of a picker. If the relation
  property targets several Schemas, adding a Dependent Subject first asks which one.
- Display: a Dependent Subject is shown as part of its Host Subject. It is named after its Schema and its Host
  Subject, as in "Birth of Pablo Picasso", not by the bracketed id [ADR 31](031-optional-subject-labels.md) would
  give it.
- A Dependent Subject keeps its own id, IRI and graph node. `Special:Subject` and the Data tab show it and can open it
  for editing on its own, and queries and exports include it.
- Finding: search shows a match in a Dependent Subject as a hit on the standalone Subject at the top of its chain of
  Host Subjects, so a search that matches a Birth finds the Person. Fields for choosing an existing Subject, such as the
  search field on `Special:Subject`, never offer a Dependent Subject.
- Creation: Schemas with Dependent Subjects are not offered where a Subject is created without a Host Subject.
- Moving a Subject to another page takes its Dependent Subjects along. A Dependent Subject cannot be moved on its own.
- Tracked in [#1554](https://github.com/ProfessionalWiki/NeoWiki/issues/1554).

## Alternatives Considered

### Other designs

- **User-defined records**: a property whose value is a group of fields defined in its Schema, like Semantic MediaWiki's
  Record type. A Birth property on the Person Schema would hold a date and a place. Such a value has no ID or IRI, so it
  cannot be opened or queried on its own. Other Schemas can't reuse its fields, while one Name Schema can serve both
  Person and Organization. And a Mapping to CIDOC-CRM would still have to create a node for the Birth. Compound values
  with a fixed shape, such as monolingual text ([ADR 34](034-monolingual-text-value-type.md)), are Value Types, not
  records.
- **A per-property inline flag**: a relation property marked inline would show its targets' fields in the editor, while
  the targets stay ordinary Subjects. That changes only editing. Where a Subject is stored, when it goes, whether it
  needs a label and whether search offers it are about the Subject, not about one property that points at it.
- **Inferring dependence from where a Subject is created**: a Birth and a Place can both be created from a Person's
  editor, but only the Birth is part of the Person; other Subjects point at the Place.

### Other rules

- **Letting a Schema change its declaration later**: turning existing standalone Subjects into Dependent Subjects would
  first require checking that each has exactly one referencing Relation. That needs where-used
  ([#1039](https://github.com/ProfessionalWiki/NeoWiki/issues/1039)), a scan of every page, or a Graph Store.
- **Letting other Subjects point at a Dependent Subject**: those Relations would turn into red links when it is removed
  with its Host Subject. The UI could only create such a Relation by pasting an id. And the strict rule can be relaxed
  later without invalidating stored data, while a permissive one cannot be tightened. The cost: a Subject cannot be both
  edited inline and pointed at by others. A wiki that edits rooms inside a building's form, with events that point at
  rooms, has to make the rooms standalone.
- **Refusing to delete a Dependent Subject on its own**: deleting a Subject through the API is never refused.
- **Storing the Relation on the Dependent Subject**, pointing at its Host Subject as CIDOC-CRM draws a birth: the
  Person's editor would then have to edit Relations that point at the Person, which it cannot do. Stored on the Person,
  the Relation lets its editor reach the Birth through the Person's own Statements.

## Related

- [ADR 28: Relations Model](028-relations-model.md)
- [ADR 7: Multiple Subjects Per Page](007-multiple-subjects-per-page.md)
- [Glossary: Dependent Subject](../glossary.md#dependent-subject)
- [Schema Format](../api/schema-format.md)
