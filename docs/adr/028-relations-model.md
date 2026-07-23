# Relations Model

Date: 2026-07-21

Status: Draft

Feedback welcome; the decisions are proposed as a set, and ratification (team and partner review) gates implementation.

## Context

Relations — the Statement values that point one Subject at another — accumulated open design questions, collected in
the Relations epic ([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)).

An integrity pass landed first: referenced-but-absent Subjects are kept as stub nodes rather than dropped
([#1080](https://github.com/ProfessionalWiki/NeoWiki/pull/1080)), relation targets are validated server-side
([#1082](https://github.com/ProfessionalWiki/NeoWiki/pull/1082)), Neo4j node-uniqueness constraints are created on
rebuild ([#1083](https://github.com/ProfessionalWiki/NeoWiki/pull/1083)), and target autocomplete is scoped to the
current wiki ([#1084](https://github.com/ProfessionalWiki/NeoWiki/pull/1084)). With integrity in place, this ADR settles
the model questions as one coherent set.

Two earlier decisions left threads this one closes. [ADR 7](007-multiple-subjects-per-page.md) allowed multiple
Subjects per page but left an automatic relation between the Main and Child Subjects open.
[ADR 10](010-add-guids-to-relations.md) gave Relations stable IDs and named edge properties as roadmap.

## Decision

### Qualify with typed Subjects, not edge properties

A Relation is `{id, target}`. The `properties` map on Relations (`RelationProperties`) is removed — from the domain
model, the JSON serializations, the Neo4j edge writes, and the native-RDF qualifier triples.

Qualification and references are already modeled as typed Subjects
([Qualifiers and References](../qualifiers-and-references.md)): a qualified value becomes its own Subject under its own
Schema, validated and rendered like any other data. Edge properties are a second, half-built path to the same end —
scalar-only, with no editing UI, no Lua exposure, dropped by the ontology-mapping projection, and silently lost when a
Subject carrying REST-written edge properties is re-saved through the editor. Finishing them would mean schema
definitions for edge properties, an editor, and Lua and mapping support — duplicating what Subjects already provide.
Schema-less qualifier bags are the Wikibase failure mode named in the epic
([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)); removal is tracked in
[#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119).

### Keep per-relation IDs

Per-relation IDs stay, and multiple Relations of the same type to the same target remain legal — the ID, not the
(type, target) pair, distinguishes them. It gives each edge a stable identity across edits and anchors the native-RDF
reification node and the synthesized-node IRIs the ontology mapping mints. Global relation-ID uniqueness is not
graph-enforceable over an open set of edge types ([#351](https://github.com/ProfessionalWiki/NeoWiki/issues/351)). This
reaffirms [ADR 10](010-add-guids-to-relations.md) minus the edge-property roadmap that the previous decision drops.

### Constrain targets to one or more Schemas

A relation property's target constraint is a list of Schemas: the single `targetSchema` widens to a list
([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)), and a target Subject must use one of them. This
covers the concrete polymorphic cases — an artwork creator that may be a person, collective, or studio; a place that
may be a city, province, or country — with no schema-inheritance machinery.

Not taken: subclass-based target constraints, where a relation targets a supertype and accepts its subtypes. That needs
a schema-inheritance system NeoWiki does not have and nothing else calls for; an explicit list covers the raised cases
directly. Fully-unconstrained targets ("any Subject") stay out until a concrete need arrives.

### Missing targets are red links

A Relation may point at a Subject that does not exist yet. Forward references are wiki-native — a red link to an
unwritten page — and creating interlinked Subjects in a batch depends on them: client-supplied and pre-minted IDs
([#1100](https://github.com/ProfessionalWiki/NeoWiki/issues/1100),
[#1101](https://github.com/ProfessionalWiki/NeoWiki/pull/1101)) let a Subject be created referencing a target ID before
that target is written.

Server-side, [`relation-target-not-found`](../api/validation-codes.md#relation-target-not-found) is a non-blocking
warning and [`relation-target-schema-mismatch`](../api/validation-codes.md#relation-target-schema-mismatch) a blocking
error (both in [#1082](https://github.com/ProfessionalWiki/NeoWiki/pull/1082)); an absent target still exists in the
graph as a stub node ([#1080](https://github.com/ProfessionalWiki/NeoWiki/pull/1080)). The UI direction is red-link
rendering with a create affordance ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)), not error
styling.

### Same-page relationships are schema-defined

No Relation is created automatically between Subjects that share a page. A Child Subject relates to the page's Main
Subject only through an explicit relation property in its Schema. An unstated co-location link would carry no defined
meaning, and the same relationship expressed across pages would then diverge from the same-page shortcut (positions in
[#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)).

The Main Subject designation stays: it anchors the automatic display and the page-topic semantics, and the
page/document-type pattern builds on it ([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)). Pre-filling a
Child Subject's relation to the Main Subject during creation is editing convenience layered on this rule, not a model
relation. This resolves the open question in [ADR 7](007-multiple-subjects-per-page.md).

### Name a relation once, on the property

**Least settled — needs team review before ratification; no other decision depends on it.**

Drop the separate relation-type name (the `relation` attribute on a relation property) and key both the graph edge type
and the native-RDF predicate on the property name. Today a relation property carries two names: the property name and a
relation-type name used as the Neo4j edge label. One name removes a concept users must define and a divergence: the
native RDF projection keys predicates on the relation-type name while ontology mappings key on the property name. It
also halves the rename footgun: renaming either name silently re-types existing edges on the next graph rebuild
([ADR 17](017-names-as-identifiers.md) territory).

Cost: Cypher edge types then read as property names ("Birth place") rather than verb phrases ("Born in"). Demo data and
docs migrate — trivial pre-production.

Not taken (status quo): keep both names for better-reading graph queries, at the cost of the extra concept and the
keying divergence.

## Consequences

- Implementation is gated on ratification. Trackers:
  [#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119) (remove edge properties),
  [#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991) (target-Schema list),
  [#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120) (red links).
- What gets simpler: no schema-for-edge-properties design, no separate qualifier editor, and the RDF reification
  question narrows to relation identity alone (see [planning/Relations.md](../planning/Relations.md)).
- Breaking data-format changes are acceptable: NeoWiki is not in production.
- Out of scope, mapped in [planning/Relations.md](../planning/Relations.md): nested-vs-flat authoring of intermediate
  structures, unconstrained targets, cardinality beyond single/multiple, no-value/some-value markers
  ([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937)), and inverse-display configuration
  ([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)).

## Related

- [planning/Relations.md](../planning/Relations.md) — the remaining Relations work, open questions, and forward map.
- [Qualifiers and References](../qualifiers-and-references.md), [Graph Model](../api/graph-model.md),
  [Subject Format](../api/subject-format.md), [Validation Codes](../api/validation-codes.md).
- [ADR 7](007-multiple-subjects-per-page.md), [ADR 10](010-add-guids-to-relations.md),
  [ADR 17](017-names-as-identifiers.md), [ADR 26](026-validation-severity-levels.md).
- [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630) — the Relations epic.
