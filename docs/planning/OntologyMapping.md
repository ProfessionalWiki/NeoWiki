# Ontology Mapping

This is an early design document for stakeholder feedback before we start implementation and record decisions via a
new ADR.

Started 2026-06-24 by Jeroen De Dauw with help from Claude Opus 4.8.

Status: Draft strawman, for discussion with ECHOLOT partners (T2.3 and T3.x).

## Summary

NeoWiki defines its own native Schemas ([ADR 6](../adr/006-schemas.md)) and projects them to NeoWiki-native RDF
predicates ([RdfMapping.md](RdfMapping.md)). For interoperability — the core of ECHOLOT (SO2, T3.1, T3.2) — that
native data must also be expressible in established cultural-heritage ontologies such as CIDOC-CRM, EDM, HDTO, and
BIBFRAME.

This document proposes an **ontology mapping layer**: a separate, additive layer that translates a wiki's native
Schemas and data into standard-vocabulary RDF, without deforming the native data model. The native model stays
canonical; mappings are first-class, authorable, optional, installable objects.

The central design problem is that good ontology mapping is **not predicate renaming**. CIDOC-CRM and similar
ontologies are event-centric: aligning to them requires *synthesizing intermediate nodes that do not exist in
NeoWiki's data*. The mapping mechanism is therefore a graph-to-graph transformation governed by reusable patterns,
not a lookup table. Most of this document is about how to express and execute those patterns, and where the patterns
should come from.

> Terminology note: ECHOLOT partners often say "RDF mapping" to mean *this* — ontology alignment. In our docs,
> [RdfMapping.md](RdfMapping.md) is the **native RDF projection** (the base serialization), and this document is the
> **ontology mapping layer** on top of it. The two interlock but are designed and matured separately.

## Why a separate, additive layer

This direction is already settled across our planning:

- **Local Schemas stay canonical.** [GlobalProperties.md](GlobalProperties.md) rejected global, ontology-shaped
  properties: the same interoperability is achievable with a mapping layer, without the UX and architectural cost of
  forcing the data model to mirror external ontologies.
- **Map at export time, not at modelling time.** Native schemas are defined for the wiki's own needs and mapped to
  ontologies when RDF is produced. This keeps editing, validation, and querying working against a model users
  understand, while still emitting standard LOD.
- **Model diversity (ECHOLOT architecture principle).** No single data model is assumed; the system offers
  transformation paths between models. One wiki may map to several target ontologies at once (KPI 2.1 targets >6
  mapped models), and a mapping is independent, optional, and installable.

The native projection ([RdfMapping.md](RdfMapping.md)) deliberately emits only NeoWiki-native predicates. The ontology
mapping layer consumes that native data (or the Subject domain objects directly) and emits *additional* triples using
standard vocabulary. The two are composed, not merged.

## What makes this hard: structural transformation

Target ontologies sit on a spectrum of difficulty.

**The easy tier — near 1:1 alignment.** Flat, property-centric targets (Dublin Core, much of EDM) mostly need term
substitution: `Person.Name` → `foaf:name`, `Artwork.Creator` → `dc:creator`. A lookup table from (Schema, property)
to a target term handles this.

**The hard tier — structural / event-centric alignment.** CIDOC-CRM, the dominant CH ontology, mediates
relationships through events. A single native `Creator` relation from an object to a person does not map to one
predicate; it expands to a path with an intermediate event node:

```
E22_Human-Made_Object  →  P108i_was_produced_by  →  E12_Production  →  P14_carried_out_by  →  E39_Actor
```

The `E12_Production` node has **no counterpart in NeoWiki's data**. The mapping must mint it. This is the crux: any
mapping formalism we adopt has to express **path expansion and node synthesis**, not just renaming. A formalism that
only does term substitution covers EDM but fails CIDOC-CRM — and CIDOC-CRM is the one that matters most for the case
studies.

### Identity for synthesized nodes

Synthesized nodes need stable, deterministic IRIs so that re-export is idempotent and the per-page named-graph sync in
[RdfMapping.md](RdfMapping.md#named-graphs) (`DROP GRAPH` + `INSERT DATA`) stays correct.

NeoWiki gives us a natural anchor: **Relations carry a persistent ID** ([ADR 10](../adr/010-add-guids-to-relations.md),
reified as a `neo:Relation` node in the native mapping). The mediating CIDOC-CRM event node can derive its IRI from
that Relation IRI, so the `E12_Production` for a given Creator relation is the same node on every export. Where no
native ID exists (e.g. a literal-valued statement that expands structurally), the IRI must be derived deterministically
from the source Subject IRI plus the rule and a stable position key. This is one reason the [Layer 2 reified
relations](RdfMapping.md#relations) earn their keep: they are the identity hook for structural mapping.

## Design principles

1. **Additive and non-destructive.** Mapping never changes native Schemas or stored data. It only produces RDF. A wiki
   with no mappings still exports valid native RDF.
2. **Patterns are reusable and externally curated where possible.** Encoding CIDOC-CRM correctly is specialist work.
   NeoWiki should *bind* local constructs to curated ontology patterns, not re-encode ontology knowledge in its own
   codebase.
3. **Reuse a formalism; do not invent one casually.** Prefer an established mapping/transformation formalism (see open
   questions) over a bespoke NeoWiki DSL, consistent with [ADR 19](../adr/019-graph-database-architecture.md)'s
   "don't reinvent the query layer" stance.
4. **Mappings are first-class wiki objects.** Authorable and versioned like other content (a wiki page and/or an
   API-writable object), not buried in server configuration. Distributable as bundles.
5. **Bidirectional in intent, export-first in delivery.** Export (native → ontology) ships first; import
   (ontology → native, T3.2/T4.1) reuses the same pattern vocabulary where feasible but is a separate, harder effort.

## Strawman model

A concrete proposal to react to. Details are deliberately tentative; the open questions below are where partner input
is needed.

### The Mapping object

A **Mapping** binds one source Schema to one target ontology and is a set of **rules**. It is a first-class object,
separate from the Schema it references ([ADR 17](../adr/017-names-as-identifiers.md): Schemas are referenced by name).
Separating it from the Schema keeps the Schema clean and lets the same Schema carry several mappings (one per target
ontology), installed independently.

```
Mapping {
  schema:  "Artwork"            # source Schema (by name)
  target:  "cidoc-crm"          # target ontology / profile
  rules:   [ Rule, Rule, ... ]
}
```

### Rules and the transformation

Each **rule** matches a native construct — a Subject of the Schema, a Statement on a given property, or a Relation of
a given type — and emits a **pattern**: a template of target triples that may mint intermediate nodes.

The most natural executable form for such a pattern is a **`CONSTRUCT`-style template** over the native triples. It is
standard, store-agnostic, and mints nodes via deterministic IRI templates. A rule for the Creator example:

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
  BIND( IRI(CONCAT(STR(neo-prod:), STRAFTER(STR(?rel), STR(neo-rel:)))) AS ?production )
}
```

Here `crm:` is CIDOC-CRM and `neo-prod:` is an illustrative namespace for synthesized event nodes; the `BIND` anchors
that node's IRI on the Relation ID so it is stable across re-exports.

The same Artwork Schema mapped to EDM would be mostly flat rules (`?object a edm:ProvidedCHO`,
`?object dc:creator ?actor`) with no synthesized nodes — the easy tier — illustrating that one formalism must span
both ends of the spectrum.

`CONSTRUCT` templates are one candidate *execution* form. Whether authors write them directly, or write something
higher-level (LinkML, SHACL rules, or Platka-sourced patterns) that compiles to them, is the central open question
below.

### Where patterns come from

We should not hand-encode CIDOC-CRM paths from scratch if a curated source exists. T2.3 (takin) is building a pattern
library (Platka) that stores ontology patterns as machine-readable paths. The ideal division of labour: the pattern
library owns the *recipe* (the exact RDF a given alignment must produce); NeoWiki owns the *binding* of local Schemas
and data onto those recipes and the *execution* against actual Subjects. The exact interface and format are open (see
below).

### Execution and storage

The layer runs after the native projection. Two placements are possible and not yet decided:

- **Materialized:** mapped triples are written to the triple store alongside native triples (queryable in standard
  vocabulary via SPARQL, larger store, must be kept in sync per page like the native graph).
- **Export-only:** mapped triples are produced only for bulk RDF export / on-the-fly, leaving the live store native.

## Relationship to the native RDF mapping

- **Composition order.** Native projection first (defines `neo-prop:*`, `neo:Relation`, IRIs, named graphs); ontology
  layer second (reads those, emits standard-vocabulary triples).
- **Interaction with predicate scope ([RdfMapping.md Q1](RdfMapping.md#open-questions)).** Rule left-hand sides are
  simplest and least ambiguous when the native predicate already identifies its Schema. A flat `neo-prop:Name` forces
  every rule to additionally constrain on `rdf:type`; scoped predicates (`neo-prop:Person/Name`) make the rule LHS
  self-identifying. This is a point in favour of revisiting Q1's tentative "flat" resolution, and is called out here so
  the two docs are decided together.
- **Reified relations as identity anchors.** Covered above — the native Layer 2 `neo:Relation` node is what gives
  synthesized event nodes stable IRIs.

## Scope

**In scope:** Schema-, Statement-, and Relation-level mapping rules; node synthesis / path expansion; deterministic
IRIs for synthesized nodes; producing standard-vocabulary RDF for export (and optionally the live store); mapping
objects as authorable, installable bundles.

**Out of scope (separate concerns, cross-referenced):**

- The native RDF projection itself — [RdfMapping.md](RdfMapping.md).
- Reconciliation / entity linking / `owl:sameAs` minting — WP4 (T4.2), not a mapping-layer responsibility, though
  mapped IRIs are its input.
- Rich chain-of-production provenance and rights — T2.4 model and a T3.4 plug-in, not the mapping layer (see
  [ECHOLOT.md](ECHOLOT.md)).
- The mechanics of an RDF *import* pipeline — T3.2/T4.1. Direction noted under principles, not designed here.
- Global, ontology-shaped properties — rejected in [GlobalProperties.md](GlobalProperties.md).

## Open questions

These need ECHOLOT partner input, especially from T2.3 (semantic interoperability / ontology patterns) and partners
with deep CIDOC-CRM / EDM / RDF experience.

**Q1: Mapping formalism.** The central fork. Candidates:
  1. **A NeoWiki-native mapping object** (rules compiling to `CONSTRUCT`-style templates as sketched above). Full
     control, fitted to our model; but a formalism we own and maintain.
  2. **LinkML.** An established, tooling-rich modelling language with class/slot mappings to external vocabularies.
     Open whether its mapping facilities are expressive enough for CIDOC-CRM-style structural expansion, or only the
     near-1:1 tier.
  3. **Patterns sourced from the T2.3 library (Platka).** Bind local constructs to externally-curated patterns,
     minimizing ontology knowledge in NeoWiki. Depends on what the library can emit and how stable/queryable that
     interface is.

  These are not mutually exclusive: e.g. consume patterns from (3), expressed in a formalism like (2), executed via
  (1). What combination do partners recommend?

**Q2: Expressiveness for node synthesis.** Whatever formalism is chosen, can it express path expansion and
intermediate-node minting (the `E12_Production` case) — not just term substitution? Are SHACL Advanced Features
(SPARQL-based `sh:rule`) or `CONSTRUCT` the right executable substrate? Do partners already have CIDOC-CRM expansion
patterns in an executable form we can target?

**Q3: Platka boundary.** Our understanding (to confirm): the T2.3 pattern library *creates* ontologies and derivatives
(e.g. SHACL for validation) but does **not** perform ontology-to-ontology mapping. If so, NeoWiki owns Schema→ontology
mappings, and the library supplies (a) the target patterns we emit and possibly (b) SHACL we validate against. What
exactly does NeoWiki consume from the library, in what format, over what interface?

**Q4: Direction — import.** Is export-first acceptable, with import (ontology/MARC21 → native Schemas) designed later
under T3.2/T4.1? Import is harder (target-Schema inference, reconciliation) and several case studies (ELB LODification,
media art) lean on it. How much should the export formalism be shaped now to be invertible later?

**Q5: Validation via SHACL.** If the T2.3 library emits SHACL shapes, should NeoWiki consume them for validation
(T3.1 constraints, T4.5 quality checks), and is mapping-time validation in scope here or in the validation workstream
([ADR 12](../adr/012-backend-validation.md) / [ADR 21](../adr/021-add-backend-validation.md))?

**Q6: One mapping per target vs combined.** A separate Mapping object per (Schema, target ontology), or a single
multi-target mapping per Schema? Per-target is more modular and independently installable; combined may reduce
duplication for shared sub-patterns.

**Q7: Authoring and distribution.** Where do Mapping objects live (a dedicated namespace? API-only?), who authors them
(data modellers) vs installs them (wiki admins), and how are bundles (e.g. "CIDOC-CRM for Person / Place / Object")
packaged and shared across wikis and across a farm?

**Q8: Multilinguality.** Mapped labels should carry language tags (`@lang`); canonical values used in queries stay
language-neutral. CH data is heavily multilingual (Basque, the ELB languages, etc.). How much of this belongs in the
mapping layer vs the base projection vs Views?

**Q9: Store placement.** Materialize mapped triples in the live store (standard-vocabulary SPARQL queries work
directly, at the cost of store size and sync) or produce them export-only? Affects the SPARQL plugin's sync design.

## Related

- Planning: [RdfMapping](RdfMapping.md) (the native projection this builds on), [GlobalProperties](GlobalProperties.md)
  (why mapping is a layer, not a data-model change), [SubjectSources](SubjectSources.md) (the `source → base-URI`
  registry that is also the RDF prefix/URI map), [ECHOLOT](ECHOLOT.md).
- ADRs: [006 schemas](../adr/006-schemas.md), [010 relation IDs](../adr/010-add-guids-to-relations.md),
  [017 names as identifiers](../adr/017-names-as-identifiers.md),
  [019 graph database architecture](../adr/019-graph-database-architecture.md).
- ECHOLOT tasks: T3.1 (standard schemas / ontology reuse), T3.2 (RDF export), T2.3 (semantic interoperability /
  ontology patterns), T4.1 (import/transformation pipelines).
- Issue: [#723](https://github.com/ProfessionalWiki/NeoWiki/issues/723) (RDF mapping discussion).
