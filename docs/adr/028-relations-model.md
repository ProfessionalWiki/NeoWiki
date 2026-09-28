# Relations Model

Date: 2026-09-27

Status: Draft

## Context

Relations — the values that point to a Subject — accumulated open design questions, collected in
the Relations epic ([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)).
[ADR 7](007-multiple-subjects-per-page.md) left an
automatic relation between a page's Subjects open, and [ADR 10](010-add-guids-to-relations.md) put edge properties on
the roadmap; this ADR closes both.

## Decision

### 1. Structure beyond a flat Statement is a Subject, not an edge property

A Relation is `{id, target}`. The `properties` map on Relations is removed from the model, the serializations, the
graph edges and the native RDF ([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)).

A value that needs context — a date with its source and status, a name with its type and language — becomes its own
Subject, with a Schema that validates and shows it like any other data
([Qualifiers and References](../qualifiers-and-references.md)). Its Schema declares it a Dependent Subject of the
Subject it describes ([ADR 35](035-dependent-subjects.md)), or standalone where such Subjects have no single host.
Edge properties were a second path to part of this: they could qualify a relation but never a literal, took scalars
only, and had no Schema and no editor.

### 2. Keep per-relation IDs

Several Relations from one property to one target remain legal: the ID, not the pair, distinguishes them. The ID gives
an edge a stable identity across edits and anchors the native-RDF reification node; ontology mappings mint node IRIs
from it.

### 3. Constrain targets to one or more Schemas

A relation property's `targetSchema` widens to a list, and a target Subject must use one of them
([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)); a target of another Schema is
[`relation-target-schema-mismatch`](../api/validation-codes.md#relation-target-schema-mismatch), an error that can
block the save ([ADR 26](026-validation-severity-levels.md)). The list is all dependent or all standalone
([ADR 35](035-dependent-subjects.md)). This covers the polymorphic cases: a creator that is a person, a collective or a
studio.

Not taken: subclass-based targets, where a relation accepts a supertype's subtypes. That needs an inheritance system
nothing else calls for. The list's cost: a new kind of target is added to every property that accepts the kind. Should
that become a burden, a named target list shared by properties is the remedy.

### 4. Missing targets are red links

A Relation may point at a Subject that does not exist yet, as a wiki link may point at an unwritten page; creating
interlinked Subjects in a batch with pre-minted IDs depends on it
([#1100](https://github.com/ProfessionalWiki/NeoWiki/issues/1100)). The server reports
[`relation-target-not-found`](../api/validation-codes.md#relation-target-not-found) as a warning, and the graph keeps
a stub node for the absent target. The UI renders a red link with a create affordance
([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)), not an error. A relation to a Dependent Subject is
the exception: it is never missing ([ADR 35](035-dependent-subjects.md)).

### 5. Same-page relationships are schema-defined

No Relation is created automatically between Subjects that share a page: sharing a page is storage, not a
relationship. A Subject relates to the page's Main Subject only through a relation property in a Schema
([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)). The Main Subject designation stays: it anchors the
automatic display and says which Subject the page is about.

### 6. Name a relation once, on the property

The `relation` attribute on a relation property is removed from the schema format; the graph edge type and the
native-RDF predicate take the property name ([#1553](https://github.com/ProfessionalWiki/NeoWiki/issues/1553)). Two
names made users define one concept twice and keyed the native
projection and ontology mappings on different names. With one name, Cypher edge types read as property names
("Birth place") rather than verb phrases ("Born in").

## Consequences

- Context on a value costs Subjects, not pages, and [ADR 29](029-scalability-targets.md)'s targets are counted in
  Subjects.
- Breaking data-format changes are acceptable: NeoWiki is not in production.
- Out of scope:
  - unconstrained targets ("any Subject") and cardinality beyond single/multiple;
  - no-value/some-value markers ([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937));
  - showing referencing Subjects on pages and views, and adding a referencing Subject from its target's editor
    ([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)).

## Related

- [Qualifiers and References](../qualifiers-and-references.md), [Graph Model](../api/graph-model.md),
  [Subject Format](../api/subject-format.md), [Validation Codes](../api/validation-codes.md).
- [ADR 35: Dependent Subjects](035-dependent-subjects.md) — which Subjects are structure of another.
- [ADR 7: Multiple Subjects Per Page](007-multiple-subjects-per-page.md),
  [ADR 10: Add GUIDs to Relations](010-add-guids-to-relations.md).
