# Relations

Written 2026-07-21 by Jeroen De Dauw with help from Claude Opus 4.8; revised 2026-09-07 with help from Claude Fable 5.1.

Status: Proposal for team and ECHOLOT-partner review. The model in ADR 28 awaits ratification, which gates the work
marked below.

Discussion: the Relations epic, [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630).

The model is proposed in [ADR 28](../adr/028-relations-model.md). This doc is the work map: what exists, what is open,
and what to build next. Concepts are in the [glossary](../glossary.md).

## What exists

A Relation is `{id, target}`: another Subject's id plus a stable edge identity
([subject format](../api/subject-format.md#relations), [graph model](../api/graph-model.md#typed-relations)). A
relation Property Definition carries `relation` (the edge-type name), `targetSchema`, and `multiple`
([schema format](../api/schema-format.md)). An edge-`properties` bag also exists; decision 1 removes it.

- **Integrity.** Referenced-but-absent targets persist as stub nodes. Targets are validated server-side —
  `relation-target-not-found`, `relation-target-schema-mismatch`, `relation-target-unresolvable-source`,
  `single-value-only` ([validation codes](../api/validation-codes.md)). Graph uniqueness constraints exist for
  Subjects; target autocomplete is scoped to the current wiki.
- **Editing.** The subject editor opens related Subjects beside the one being edited and creates relation targets in
  place ([#1323](https://github.com/ProfessionalWiki/NeoWiki/pull/1323),
  [#1339](https://github.com/ProfessionalWiki/NeoWiki/pull/1339)). A Subject created in flow lands on the page being
  edited and moves elsewhere keeping its id ([#1356](https://github.com/ProfessionalWiki/NeoWiki/pull/1356)). IDs can
  be pre-minted for interlinked imports ([#1101](https://github.com/ProfessionalWiki/NeoWiki/pull/1101)); single
  Statements are writable over REST ([#1216](https://github.com/ProfessionalWiki/NeoWiki/pull/1216)).
- **Display.** `Special:Subject` lists the Subjects referencing the one shown
  ([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)). Neo4j names the candidates; each one's Statements
  are then read from the wiki, which supplies the property names the graph cannot — edges carry Relation Types that a
  Schema may reuse — and keeps a stale edge invisible.
- **Projection.** The ontology mapping synthesizes intermediate nodes from flat Subjects at projection time
  ([#1229](https://github.com/ProfessionalWiki/NeoWiki/pull/1229),
  [#1263](https://github.com/ProfessionalWiki/NeoWiki/pull/1263)). The native RDF projection reifies each Relation
  beside its direct triple ([RDF export](../api/rdf-export.md)); the reification shape is decided
  ([NativeRdfProjection.md](NativeRdfProjection.md)), the predicate name follows decision 6.

## Decisions

[ADR 28](../adr/028-relations-model.md) proposes six answers; rationale lives there.

1. **Qualify with typed Subjects, not edge properties** — the edge-`properties` bag is removed
   ([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)).
2. **Keep per-relation IDs** — their uniqueness is not graph-enforceable over dynamic edge types
   ([#351](https://github.com/ProfessionalWiki/NeoWiki/issues/351)).
3. **Constrain targets to one or more Schemas** — `targetSchema` widens to a list
   ([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)).
4. **Missing targets are red links** — a warning, never blocking; the UI follows
   ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)).
5. **Same-page relationships are schema-defined** — no automatic relation between the Subjects on a page; the Main
   Subject designation stays.
6. **Name a relation once, on the property** — the schema editor already does
   ([#1494](https://github.com/ProfessionalWiki/NeoWiki/pull/1494)); the stored model, `neo:relationType` and the
   direct RDF predicate follow.
7. **A Schema declares whether its Subjects are dependent** — structure lives in Dependent Subjects on their Host
   Subject's page, in both wiki modes.

Ratification gates decisions 1, 3, 6 and 7. Nothing else waits for it.

## Open questions

### Reaching the Subjects that point here

A Subject's structure is reached from the Subject itself (decision 7); what remains are the standalone Subjects that
point at it — a person's compositions — and nothing adds them from that side. Display ships on `Special:Subject`,
always on; it loses a referrer whose projection lagged or failed, which no wiki-side verification can recover. For
pages and views, where default-off is decided, open are the configuration granularity (wiki, Schema, or view) and the
inverse labels, which cannot be derived from the forward name
([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)). For editing, open are how a user adds an incoming
relation from the target, and whether a Schema declares which incoming relation types it surfaces. The where-used
view ([#1039](https://github.com/ProfessionalWiki/NeoWiki/issues/1039)) needs
[#1135](https://github.com/ProfessionalWiki/NeoWiki/issues/1135) fixed.

### Autocomplete value sourcing

With Property Definitions local to their Schema, where relation-target autocomplete draws candidates beyond
target-Schema filtering is open ([#1122](https://github.com/ProfessionalWiki/NeoWiki/issues/1122)).

### Parked

Unconstrained ("any Subject") targets; cardinality beyond single/multiple; no-value / some-value markers
([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937)).

## Work

Now:

- Renaming a relation leaves the old edge in Neo4j ([#1135](https://github.com/ProfessionalWiki/NeoWiki/issues/1135));
  blocks the where-used view and misleads direct graph queries.
- Red-link rendering and create affordance for missing targets
  ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)).
- Inverse display for pages and views ([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)) and where-used
  ([#1039](https://github.com/ProfessionalWiki/NeoWiki/issues/1039)).
- Relation columns in `{{#cypher}}` result tables ([#809](https://github.com/ProfessionalWiki/NeoWiki/issues/809);
  prior analysis in [LegacyNeoWiki #625](https://github.com/ProfessionalWiki/LegacyNeoWiki/issues/625)).

Gated on sourced-Subject display ([Subject Sources](SubjectSources.md)):

- Cross-Source relation targets. The foundation ([#1265](https://github.com/ProfessionalWiki/NeoWiki/pull/1265))
  restricts them to registered Sources; opening them up needs remote display and graceful degradation.

After ADR 28 is ratified:

- Remove edge properties ([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)); widen `targetSchema` to a
  list ([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)); the decision 6 rename; Dependent Schemas
  (decision 7): schema format, validation, placement, inline editing and display.

Smaller, any time: pre-fill a new Subject's relation to the page's Main Subject (decision 5); relation hover card
([#377](https://github.com/ProfessionalWiki/NeoWiki/issues/377)); target links in the Schema view
([#519](https://github.com/ProfessionalWiki/NeoWiki/issues/519)).

## Related

- ADRs: [028 relations model](../adr/028-relations-model.md),
  [007 multiple subjects per page](../adr/007-multiple-subjects-per-page.md),
  [010 relation IDs](../adr/010-add-guids-to-relations.md), [023 subject sources](../adr/023-subject-sources.md),
  [026 validation severity](../adr/026-validation-severity-levels.md).
- [Qualifiers and References](../qualifiers-and-references.md).
