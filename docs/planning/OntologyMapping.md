# Ontology Mapping

This is an early design document for stakeholder feedback before we start implementation and record decisions via a
new ADR.

Started 2026-06-24 by Jeroen De Dauw with help from Claude Opus 4.8.

Status: Draft strawman, for discussion with ECHOLOT partners (T2.3 and T3.x).

Discussion: [#996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996).

> **As-built (v1, 2026-07).** The near-1:1 term-substitution tier of this design has shipped: Mappings as
> pages in a `Mapping:` namespace — one page per target ontology, the page title being the target name
> ([#1065](https://github.com/ProfessionalWiki/NeoWiki/discussions/1065)) — and an ontology projection
> selectable alongside the native one on the RDF export endpoint and `DumpRdf`. See the
> [Ontology Mapping reference](../rdf/ontology-mapping.md) and the worked
> [Person → EDM example](../examples/person-to-edm.md). The structural / node-synthesis
> tier and the mapping-formalism question (Q1, [#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995))
> remain open; the stored `"version": 1` format is provisional. Provisional answers v1 gives to some open
> questions below are noted inline.

## Summary

NeoWiki defines its own native Schemas ([ADR 6](../adr/006-schemas.md)). For RDF and SPARQL it projects that data into
RDF; the native projection uses NeoWiki-native predicates ([NativeRdfProjection.md](NativeRdfProjection.md)). For
interoperability — the core of ECHOLOT (SO2, T3.1, T3.2) — the data must instead be available in established
cultural-heritage ontologies such as CIDOC-CRM, EDM, HDTO, and BIBFRAME.

An **ontology mapping** projects native NeoWiki data **directly into a target ontology**. That ontology RDF is what a
configured triple store holds and what SPARQL queries run against. The NeoWiki-native projection
([NativeRdfProjection.md](NativeRdfProjection.md)) and each target ontology are **sibling projections** of the same
source data, selected per store rather than stacked: a store is configured with one or more projections, each in its
own family of named graphs, and different stores can hold different projection sets of the same wiki. The native
projection is the default (used when no ontology mapping is configured) and the lossless-export target; ontology
mappings define the other targets.

Mappings are first-class, authorable, installable objects, kept separate from Schemas so the native data model is not
deformed to fit an ontology. A mapping defines the **correspondence** between NeoWiki's model and an ontology, and is
intended to drive both export (native → ontology) and, later, import (ontology → native).

The central difficulty is that good ontology mapping is **not predicate renaming**: CIDOC-CRM and similar ontologies
are event-centric and require *synthesizing intermediate nodes that do not exist in NeoWiki's data*. The mapping is a
graph-to-graph transformation governed by reusable patterns. Most of this document is about expressing those patterns,
where they come from, how the open flat-vs-nested modelling fork affects them, the import direction, and validating
what the projections produce.

> Terminology note: ECHOLOT partners often say "RDF mapping" for ontology alignment — that is *this* document. The
> native vocabulary and the projection infrastructure shared by all projections (IRIs, named graphs, sync) live in
> [NativeRdfProjection.md](NativeRdfProjection.md).

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
  backend owns its mapping). A store is configured with an endpoint and the **list of projections** to load into it (the
  [#586](https://github.com/ProfessionalWiki/NeoWiki/issues/586) store config); running several stores with different
  projection sets stays a **deployment choice** — hard isolation, independent scaling — not a requirement.

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

Target ontologies sit on a spectrum of difficulty.

**The easy tier — near 1:1 alignment.** Flat, property-centric targets (Dublin Core, much of EDM) mostly need term
substitution: `Person.Name` → `foaf:name`, `Artwork.Creator` → `dc:creator`.

**The hard tier — structural / event-centric alignment.** CIDOC-CRM, the dominant CH ontology, mediates relationships
through events. A single native `Creator` relation from an object to a person does not map to one predicate; it expands
to a path with an intermediate event node:

```
E22_Human-Made_Object  →  P108i_was_produced_by  →  E12_Production  →  P14_carried_out_by  →  E39_Actor
```

The `E12_Production` node has **no counterpart in NeoWiki's data**. The mapping must mint it. This is the crux: any
mapping formalism we adopt has to express **path expansion and node synthesis**, not just renaming. A formalism that
only substitutes terms covers flat data going to EDM but fails CIDOC-CRM — and CIDOC-CRM is the one that matters most
for the case studies.

**The mirror requirement — contraction.** The transformation also runs the other way: where the data carries structure
a target does not want, the mapping must walk it and collapse it — a Birth event Subject linked from a Person collapses
to `rdaGr2:dateOfBirth` on the flat EDM agent. Both directions are needed because a wiki serves several targets at once
([sibling projections](#projections-not-layers)) that disagree about shape, so at least one target mismatches whichever
style the data is modelled in. Live on the demo wiki ([neowiki.dev](https://neowiki.dev)): flat-modelled Picasso
projects fully to EDM; Bach, whose birth is an explicit Subject, projects sparse because contraction is unimplemented.

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

The fork is settled outside this document — it is a schema-model and editing-UX decision, exercised through the shared
toy model ([Neutral Person to Many Standards](https://docs.google.com/spreadsheets/d/1j2_7j8RCUJrrMsfZaXtqHQOwp9cN9F8HIBtd3pfsToU/edit))
that expresses one person model across several ontologies. The same toy model doubles as the first end-to-end
exercise of this document's approach: implement its neutral person schema in NeoWiki, define a Mapping for it, and
project to EDM first — the near-1:1 tier — proving or disproving the mechanism by doing (proposed at the 2026-07-03
WP2/3/4 call). What matters here is that the formalism needs both
directions under **either** route: not every wiki will model maximally nested (a mapping must handle whatever the
native model is), and sibling targets decompose differently — EDM stays flat where CIDOC-CRM wants events — so no
single nesting depth spares all projections. Route (b) reduces how often synthesis fires and makes contraction fire
for the flat targets instead; it does not remove the requirement (Q2, Q10).

### Identity for synthesized nodes

Synthesized nodes need stable, deterministic IRIs so that re-projecting a page is idempotent and the per-page
named-graph sync in [NativeRdfProjection.md](NativeRdfProjection.md#named-graphs) (`DROP GRAPH` + `INSERT DATA`)
stays correct for an ontology store too.

NeoWiki gives a natural anchor: **Relations carry a persistent ID** ([ADR 10](../adr/010-add-guids-to-relations.md)).
The mediating CIDOC-CRM event node can derive its IRI from that Relation ID, so the `E12_Production` for a given Creator
relation is the same node on every projection. Where no native ID exists (e.g. a literal-valued statement that expands
structurally), the IRI is derived deterministically from the source Subject IRI plus the rule and a stable position key.
Stable synthesized-node identity is also what makes the import direction tractable and validation reports traceable
back to the wiki (both below).

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

## Strawman model

A concrete proposal to react to. Details are deliberately tentative; the open questions below are where partner input is
needed.

### The Mapping object

A **Mapping** binds one source Schema to one target ontology and is a set of **rules**. It is a first-class object,
separate from the Schema it references ([ADR 17](../adr/017-names-as-identifiers.md): Schemas are referenced by name),
so the Schema stays clean and can carry several mappings (one per target ontology), installed independently.

```
Mapping {
  schema:  "Artwork"            # source Schema (by name)
  target:  "cidoc-crm"          # target ontology / profile
  rules:   [ Rule, Rule, ... ]
}
```

### Rules and the transformation

Each **rule** matches a native construct — a Subject of the Schema, a Statement on a given property, or a Relation of a
given type — and emits a **pattern**: target triples that may mint intermediate nodes.

A natural way to express a rule is a **`CONSTRUCT`-style template**: standard, store-agnostic, minting nodes via
deterministic IRI templates. The native projection serves as the transformation's input representation, and the
target-ontology triples it produces are what the store holds. A rule for the Creator example:

```sparql
# rule: Artwork.Creator → CIDOC-CRM production event
CONSTRUCT {
  ?object      a crm:E22_Human-Made_Object ;
               crm:P108i_was_produced_by ?production .
  ?production  a crm:E12_Production ;
               crm:P14_carried_out_by ?actor .
  ?actor       a crm:E39_Actor .
}
WHERE {
  ?rel  a neo:Relation ;
        neo:relationType neo-prop:Creator ;
        neo:source ?object ;
        neo:target ?actor .
  # deterministic IRI for the synthesized event, anchored on the Relation ID
  BIND( IRI(CONCAT(STR(crm-prod:), STRAFTER(STR(?rel), STR(neo-rel:)))) AS ?production )
}
```

Here `crm:` is CIDOC-CRM and `crm-prod:` is an illustrative namespace for synthesized event nodes; the `BIND` anchors
that node's IRI on the Relation ID so it is stable across re-projections. The same Artwork Schema mapped to EDM would be
mostly flat rules (`?object a edm:ProvidedCHO`, `?object dc:creator ?actor`) with no synthesized nodes — illustrating
that one formalism must span both ends of the spectrum.

`CONSTRUCT` is one candidate form. Whether authors write templates directly, or write something higher-level (LinkML,
SHACL rules, or patterns sourced from the T2.3 library) that compiles to them, is the central open question below.

### Where patterns come from

We should not hand-encode CIDOC-CRM paths from scratch if a curated source exists. T2.3 (takin) is building a pattern
library (Platka) that stores ontology patterns as machine-readable paths. The ideal division of labour: the library
owns the *recipe* (the exact RDF a given alignment must produce); NeoWiki owns the *binding* of local Schemas and data
onto those recipes and the *execution* against actual Subjects. The exact interface and format are open (below).

## Import

The mapping is the **correspondence** between NeoWiki's model and an ontology, so it is also the basis for importing
ontology RDF into NeoWiki Subjects (T3.2 calls for native RDF/RDF\* import; T2.3 frames interoperability as
bidirectional model-model mappings). Export ships first, but the formalism should be chosen with import in mind.

Import is not export run backwards. A structural mapping is generally **not trivially invertible**: importing CIDOC-CRM
means **recognising** the `E12_Production` pattern in the incoming graph and **collapsing** it back to a `Creator`
relation — graph pattern-matching on input, not template expansion of output. Import also pulls in adjacent concerns the
export projection does not have:

- **Reconciliation / entity linking** of incoming IRIs to existing Subjects — a WP4 concern (T4.2), not the mapping
  itself.
- **Batch ID-minting** for many interlinked incoming Subjects (the current API mints one Subject at a time).
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
- Per-page named graphs ([NativeRdfProjection.md](NativeRdfProjection.md#named-graphs)) make re-validation
  incremental: only pages whose graphs changed since the last run need re-checking.
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
  [NativeRdfProjection.md](NativeRdfProjection.md).
- The import *pipeline* mechanics and orchestration — T4.1.
- Reconciliation / entity linking / `owl:sameAs` minting — WP4 (T4.2); mapped IRIs are its input.
- Rich chain-of-production provenance and rights — T2.4 model and a T3.4 plug-in (see [ECHOLOT.md](ECHOLOT.md)).
- Global, ontology-shaped properties — rejected in [GlobalProperties.md](GlobalProperties.md).

## Open questions

These need ECHOLOT partner input, especially from T2.3 (semantic interoperability / ontology patterns) and partners
with deep CIDOC-CRM / EDM / RDF experience.

**Q1: Mapping formalism.** The central fork. Candidates:
  1. **A NeoWiki-native mapping object** (rules compiling to `CONSTRUCT`-style templates as sketched above). Full
     control, fitted to our model; but a formalism we own and maintain.
  2. **LinkML.** An established, tooling-rich modelling language with class/slot mappings to external vocabularies. Open
     whether its mapping facilities are expressive enough for CIDOC-CRM-style structural expansion, or only the near-1:1
     tier.
  3. **Patterns sourced from the T2.3 library (Platka).** Bind local constructs to externally-curated patterns,
     minimizing ontology knowledge in NeoWiki. Depends on what the library can emit and how stable that interface is.

  These are not mutually exclusive: e.g. consume patterns from (3), expressed in a formalism like (2), executed via (1).
  What combination do partners recommend? Independent of the combination: survey existing CH mapping tooling before
  building — mature ontology-mapping frameworks exist in this space, and reusing or aligning with one may beat
  rebuilding (George, 2026-07-03).

**Q2: Expressiveness for node synthesis.** Whatever formalism is chosen, can it express path expansion and
intermediate-node minting (the `E12_Production` case) — not just term substitution? Can it equally express the
[mirror direction](#what-makes-this-hard-structural-transformation) — recognising explicitly modelled structure and
collapsing it for a flatter target? Both directions are recorded as an evaluation constraint on
[#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995). Are SHACL Advanced Features
(SPARQL-based `sh:rule`) or `CONSTRUCT` the right executable substrate? Do partners already have CIDOC-CRM expansion
patterns in an executable form we can target?

**Q3: Platka boundary.** Our understanding (to confirm): the T2.3 pattern library *creates* ontologies and derivatives
(e.g. SHACL for validation) but does **not** perform ontology-to-ontology mapping. If so, NeoWiki owns Schema→ontology
mappings, and the library supplies (a) the target patterns we project to and possibly (b) SHACL we validate against.
What exactly does NeoWiki consume from the library, in what format, over what interface?

**Q4: Import.** How far should the export formalism be shaped now so the same Mapping can drive import later? Where is
the boundary between the Mapping (correspondence), the import executor (pattern recognition), and T4.1/T4.2 (pipeline,
reconciliation)?

**Q5: Validation via SHACL.** [Validating projections](#validating-projections) proposes consuming shapes emitted by
the T2.3 library to check ontology projections, with the engines in the T4.5 tooling rather than in NeoWiki. Open:
which shapes the library actually emits and their coverage per ontology; and where findings surface for curators
(report pages, a dashboard, an API the quality component writes back to) — in NeoWiki core or a plug-in. Candidate
engine (suggested 2026-07-03): [rudof](https://github.com/rudof-project/rudof) — Rust, SHACL + ShEx, has an MCP
interface, and can validate a QLever endpoint directly; endpoint-side validation still needs the traceability path
above, since the store has no sync-back to the wiki.

**Q6: One mapping per target vs combined.** A separate Mapping per (Schema, target ontology), or a single multi-target
mapping per Schema? Per-target is more modular and independently installable; combined may reduce duplication for shared
sub-patterns.

*v1: one Mapping page per target ontology, holding an entry for every mapped Schema — the page title is the target name,
so uniqueness needs no save-time check ([#1065](https://github.com/ProfessionalWiki/NeoWiki/discussions/1065)). Combined
multi-target pages stay open for a later format version.*

**Q7: Authoring and distribution.** Where do Mappings live (a dedicated namespace? API-only?), who authors them (data
modellers) vs installs them (wiki admins), and how are bundles (e.g. "CIDOC-CRM for Person / Place / Object") packaged
and shared across wikis and a farm?

*v1: Mappings live as pages in a dedicated `Mapping:` namespace (one page per target ontology, named after it —
[ADR 17](../adr/017-names-as-identifiers.md)-style), authored like Schemas/Layouts and gated by the
`neowiki-mapping-edit` right, and seedable as demo/bundle data (the Person→EDM example ships this way). Packaging and
farm-wide sharing of bundles is not yet addressed.*

**Q8: Multilinguality.** Mapped labels should carry language tags (`@lang`); canonical values used in queries stay
language-neutral. CH data is heavily multilingual (Basque, the ELB languages, etc.). How much belongs in the mapping vs
the native projection vs Views?

**Q9: Multiple projections per wiki.** Should a wiki be able to serve several projections at once (several stores:
native + one or more ontologies), and is a native projection always available as a baseline? This makes "which
vocabulary is in the store" a per-store configuration rather than a single global choice.

*v1: yes for export — a wiki serves several projections at once, selected per request via the `projection` parameter,
with `native` always the baseline and each Mapping page adding its target to the known set. Named graphs are qualified
by projection ([#1053](https://github.com/ProfessionalWiki/NeoWiki/issues/1053)), so a single store can likewise hold
several projections at once — see [Projections, not layers](#projections-not-layers). The projector/serializer seam
#586 consumes is `newRdfProjection(name)`.*

**Q10: Flat vs nested native modelling.** The [fork above](#flat-vs-nested-native-modelling-open-fork): should
case-study data live in flat Schemas with the mapping synthesizing intermediate nodes, or in nested Schemas with the
editing UI projecting the nesting down? Settled through the toy model, outside this document. If (b) wins: what does
nesting look like in the schema format, and how much of the synthesis machinery here stops being exercised in practice?

*Update (2026-07): the fork no longer gates the transformation machinery — both directions are needed however it
resolves, since sibling targets disagree about shape and at least one mismatches either modelling style ("one would
need to expand (or contract) on export/import" — George Bruseker,
[#999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999), 2026-07-06). What the fork still steers: build
order, editing-UI investment, and what standard Schema bundles default to.*

## Related

- Planning: [NativeRdfProjection](NativeRdfProjection.md) (the native projection and shared per-store infrastructure),
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
