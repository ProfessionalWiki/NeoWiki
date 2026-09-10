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
  `relation-target-not-found`, `relation-target-schema-mismatch`, `single-value-only`
  ([validation codes](../api/validation-codes.md)). Graph uniqueness constraints exist for Subjects; target
  autocomplete is scoped to the current wiki.
- **Editing.** The subject editor edits related Subjects in a tree and creates relation targets in place
  ([#1323](https://github.com/ProfessionalWiki/NeoWiki/pull/1323),
  [#1339](https://github.com/ProfessionalWiki/NeoWiki/pull/1339)). A Subject created in flow lands on the page being
  edited and moves elsewhere keeping its id ([#1356](https://github.com/ProfessionalWiki/NeoWiki/pull/1356)). IDs can
  be pre-minted for interlinked imports ([#1101](https://github.com/ProfessionalWiki/NeoWiki/pull/1101)); single
  Statements are writable over REST ([#1216](https://github.com/ProfessionalWiki/NeoWiki/pull/1216)).
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
6. **Name a relation once, on the property** — the least settled decision. The naming inventory is on
   [#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630); the outcome determines `neo:relationType` and the
   direct RDF predicate.

Ratification gates decisions 1, 3, and 6. Nothing else waits for it.

## Open questions

### Where structure lives

Intermediate nodes — a birth event; a dimension with unit, bounds, and source — can be flat fields that the mapping
assembles into nodes at projection time, or Subjects of their own edited in the tree. Both paths are built; flat puts
the coordination in the mapping, nested puts it in the editor. Open is what to recommend: which path the standard
Schema bundles default to, and how much editor investment the nested path gets
([OntologyMapping.md](OntologyMapping.md)).

### Reaching the Subjects that point here

With CIDOC-CRM-style modelling the meaningful Subjects point *at* the one being edited — a birth event references its
person — and nothing shows or adds them from that side. For display, default-off is decided; open are the
configuration granularity (wiki, Schema, or view) and the inverse labels, which cannot be derived from the forward name
([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)). For editing, open are how a user adds an incoming
relation from the target, and whether a Schema declares which incoming relation types it surfaces. Both need the
relations endpoint ([#1324](https://github.com/ProfessionalWiki/NeoWiki/issues/1324), specified and measured; the graph
store cannot serve it, since edges carry Relation Types that a Schema may reuse), and the where-used view
([#1039](https://github.com/ProfessionalWiki/NeoWiki/issues/1039)) also needs
[#1135](https://github.com/ProfessionalWiki/NeoWiki/issues/1135) fixed.

### Page-scoped vs free-standing Subjects

Page-scoped dependents that live and die with their host page differ from free-standing Subjects merely stored
there, which must outlive the page and whose home should stay knowable. Nothing
marks which is which: deleting a page removes its Subjects (referenced ones survive as stubs), and a Subject can be
moved on its own. Open: whether the distinction needs a mechanism — a Schema-level flag, a per-Subject flag, or
derivation from the Subject's relations — and what deleting a page should then do to each.

### Autocomplete value sourcing

With Property Definitions local to their Schema, where relation-target autocomplete draws candidates beyond
target-Schema filtering is open ([#1122](https://github.com/ProfessionalWiki/NeoWiki/issues/1122)).

### Parked

Unconstrained ("any Subject") targets; cardinality beyond single/multiple; no-value / some-value markers
([#937](https://github.com/ProfessionalWiki/NeoWiki/issues/937)).

## Work

Now:

- Renaming a relation leaves the old edge in Neo4j ([#1135](https://github.com/ProfessionalWiki/NeoWiki/issues/1135));
  blocks the incoming-relation and where-used views.
- Red-link rendering and create affordance for missing targets
  ([#1120](https://github.com/ProfessionalWiki/NeoWiki/issues/1120)).
- The relations endpoint ([#1324](https://github.com/ProfessionalWiki/NeoWiki/issues/1324)), then inverse display
  ([#904](https://github.com/ProfessionalWiki/NeoWiki/issues/904)) and where-used
  ([#1039](https://github.com/ProfessionalWiki/NeoWiki/issues/1039)).
- Tree editor follow-ups: collapse and depth cap ([#1327](https://github.com/ProfessionalWiki/NeoWiki/issues/1327)),
  replacing a target loses the new one ([#1358](https://github.com/ProfessionalWiki/NeoWiki/issues/1358)).
- Relation columns in `{{#cypher}}` result tables ([#809](https://github.com/ProfessionalWiki/NeoWiki/issues/809);
  prior analysis in [LegacyNeoWiki #625](https://github.com/ProfessionalWiki/LegacyNeoWiki/issues/625)).

After the Subject Sources foundation ([#1265](https://github.com/ProfessionalWiki/NeoWiki/pull/1265)) lands:

- Rename the identifiers that still say "child subject"
  ([#1367](https://github.com/ProfessionalWiki/NeoWiki/issues/1367)); BlueSpice needs notice first.
- Cross-Source relation targets: #1265 restricts them to registered Sources; opening them — remote display, graceful
  degradation — follows [Subject Sources](SubjectSources.md).

After ADR 28 is ratified:

- Remove edge properties ([#1119](https://github.com/ProfessionalWiki/NeoWiki/issues/1119)); widen `targetSchema` to a
  list ([#991](https://github.com/ProfessionalWiki/NeoWiki/issues/991)); the decision 6 rename.

Smaller, any time: pre-fill a new Subject's relation to the page's Main Subject (decision 5); relation hover card
([#377](https://github.com/ProfessionalWiki/NeoWiki/issues/377)); target links in the Schema view
([#519](https://github.com/ProfessionalWiki/NeoWiki/issues/519)).

## Related

- ADRs: [028 relations model](../adr/028-relations-model.md),
  [007 multiple subjects per page](../adr/007-multiple-subjects-per-page.md),
  [010 relation IDs](../adr/010-add-guids-to-relations.md), [023 subject sources](../adr/023-subject-sources.md),
  [026 validation severity](../adr/026-validation-severity-levels.md).
- [Qualifiers and References](../qualifiers-and-references.md).
