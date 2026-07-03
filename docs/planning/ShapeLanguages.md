# Shape Languages: ShEx and SHACL

Written 2026-07-03 by Jeroen De Dauw with help from Claude Fable 5.

Status: Position for discussion with ECHOLOT partners (T2.3, T3.x, T4.5). We are explicitly after disagreement and
additions: if an argument below is wrong, or a use case is missing, please say so.

## Position

[SHACL](https://www.w3.org/TR/shacl/) (a W3C Recommendation) and [ShEx](https://shex.io/) (used by Wikidata for its
EntitySchemas) describe and validate the shape of RDF graphs. This document answers what role they should play in
NeoWiki — up to and including replacing NeoWiki's own Schema format.

In one line: **native Schemas stay the source of truth; shape languages are adopted at the RDF boundaries.**

1. NeoWiki keeps its own Schema format ([ADR 6](../adr/006-schemas.md), [ADR 9](../adr/009-move-away-from-json-schema.md),
   [format reference](../reference/schema-format.md)) and its own validator. We do not adopt ShEx or SHACL as the
   internal schema format, nor as the engine behind Subject validation.
2. NeoWiki embraces shape languages where RDF crosses its boundary:
   - **Emitting shapes** generated from native Schemas, as machine-readable contracts for consumers of our RDF.
   - **Conformance-validating ontology projections** (CIDOC-CRM, EDM, …) against target-ontology shapes, in
     cooperation with the T4.5 quality-check component.
   - **Import**: validating incoming RDF against shapes, and bootstrapping native Schemas from a shape document.

The asymmetry is deliberate: generating shapes *from* native Schemas is lossless, because our constraint model is a
small subset of what shape languages express. The reverse direction is lossy, which is acceptable at import time
(reported, once) but not for a source-of-truth format (silent, forever).

## Background

For NeoWiki terminology, see the [glossary](../concepts/glossary.md). The short version: Subjects hold Statements
whose Values can have multiple ordered parts; Relations between Subjects carry persistent IDs and their own
properties. Schemas define Subject types via Property Definitions. Subject validation is backend-only: a single PHP
validator behind REST endpoints returns structured violations
([ADR 21](../adr/021-add-backend-validation.md), amended by ADR 25 in
[#973](https://github.com/ProfessionalWiki/NeoWiki/pull/973); [codes reference](../reference/validation-codes.md)),
and the editing UI renders what the server returns. For RDF, the wiki's data is *projected*: a native projection
([RdfMapping](RdfMapping.md)) and per-store ontology projections
([OntologyMapping](OntologyMapping.md), in review in [#920](https://github.com/ProfessionalWiki/NeoWiki/pull/920)).

## Why not as the internal format or engine

[ADR 9](../adr/009-move-away-from-json-schema.md) already records the short form of this decision. Since the project
has changed around it — validation moved to the backend, RDF projections and ontology mappings are being designed —
we revisited it. The conclusion stands, for these reasons:

1. **Our data model is not an RDF graph.** Shape engines validate RDF. Using one internally would put a
   Subject-to-RDF projection inside the editing path, and require translating engine reports back into NeoWiki terms.
2. **Violation reporting is a UI contract, not a boolean.** Our validator returns stable codes with message arguments
   and a `valuePartIndex` pointing into a Value's *ordered* parts, distinguishes writer's-schema type drift
   ([ADR 11](../adr/011-include-writers-schema.md)), and separates pre-existing violations from newly introduced ones
   so that enforcement never locks users out of already-invalid Subjects. SHACL reports (focus node, path, constraint
   component) cannot carry part indexes into ordered values — RDF multi-values are unordered — and have no notion of
   "pre-existing". ShEx reporting is weaker still.
3. **Schemas carry more than constraints.** Property Definitions also hold editing and display configuration (select
   options with stable IDs, defaults, display attributes) and graph-projection configuration (relation type names,
   target Schemas). SHACL's annotation vocabulary covers some presentation concerns; ShEx has nothing comparable.
   Either way, product configuration would live inside RDF documents that every consumer must parse.
4. **The subset problem.** Shape languages express far more than a form-based editing UI can round-trip: recursion,
   disjunction, closed shapes, arbitrary cardinality ranges, SPARQL-based constraints. If stored shapes were the
   source of truth, we would either reject out-of-subset documents (standard-looking syntax without standard
   semantics) or preserve constructs the editor cannot represent (silently dropped or corrupted on the next form
   save). We already ran this experiment with JSON Schema ([ADR 6](../adr/006-schemas.md) →
   [ADR 9](../adr/009-move-away-from-json-schema.md)); the mismatch with ShEx or SHACL is larger. Wikidata's
   EntitySchemas illustrate the other outcome: stored ShEx, edited as raw text, not enforced in the editing path.
5. **Extensibility is code.** Property Types are a plugin interface: extensions register new types with their own
   validation logic in PHP. Shape languages cannot express those validators.
6. **Engine availability.** The canonical validator is PHP. We are not aware of a production-grade PHP implementation
   of either SHACL or ShEx, so an internal engine would mean maintaining one ourselves or adding a sidecar service to
   the interactive editing round-trip.

None of this diminishes shape languages at what they are designed for — RDF graph conformance — which is exactly
where they fit:

## Where shape languages fit

### Generated shape exports

Generate SHACL (and, given demand, ShEx) from native Schemas, expressed over the projection vocabularies. Consumers
of NeoWiki RDF get a machine-readable contract for the data's structure; the Wikibase-adjacent community gets
artifacts in a familiar form. This complements the self-description question in [RdfMapping](RdfMapping.md) (Q10) and
the possible JSON Schema output mentioned in [ADR 9](../adr/009-move-away-from-json-schema.md) — one
schema-translation surface with several output formats. Cheap to build: a serializer, no engine.

### Conformance validation of ontology projections

The one thing shapes do that native validation never can: check that a projection into a target ontology conforms to
that ontology's expectations — both that the mapping produces correct structure (checked over sample Subjects at
mapping-authoring time) and that the data meets the target's requirements (e.g. a mandatory rights statement is
missing). Engines run in external quality tooling (T4.5), not in the wiki's editing path; NeoWiki provides projected
RDF, incremental re-validation via per-page named graphs, and traceability of findings back to page, Subject, and
property. Detailed in [OntologyMapping](OntologyMapping.md) ("Validating projections").

Workflow-wise, conformance is a publication and import concern, surfaced as reports and worklists — the pattern
Wikidata uses for property-constraint reports — and as pre-publication gates where output must pass a target's
ingestion checks (e.g. supplying EDM to an aggregator). It is deliberately not an inline editing signal: ordinary
editors work in native terms, and recurring conformance findings are better fed back into tightening the native
Schema or the mapping defaults than shown as errors on every edit.

### Import

Two uses. First, validate incoming RDF against shapes inside the import pipeline (T4.1), so violations surface in the
import preview and job reports where someone is already triaging records. Second, **bootstrap native Schemas from a
shape document**: map the supported subset to Property Definitions, and report what was not mapped. Lossy-with-report
is acceptable here because authority transfers to the native Schema after import. This supports shipping standard
Schema bundles and migrating existing shape-described models (e.g. Wikibase EntitySchemas) into NeoWiki.

## SHACL first, ShEx optional

SHACL is the primary target: it is the W3C Recommendation, it is what the T4.5 task description names, it is what the
T2.3 pattern library is expected to emit, and it has the larger tooling ecosystem. ShEx's pull is familiarity in the
Wikidata/Wikibase community. Since the exportable subset is small, one generator can serialize both cheaply — but we
would rather add ShEx output in response to a concrete consumer than speculatively.

## Open questions for partners

**Q1: Shape consumers.** Which tools or workflows on the partner side would consume SHACL (or ShEx) generated from
NeoWiki Schemas? This determines how much the export capability matters and in which profile.

**Q2: Existing target-ontology shapes.** Which shapes exist today that ontology projections should be checked
against? Platka-derived SHACL for CIDOC-CRM? EDM validation appears to live in XML Schema/Schematron (Metis) rather
than SHACL — what should an RDF-side EDM check use?

**Q3: ShEx demand.** Is there an actual consumer for ShEx output or input (EntitySchema interoperability), or is
SHACL sufficient for ECHOLOT?

**Q4: Bootstrap sources.** Which existing shape-described models would partners want turned into starter NeoWiki
Schemas, as a test of the import-bootstrap direction?

**Q5: The position itself.** Does anyone see a use case that requires shapes as the *internal* format or engine —
something the boundary approach above cannot serve?

## Related

- Planning: [OntologyMapping](OntologyMapping.md) (projection validation, mapping formalism),
  [RdfMapping](RdfMapping.md) (native projection), [GlobalProperties](GlobalProperties.md),
  [ECHOLOT](ECHOLOT.md).
- ADRs: [006 schemas](../adr/006-schemas.md), [009 move away from JSON Schema](../adr/009-move-away-from-json-schema.md)
  (records ShEx/SHACL as considered alternatives), [011 writer's schema](../adr/011-include-writers-schema.md),
  [021 backend validation](../adr/021-add-backend-validation.md) and its amendment in
  [#973](https://github.com/ProfessionalWiki/NeoWiki/pull/973).
- Reference: [schema format](../reference/schema-format.md), [validation codes](../reference/validation-codes.md).
- ECHOLOT tasks: T3.1 (structured data, constraints and validation), T3.2 (RDF import/export), T2.3 (semantic
  interoperability / ontology patterns), T4.1 (import pipelines), T4.5 (quality checks, names SHACL).
