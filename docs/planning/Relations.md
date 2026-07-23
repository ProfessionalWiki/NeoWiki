# Relations

Written 2026-07-21 by Jeroen De Dauw with help from Claude Opus 4.8.

Status: Proposal for team and ECHOLOT-partner review. We are after disagreement and additions: if an open question
below is framed wrong, or work is missing, say so.

Discussion: _to be opened; a maintainer will create the GitHub Discussion thread and link it here._

This is a proposal-stage map of the remaining work on NeoWiki Relations. The model itself — what a Relation is and the
rules governing it — is proposed in [ADR 28: Relations Model](../adr/028-relations-model.md); this doc carries the
landed groundwork, the open questions, and the forward map. For the concepts
(Subject, Statement, Relation, Schema) see the [glossary](../glossary.md); for modeling qualifiers and references, see
[Qualifiers and References](../qualifiers-and-references.md).

## Where Relations stand

A Relation is the value of a `relation`-typed Statement: `{id, target}`, where `target` is another Subject's id and
`id` gives the edge a stable identity ([subject format](../api/subject-format.md#relation-relation),
[graph model](../api/graph-model.md#typed-relations)). At the Schema level a relation Property Definition carries
`relation` (the graph edge-type name), `targetSchema` (the Schema the target must use), and `multiple` (whether more
than one target is allowed). A per-relation edge-`properties` bag also exists today; model decision 1 below removes it.

An integrity pass landed ahead of the model decisions: referenced-but-absent Subjects are kept as stub nodes rather
than dropped ([#1080](https://github.com/ProfessionalWiki/NeoWiki/pull/1080)); relation targets are validated
server-side — a `relation-target-not-found` warning, a `relation-target-schema-mismatch` error, and `single-value-only`
([#1082](https://github.com/ProfessionalWiki/NeoWiki/pull/1082)); Neo4j uniqueness constraints are created on rebuild
([#1083](https://github.com/ProfessionalWiki/NeoWiki/pull/1083)); target autocomplete is scoped to the current wiki
([#1084](https://github.com/ProfessionalWiki/NeoWiki/pull/1084)); and the `expand=relations` REST response shape is
documented ([#1085](https://github.com/ProfessionalWiki/NeoWiki/pull/1085)). Batch ID minting also landed — pre-minted
and client-supplied Subject IDs on create ([#1101](https://github.com/ProfessionalWiki/NeoWiki/pull/1101)) — so
interlinked imports can wire relations before their targets exist.

## Model decisions

[ADR 28](../adr/028-relations-model.md) settles six questions; rationale lives there.

1. **Qualify with typed Subjects, not edge properties** — the edge-`properties` bag is removed
   ([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)).
2. **Keep per-relation IDs** — multiple same-type edges to one target stay legal and individually addressable
   ([#351](https://github.com/ProfessionalWiki/NeoWiki/issues/351)).
3. **Constrain targets to one or more Schemas** — `targetSchema` widens to a list
   ([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)).
4. **Missing targets are red links** — legitimate forward references; a warning, not an error
   ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)).
5. **Same-page relationships are schema-defined** — no automatic Main/Child relation
   ([#959](https://github.com/ProfessionalWiki/NeoWiki/issues/959)).
6. **Name a relation once, on the property** — proposed, least settled and the item most open to feedback: key edges
   and predicates on the property name, dropping the separate relation-type name (the status-quo case for keeping both
   names is recorded in the epic, [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)).

## Open questions

### Nested vs flat authoring of intermediate structures

CIDOC-CRM-style intermediate nodes — a birth event; a dimension with unit, upper and lower bound, source — can be
expressed today as Child Subjects or a separately linked Subject. The open question is how they are *authored*:

- **Route A — flat schemas, mapping synthesizes.** Schemas stay flat; the ontology mapping assembles the intermediate
  node at RDF-projection time. Cost: the mapping must coordinate several flat fields into one shared node.
- **Route B — Child Subjects as the native representation.** The nesting is real Subjects, with inline editing UX that
  projects the structure down into a form. Cost: editor complexity and lazy-loading performance.

Public positions lean toward Route B ([discussion #996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996),
[#999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999)). The decision follows the neutral-person → EDM
end-to-end projection exercise ([Person to EDM](../examples/person-to-edm.md)) rather than preceding it.

### Relations in RDF

The native projection reifies a Relation: a direct triple plus a `neo:Relation` node preserving the relation's identity
([RDF export](../rdf/rdf-export.md),
[Qualifiers and References](../qualifiers-and-references.md#in-the-graph-and-in-rdf)). With edge properties removed
(model decision 1), the reification node's only remaining job is stable relation identity. Open: whether reification
meets the LOD community's expectations, and whether to plan an RDF-star migration
([#999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999)).

### Inverse display configuration

Default-off is decided — relations are stored one-directionally, and the inverse is a display concern. Open: the
configuration granularity (wiki, schema, or view) and the inverse labels, which cannot be derived from the forward name
([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)).

### Autocomplete value sourcing

With Property Definitions local to their Schema, where relation-target autocomplete draws candidates beyond
target-Schema filtering is open ([#1122](https://github.com/ProfessionalWiki/NeoWiki/issues/1122)).

### Parked

Unconstrained ("any Subject") targets; cardinality beyond single/multiple; no-value / some-value markers
([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937)).

## Forward work

### Editing

- In-flow creation and editing of relation targets, and where in-flow-created Subjects live
  ([#971](https://github.com/ProfessionalWiki/NeoWiki/issues/971)).
- Red-link create affordance for missing targets ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)).
- Main-Subject prefill as editing sugar (model decision 5).

### Display

- Incoming / inverse relations ([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)).
- A built-in incoming-relations section — the smallest non-Lua path to reach related and Child Subjects.
- Relation hover card ([#377](https://github.com/ProfessionalWiki/NeoWiki/issues/377)).
- Target links in the Schema view ([#519](https://github.com/ProfessionalWiki/NeoWiki/issues/519)).
- Where-used over incoming relations ([#1039](https://github.com/ProfessionalWiki/NeoWiki/issues/1039)).

### APIs and import

- Statement-level write API ([#591](https://github.com/ProfessionalWiki/NeoWiki/issues/591)).

### Query rendering

- Relation columns in `{{#cypher}}` result tables ([#809](https://github.com/ProfessionalWiki/NeoWiki/issues/809);
  prior analysis [LegacyNeoWiki #625](https://github.com/ProfessionalWiki/LegacyNeoWiki/issues/625)).

### Cross-Source targets

- v1 restricts relation targets to resolvable Sources
  ([#1043](https://github.com/ProfessionalWiki/NeoWiki/pull/1043)); opening cross-Source relations — remote display,
  graceful degradation — follows the [Subject Sources](SubjectSources.md) track
  ([#993](https://github.com/ProfessionalWiki/NeoWiki/issues/993), [ADR 23](../adr/023-subject-sources.md)).

## Track coordination

Relations intersect three tracks in flight. [Subject Sources](SubjectSources.md)
([ADR 23](../adr/023-subject-sources.md)) makes `(source, localId)` the reference form a relation target resolves
through. [Ontology mapping](OntologyMapping.md) and the QLever projection synthesize intermediate nodes from relations
in their structural tier — the nested-vs-flat question above.
[Validation severity levels](../adr/026-validation-severity-levels.md) (ADR 26) govern the error and warning tiers of
the new relation codes.

## Related

- ADRs: [028 relations model](../adr/028-relations-model.md) (the decisions),
  [007 multiple subjects per page](../adr/007-multiple-subjects-per-page.md),
  [010 relation IDs](../adr/010-add-guids-to-relations.md), [023 subject sources](../adr/023-subject-sources.md),
  [026 validation severity](../adr/026-validation-severity-levels.md).
- Reference: [Qualifiers and References](../qualifiers-and-references.md), [Graph Model](../api/graph-model.md),
  [Subject Format](../api/subject-format.md), [Validation Codes](../api/validation-codes.md).
- Epic: [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630).
