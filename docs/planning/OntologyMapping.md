# Ontology Mapping

A design document whose implementation has since shipped (see the as-built note below). The open questions at the end
still stand, and the decisions still need consolidating into an ADR.

Started 2026-06-24 by Jeroen De Dauw with help from Claude Opus 4.8.

Status: Implemented; open questions still under discussion with ECHOLOT partners (T2.3 and T3.x).

Discussion: [#996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996).

> **As-built (2026-08).** Mappings are pages in a `Mapping:` namespace — one page per target ontology, the
> page title being the target name ([#1065](https://github.com/ProfessionalWiki/NeoWiki/discussions/1065)) —
> and an ontology projection is selectable alongside the native one on the RDF export endpoint and `DumpRdf`.
> The near-1:1 term-substitution tier shipped first (2026-07); the **structural tier** followed as an optional
> addition to the same format: node synthesis with deterministic IRIs, and contraction as source-side
> contributions. See the [Ontology Mapping reference](../rdf/ontology-mapping.md) and the worked
> [Person → EDM example](../rdf/person-to-edm.md). The rule format is NeoWiki-native, so the
> mapping-formalism question (Q1, [#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995)) stays open
> at the authoring level; the stored format is provisional. The questions it answered are listed under
> [Decided](#decided).

## Still open (2026-08)

- [Q1 — Authoring formalism](#q1-authoring-formalism): should LinkML or a Platka export sit in front of the shipped
  JSON?
- [Q3 — Platka boundary](#q3-platka-boundary): division of labour, format, and interface with takin's library.
- [Q4 — Import](#q4-import): how far to shape the format for the import direction.
- [Q5 — Validation via SHACL](#q5-validation-via-shacl): which shapes, and where findings surface for curators.
- [Q7 — Packaging and distribution](#q7-packaging-and-distribution): bundles across wikis and a farm.
- [Q8 — Multilinguality](#q8-multilinguality): the cohesive pass across NeoWiki's surfaces.
- [Q10 — Flat vs nested native modelling](#q10-flat-vs-nested-native-modelling): the modelling fork, to be settled
  outside this document.

Everything else the document asked has been answered by the implementation — see [Decided](#decided).

## Summary

NeoWiki defines its own native Schemas ([ADR 6](../adr/006-schemas.md)). For RDF and SPARQL it projects that data into
RDF; the native projection uses NeoWiki-native predicates ([NativeRdfProjection.md](NativeRdfProjection.md)). For
interoperability — the core of ECHOLOT (SO2, T3.1, T3.2) — the data must instead be available in established
cultural-heritage ontologies such as CIDOC-CRM, EDM, HDTO, and BIBFRAME.

An **ontology mapping** projects native NeoWiki data **directly into a target ontology**: that ontology RDF is what a
configured triple store holds and what SPARQL queries run against. The native projection stays the default — used
when no mapping is configured — and the lossless-export target; ontology mappings define the others.

Mappings are first-class, authorable, installable objects, kept separate from Schemas so the native data model is not
deformed to fit an ontology. A mapping defines the **correspondence** between NeoWiki's model and an ontology, and is
intended to drive both export (native → ontology) and, later, import (ontology → native).

The central difficulty is that good ontology mapping is **not predicate renaming**: CIDOC-CRM and similar ontologies
are event-centric and require *synthesizing intermediate nodes that do not exist in NeoWiki's data*. The mapping is a
graph-to-graph transformation governed by reusable patterns.

> Terminology note: ECHOLOT partners often say "RDF mapping" for ontology alignment — that is *this* document. The
> native vocabulary and the infrastructure shared by all projections (IRIs, named graphs, sync) are specified in the
> [RDF Export reference](../rdf/rdf-export.md).

## Projections, not layers

A triple store holds **one or more projections** of the wiki's data, and SPARQL against it is written in the vocabulary
of whichever projection a query targets. Each projection writes its own family of named graphs
(`$base/graph/{projection}/page/{id}`, [#1053](https://github.com/ProfessionalWiki/NeoWiki/issues/1053),
[discussion #996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996)), so projections in a shared store never
overwrite one another.

- The **native projection** ([NativeRdfProjection.md](NativeRdfProjection.md)) is the default target. A wiki with no
  ontology mapping configured still gets RDF and SPARQL, in NeoWiki-native terms.
- An **ontology mapping** defines an alternative target (CIDOC-CRM, EDM, …). A store can hold that projection — on its
  own or alongside others — and SPARQL against it is written in that ontology.
- Projections are **pluggable per store**, consistent with [ADR 19](../adr/019-graph-database-architecture.md) (each
  backend owns its mapping). As built, each entry in the [#586](https://github.com/ProfessionalWiki/NeoWiki/issues/586)
  store config carries an endpoint and one projection, so several projections in one store means several entries
  sharing an endpoint ([config](../operations/installation.md#several-projections-in-one-store)). Running separate
  stores with different projection sets stays a **deployment choice** — hard isolation, independent scaling — not a
  requirement.

Subject IRIs are identical across projections (only the vocabulary and the graph differ), so queries in a shared store
join across sibling projections with no `owl:sameAs` machinery. Whether such a query must name the graphs depends on
the store's default-dataset semantics: QLever always queries the union of all graphs, most other stores offer a
union-default-graph option, and a spec-strict endpoint needs `FROM`/`GRAPH`. That trait belongs to the per-page
named-graph design as a whole, not to holding several projections in one store.

Why the native projection stays a first-class target rather than always mapping to an ontology: standard ontologies are
lossy and opinionated, so mapping into one can drop detail that has no place in that ontology. The triple store is a
rebuildable projection, not the source of truth (which stays in MediaWiki revision slots,
[ADR 4](../adr/004-use-dedicated-slot.md)), so lossy ontology projections are fine for querying; the native projection
covers the case where nothing may be lost (e.g. a complete RDF export).

## Why mapping is separate from Schemas

Settled across our planning:

- **Local Schemas stay canonical.** [GlobalProperties.md](GlobalProperties.md) rejected global, ontology-shaped
  properties: the same interoperability is achievable with a mapping, without forcing the data model to mirror external
  ontologies.
- **Map at projection time, not at modelling time.** Schemas are defined for the wiki's own needs; the mapping to an
  ontology is applied when RDF is produced. Editing, validation, and View-based display keep working against a model
  users understand.
- **Model diversity (ECHOLOT architecture principle).** No single data model is assumed; the system offers
  transformation paths between models. One wiki can target several ontologies (KPI 2.1 targets >6 mapped models), each
  an independent, optional, installable mapping.

## What makes this hard: structural transformation

Targets sit on a spectrum. Flat, property-centric ones (Dublin Core, much of EDM) mostly need term substitution:
`Person.Name` → `foaf:name`. Event-centric ones do not: CIDOC-CRM mediates relationships through events, so a native
`Creator` relation expands to a path through an `E12_Production` node that has **no counterpart in NeoWiki's data**
and must be minted. The mirror case is contraction — where the data carries structure a target does not want, the
mapping collapses it, as a Birth event Subject collapses to `rdaGr2:dateOfBirth` on a flat EDM agent. Both directions
are needed because a wiki serves several targets at once ([sibling projections](#projections-not-layers)) that
disagree about shape, so at least one mismatches whichever style the data is modelled in.

Expressing both in one declarative form is what the mapping format had to solve, and does. The
[Person → EDM example](../rdf/person-to-edm.md) walks a real projection, including the `E67_Birth` synthesis.

### Flat vs nested native modelling (open fork)

Where the structure comes from is itself undecided (2026-06-24 data-modelling call with takin and OEAW). NeoWiki can
already express intermediate nodes as child Subjects linked by Relations; the fork is about what the modelling norm
should be and where the burden sits:

- **(a) Flat native Schemas** (e.g. birthplace directly on Person), with intermediate nodes synthesized at projection
  time by the mapping. The cost lands in this document: the mapping must coordinate several flat fields into shared
  nodes (birthplace and birth date must land on the *same* `E67_Birth` node).
- **(b) Nested structure inside Schemas** (nested arrays / sub-subjects), so the native data already carries the
  intermediate nodes and their mapping collapses toward term substitution. The cost shifts to the schema model and the
  editing UI, which must project the nesting down into an accessible form — what Arches and ResearchSpace do. George
  Bruseker leans (b) if the UI can project down.

The fork is to be settled outside this document — it is a schema-model and editing-UX decision, exercised through the shared
toy model ([Neutral Person to Many Standards](https://docs.google.com/spreadsheets/d/1j2_7j8RCUJrrMsfZaXtqHQOwp9cN9F8HIBtd3pfsToU/edit))
that expresses one person model across several ontologies. The same toy model doubles as the first end-to-end
exercise of this document's approach: implement its neutral person schema in NeoWiki, define a Mapping for it, and
project to EDM first — the near-1:1 tier — proving or disproving the mechanism by doing (proposed at the 2026-07-03
WP2/3/4 call). What matters here is that the formalism needs both directions under **either** route: not every wiki
will model maximally nested (a mapping must handle whatever the native model is), and sibling targets decompose
differently — EDM stays flat where CIDOC-CRM wants events — so no single nesting depth spares all projections.
Route (b) reduces how often synthesis fires and makes contraction fire for the flat targets instead; it does not
remove the requirement (Q10).

## Design principles

1. **Non-destructive.** A mapping never changes native Schemas or stored data; it only defines a projection. A wiki with
   no mapping still has the native projection.
2. **Patterns are reusable and externally curated where possible.** Encoding CIDOC-CRM correctly is specialist work.
   NeoWiki should *bind* local constructs to curated ontology patterns, not re-encode ontology knowledge in its own
   codebase.
3. **Reuse a formalism; do not invent one casually.** Prefer an established mapping/transformation formalism (see open
   questions) over a bespoke NeoWiki DSL, consistent with [ADR 19](../adr/019-graph-database-architecture.md)'s
   "don't reinvent the query layer" stance.
4. **Mappings are first-class wiki objects.** Authorable and versioned like other content (a wiki page and/or an
   API-writable object), not buried in server configuration. Distributable as bundles.
5. **Bidirectional correspondence.** A mapping describes how NeoWiki's model and an ontology correspond, for both export
   and import. Export ships first; import (below) reuses the correspondence but needs additional machinery.

## The shipped format

A Mapping is a JSON page in the `Mapping:` namespace, one per target ontology, declaring per Schema how the Subject,
its properties, its synthesized nodes, and its contributions project. It is specified in the
[Ontology Mapping reference](../rdf/ontology-mapping.md).

## Import

The mapping is the **correspondence** between NeoWiki's model and an ontology, so it is also the basis for importing
ontology RDF into NeoWiki Subjects (T3.2 calls for native RDF/RDF\* import; T2.3 frames interoperability as
bidirectional model-model mappings). Export ships first, but the formalism should be chosen with import in mind.

Import is not export run backwards. A structural mapping is generally **not trivially invertible**: importing CIDOC-CRM
means **recognizing** the `E12_Production` pattern in the incoming graph and **collapsing** it back to a `Creator`
relation — graph pattern-matching on input, not template expansion of output. Import also pulls in adjacent concerns the
export projection does not have:

- **Reconciliation / entity linking** of incoming IRIs to existing Subjects — a WP4 concern (T4.2), not the mapping
  itself.
- **Batch ID-minting** for many interlinked incoming Subjects — shipped
  ([#1100](https://github.com/ProfessionalWiki/NeoWiki/issues/1100)).
- **Target-Schema selection** for incoming data.

So a Mapping should aim to be a bidirectional *definition*, while import and export are separate *executors* and the
import pipeline mechanics live with T4.1. How far to shape the export formalism now for later invertibility is an open
question.

## Validating projections

Ontology projections raise a validation question that native Subject validation
([ADR 21](../adr/021-add-backend-validation.md)) cannot answer: does the projected RDF conform to the target
ontology's expectations? Shapes for the target ontology — e.g. SHACL emitted by the T2.3 library (Q3) — can answer it,
and two distinct failure sources make that worthwhile:

- **Mapping bugs.** A rule produces structurally wrong output for every Subject it matches. Validating the projection
  of a few sample Subjects at mapping-authoring time is effectively unit-testing the mapping.
- **Data gaps.** The mapping is correct, but the data does not meet the target's requirements (e.g. a rights statement
  the target ontology mandates is missing). These are per-Subject curation findings.

Proposed division of labour: shape engines run in external quality tooling (T4.5), not in the wiki's editing path.
NeoWiki's job is to keep projections checkable and findings traceable:

- Export of projected RDF for a given mapping (per page and in bulk), so external validators have input.
- Per-page named graphs ([RDF Export reference](../rdf/rdf-export.md#iri-scheme)) make re-validation incremental:
  only pages whose graphs changed since the last run need re-checking.
- **Traceability requirement.** A validation report references ontology-projection nodes, but the people acting on it
  work in the wiki. Reports must be translatable back to the originating page, Subject, and property. The anchors
  exist by construction — focus node → named graph → page; synthesized-node IRI → Relation ID → Statement; violated
  path → the rule that emits it → the native construct the rule matches — so rules must retain that provenance.

Conformance to a target ontology is a publication and import concern, not an editing concern: findings surface as
reports and worklists (and can feed back into tightening native Schemas or mapping defaults), not as inline errors in
the Subject editor, which stays native-validation-only.

## Scope

**In scope:** defining Mappings (Schema-, Statement-, and Relation-level rules; node synthesis / path expansion;
deterministic IRIs for synthesized nodes); producing an ontology projection for a configured store and for export; the
native projection as the default target; the Mapping as a bidirectional definition.

**Out of scope (separate concerns, cross-referenced):**

- The native projection and the shared projection infrastructure (IRIs, named graphs, sync) —
  [NativeRdfProjection.md](NativeRdfProjection.md) for the rationale, the
  [RDF Export reference](../rdf/rdf-export.md) for what it emits.
- The import *pipeline* mechanics and orchestration — T4.1.
- Reconciliation / entity linking / `owl:sameAs` minting — WP4 (T4.2); mapped IRIs are its input.
- Rich chain-of-production provenance and rights — T2.4 model and a T3.4 plug-in (see [ECHOLOT.md](ECHOLOT.md)).
- Global, ontology-shaped properties — rejected in [GlobalProperties.md](GlobalProperties.md).

## Open questions

These need ECHOLOT partner input, especially from T2.3 (semantic interoperability / ontology patterns) and partners
with deep CIDOC-CRM / EDM / RDF experience. Questions the implementation answered are listed under
[Decided](#decided), keeping their original numbers so a thread that cites "Q6" still resolves.

### Q1: Authoring formalism

The shipped format is a NeoWiki-native declarative JSON, and it is the executable substrate. What stays open is
whether a higher-level formalism should sit in front of it: **LinkML**, whose class/slot mappings to external
vocabularies are established and tooling-rich but of unproven expressiveness for CIDOC-CRM-style structural expansion
([#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995)), or an export from the T2.3 pattern library that
compiles down to it. Independent of the answer: survey existing CH mapping tooling before building more — mature
ontology-mapping frameworks exist in this space, and reusing or aligning with one may beat rebuilding (George,
2026-07-03).

### Q3: Platka boundary

Platka is the T2.3 pattern library (takin). Open: the division of labour between it and NeoWiki, what NeoWiki would
consume from it, in what format, and over what interface. The division we have assumed is that the library owns the
*recipe* — the exact RDF a given alignment must produce — while NeoWiki owns the *binding* of local Schemas onto it
and the *execution* against actual Subjects; to be confirmed with takin
([#996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996)).

### Q4: Import

How far should the export format be shaped now so the same Mapping can drive import later? Where is the boundary
between the Mapping (correspondence), the import executor (pattern recognition), and T4.1/T4.2 (pipeline,
reconciliation)?

### Q5: Validation via SHACL

[Validating projections](#validating-projections) proposes consuming shapes emitted by the T2.3 library to check
ontology projections, with the engines in the T4.5 tooling rather than in NeoWiki. Open: which shapes the library
actually emits and their coverage per ontology; and where findings surface for curators (report pages, a dashboard,
an API the quality component writes back to) — in NeoWiki core or a plug-in. Candidate engine (suggested 2026-07-03):
[rudof](https://github.com/rudof-project/rudof) — Rust, SHACL + ShEx, has an MCP interface, and can validate a QLever
endpoint directly; endpoint-side validation still needs the traceability path above, since the store has no sync-back
to the wiki.

### Q7: Packaging and distribution

Mappings are wiki pages, but bundles are not addressed: how a set such as "CIDOC-CRM for Person / Place / Object" is
packaged, versioned, installed, and shared across wikis and a farm.

### Q8: Multilinguality

A mapping can language-tag the literals it projects, which leaves the harder half open: where multilinguality lives
across NeoWiki as a whole — property labels ([#710](https://github.com/ProfessionalWiki/NeoWiki/issues/710)), select
options ([#726](https://github.com/ProfessionalWiki/NeoWiki/issues/726)), the native projection, and Views. CH data is
heavily multilingual (Basque, the ELB languages), while canonical values used in queries stay language-neutral. A
cohesive pass across surfaces is wanted rather than per-surface answers.

### Q10: Flat vs nested native modelling

The [fork above](#flat-vs-nested-native-modelling-open-fork): should case-study data live in flat Schemas with the
mapping synthesizing intermediate nodes, or in nested Schemas with the editing UI projecting the nesting down?
To be settled through the toy model, outside this document. If (b) wins: what does nesting look like in the schema
format, and how much of the synthesis machinery stops being exercised in practice?

*Update (2026-07): the fork no longer gates the transformation machinery — both directions are needed however it
resolves, since sibling targets disagree about shape and at least one mismatches whichever style the data is
modelled in ("one would need to expand (or contract) on export/import" — George Bruseker,
[#999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999), 2026-07-06). What the fork still steers: build
order, editing-UI investment, and what standard Schema bundles default to.*

## Decided

Answered by the shipped implementation. Numbers are the original question numbers.

- **Q2 — expressiveness for node synthesis.** The format expresses both directions natively: synthesized nodes for
  expansion, contributions for contraction. Neither SHACL Advanced Features nor `CONSTRUCT` became the substrate
  ([reference](../rdf/ontology-mapping.md)).
- **Q6 — one mapping per target vs combined.** One Mapping page per target ontology, holding an entry for every mapped
  Schema ([#1086](https://github.com/ProfessionalWiki/NeoWiki/pull/1086),
  [discussion #1065](https://github.com/ProfessionalWiki/NeoWiki/discussions/1065)).
- **Q7 — where Mappings live.** Pages in the `Mapping:` namespace, listed on `Special:Mappings` and gated by the
  `neowiki-mapping-edit` right ([reference](../rdf/ontology-mapping.md#ontology-mappings-are-wiki-pages)).
- **Q8 — language tags in a mapping.** A mapped property or contribution takes a `lang` tag, applied to the plain-string
  literals it produces ([reference](../rdf/ontology-mapping.md#format-version-1)); the native projection mints none.
- **Q9 — multiple projections per wiki.** A wiki serves several sibling projections at once, selected per request, with
  `native` always the baseline; named graphs are qualified by projection so one store can hold several
  ([#1055](https://github.com/ProfessionalWiki/NeoWiki/pull/1055),
  [config](../operations/installation.md#several-projections-in-one-store)).
- **Identity for synthesized nodes.** Node IRIs are derived deterministically from the data — from the Relation's
  persistent ID where one exists ([ADR 10](../adr/010-add-guids-to-relations.md)), otherwise from the Subject IRI and
  the node key — so re-projecting a page is idempotent
  ([reference](../rdf/ontology-mapping.md#synthesized-node-iris)).

## Related

- Planning: [NativeRdfProjection](NativeRdfProjection.md) (why the native projection is shaped as it is),
  [GlobalProperties](GlobalProperties.md) (why mapping is separate from the data model),
  [SubjectSources](SubjectSources.md) (the `source → base-URI` registry that is also the RDF prefix/URI map),
  [ECHOLOT](ECHOLOT.md).
- ADRs: [004 dedicated slot](../adr/004-use-dedicated-slot.md) (source of truth),
  [006 schemas](../adr/006-schemas.md), [010 relation IDs](../adr/010-add-guids-to-relations.md),
  [017 names as identifiers](../adr/017-names-as-identifiers.md),
  [019 graph database architecture](../adr/019-graph-database-architecture.md).
- ECHOLOT tasks: T3.1 (standard schemas / ontology reuse), T3.2 (RDF export and import), T2.3 (semantic
  interoperability / ontology patterns), T4.1 (import/transformation pipelines), T4.2 (reconciliation), T4.5
  (quality checks).
- Toy model: [Neutral Person to Many Standards](https://docs.google.com/spreadsheets/d/1j2_7j8RCUJrrMsfZaXtqHQOwp9cN9F8HIBtd3pfsToU/edit)
  — one person model expressed across several ontologies (takin); shared evaluation vehicle for the modelling fork and
  the first mappings.
- Discussions: [#996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996) (this doc),
  [#999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999) (native projection),
  [#1065](https://github.com/ProfessionalWiki/NeoWiki/discussions/1065) (mapping storage granularity).
