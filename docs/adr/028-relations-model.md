# Relations Model

Date: 2026-07-21

Status: Draft, revised 2026-09-24

## Context

Relations — the Statement values that point one Subject at another — accumulated open design questions, collected in
the Relations epic ([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)). An integrity pass landed first
([#1080](https://github.com/ProfessionalWiki/NeoWiki/pull/1080) to
[#1084](https://github.com/ProfessionalWiki/NeoWiki/pull/1084)). [ADR 7](007-multiple-subjects-per-page.md) left an
automatic relation between a page's Subjects open and [ADR 10](010-add-guids-to-relations.md) named edge properties as
roadmap; this ADR closes both.

Since the July draft, partners modelling CIDOC-CRM data built a dozen main Schemas and two dozen small ones — names,
identifiers, births, dimensions — hung off the main ones by relations, and testers modelling their own data asked for
a source on a date and for helper Subjects that do not surface as things of their own. The seventh decision is the
answer to both.

## Decision

### Structure beyond a flat Statement is a Subject, not an edge property

A Relation is `{id, target}`. The `properties` map on Relations is removed from the model, the serializations, the
graph edges and the native RDF ([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)).

A value that needs context — a date with its source and status, a name with its type and language, an attendance with
its year — becomes its own Subject: the property that held the value becomes a relation to a dependent Schema
(decision 7) holding the value and its context, validated and shown like any other data
([Qualifiers and References](../qualifiers-and-references.md)). Edge properties were a second path to part of that — a
qualified relation, never a qualified literal — scalar-only, without a Schema or an editor, dropped by ontology
mappings, and lost on re-save through the editor. One mechanism with Schemas, not a schema-less qualifier bag
([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)).

### Keep per-relation IDs

Per-relation IDs stay, and several Relations from one property to one target remain legal: the ID, not the pair,
distinguishes them. The ID gives an edge a stable identity across edits and anchors the native-RDF reification node
and the node IRIs an ontology mapping mints. IDs are random and unique by construction
([ADR 14](014-improved-id-format.md)); the graph does not enforce it
([#351](https://github.com/ProfessionalWiki/NeoWiki/issues/351)).

### Constrain targets to one or more Schemas

A relation property's `targetSchema` widens to a list, and a target Subject must use one of them
([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)). This covers the polymorphic cases — a creator that
is a person, a collective or a studio; a place that is a city, a province or a country — with no schema inheritance.

Not taken: subclass-based targets, where a relation accepts a supertype's subtypes. That needs an inheritance system
nothing else calls for. The list's cost is bounded: a new kind of target is added to every property that accepts the
kind, and should that become a burden the remedy is a named target list shared by properties, not inheritance.
Unconstrained targets ("any Subject") stay out until a concrete need arrives.

### Missing targets are red links

A Relation may point at a Subject that does not exist yet, as a wiki link may point at an unwritten page; creating
interlinked Subjects in a batch depends on it, with pre-minted IDs
([#1100](https://github.com/ProfessionalWiki/NeoWiki/issues/1100)). The server reports
[`relation-target-not-found`](../api/validation-codes.md#relation-target-not-found) as a warning and
[`relation-target-schema-mismatch`](../api/validation-codes.md#relation-target-schema-mismatch) as an error that can
block the save ([ADR 26](026-validation-severity-levels.md)); the graph keeps a stub node for the absent target. The
UI renders a red link with a create affordance ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)),
not an error.

### Same-page relationships are schema-defined

No Relation is created automatically between Subjects that share a page: sharing a page is storage, not a
relationship, and a Subject relates to the page's Main Subject only through a relation property in a Schema
([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)). The Main Subject designation stays: it anchors the
automatic display and says which Subject the page is about.

### Name a relation once, on the property

The `relation` attribute on a relation property is removed from the schema format; the graph edge type and the
native-RDF predicate take the property name. Two names made users define one concept twice and keyed the native
projection and ontology mappings on different names. Cypher edge types then read as property names ("Birth place")
rather than verb phrases ("Born in"). The schema editor already takes the property name for both
([PR 1494](https://github.com/ProfessionalWiki/NeoWiki/pull/1494)); the stored model and the projections follow.

### A Schema declares whether its Subjects are dependent

A Schema is standalone unless it declares its Subjects dependent. A dependent Subject — a name, an identifier, a
birth, a sourced date — is part of exactly one host: the Subject whose relation statement holds it. It is entered and
shown inside the host, stored on the host's page in both wiki modes
([ADR 33](033-page-first-and-subject-first-wikis.md)), moved and deleted with the host, and dropped on saving the
page when no standalone Subject on it reaches it. It is not a search hit or a picker candidate, and needs no label;
other Subjects may point at it, and a missing one is a red link like any other. A standalone Subject is what the wiki
is about: picked by type-ahead, created as a stub when missing, linked to rather than shown inline, and placed by the
wiki mode. A relation property targets dependent or standalone Schemas, never both, checked when the Schema is saved;
a dependent Subject may host dependents of its own.

Structure hangs off its host by a relation on the host. Which way an ontology draws it — a birth that points at the
person — is the mapping's concern ([Mapping Format](../authoring/mapping-format.md)), so editing a Subject's structure
never needs its incoming relations. A relationship with no natural host — a marriage, an exhibition — is a standalone
Subject with participants, or is held by one side as in Wikibase; the other side shows it among its referring Subjects.

Not taken: nesting records inside a Schema, which makes the intermediate node unaddressable and unreusable while
mappings must still synthesize it. Not taken: deriving the kind from where a Subject was created, which cannot tell a
reusable type from a birth.

## Consequences

- Structured and sourced data costs Subjects, not pages; [ADR 29](029-scalability-targets.md)'s targets count Subjects.
- Breaking data-format changes are acceptable: NeoWiki is not in production.
- Decided separately: naming a dependent Subject from its data, a label template amending
  [ADR 31](031-optional-subject-labels.md).
- Out of scope, mapped in [planning/Relations.md](../planning/Relations.md): unconstrained targets, cardinality beyond
  single/multiple, no-value/some-value markers ([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937)), and
  showing referring Subjects on pages and views, and adding a referring Subject from its target's editor
  ([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)).

## Related

- [planning/Relations.md](../planning/Relations.md) — the remaining Relations work, open questions, and forward map.
- [Qualifiers and References](../qualifiers-and-references.md), [Graph Model](../api/graph-model.md),
  [Subject Format](../api/subject-format.md), [Validation Codes](../api/validation-codes.md).
- [ADR 7](007-multiple-subjects-per-page.md), [ADR 10](010-add-guids-to-relations.md),
  [ADR 31](031-optional-subject-labels.md), [ADR 33](033-page-first-and-subject-first-wikis.md).
- [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630) — the Relations epic.
