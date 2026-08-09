# Native RDF Projection

Written 2026-02-23 by Jeroen De Dauw with help from Claude Opus 4.6

Status: Draft, incorporating feedback from ECHOLOT partners (Bilbao meeting March 2026 and async discussion)

Discussion: [#999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999).

> **As-built (2026-07).** The native projection specified here has shipped, together with the shared
> IRI/namespace regime — wiki-level, so subject IRIs are identical across stores — reused by every
> sibling projection, and the per-page named graphs it defines, qualified by projection
> ([#1053](https://github.com/ProfessionalWiki/NeoWiki/issues/1053)) so sibling projections can share one store. See
> the [RDF Export reference](../rdf/rdf-export.md). Ontology (sibling) projections build on this shared
> infrastructure — see [OntologyMapping.md](OntologyMapping.md) and the worked
> [Person → EDM example](../rdf/person-to-edm.md). The sync into a configured SPARQL 1.1 store (e.g. QLever) has
> shipped too, as the per-page graph replacement this document specifies.

## Still open (2026-08)

- [Q6 — Base URI conventions](#q6-base-uri-conventions): which base URI an ECCCH-integrated deployment mints under.
- [Q8 — Writer's schema in RDF](#q8-writers-schema-in-rdf): whether a full-export mode carries it. Untracked.
- [Q10 — Schema namespace page](#q10-schema-namespace-page): RDFS/OWL self-description of Schemas
  ([#1163](https://github.com/ProfessionalWiki/NeoWiki/issues/1163)).

Everything else this document asked has been answered — see [Decided](#decided).

## Purpose

Why NeoWiki has a projection of its own, and in the shape it has. The projection determines what RDF a triple store
holds and therefore what SPARQL queries users can write, so it is a design decision rather than a serialization
detail; [ADR 19](../adr/019-graph-database-architecture.md) puts it behind the SPARQL plugin.

The native projection is RDF in NeoWiki's own vocabulary: lossless, self-sufficient, and the default when no ontology
mapping is configured. It also settles the infrastructure every projection shares — the IRI and namespace regime,
per-page named graphs, and the store sync. Native and ontology projections are siblings: a store holds one or more,
each in its own family of per-page named graphs, and is queried in the vocabulary of whichever projection a query
targets. Everything specific to non-native targets lives in [OntologyMapping.md](OntologyMapping.md), which builds on
this document.

## Design Principles

1. **Simple queries should be simple.** The most common operation — looking up a Subject's properties or finding
   Subjects by property values — should require only basic triple patterns, not navigating reification structures.
2. **No information loss.** Everything in the [Subject format](../api/subject-format.md) must be representable in RDF,
   including Relation IDs and Relation properties.
3. **Standard RDF 1.1.** No dependency on RDF-star/RDF 1.2, which is still a Working Draft and not supported by
   QLever. Can be adopted later as an optimization.
4. **Standard vocabulary where appropriate.** Use established predicates (`rdf:type`, `rdfs:label`) for standard
   concepts. Use a NeoWiki namespace for domain-specific terms.
5. **Per-wiki namespaces.** Each wiki instance mints its own entity and property URIs. Cross-wiki linking is a
   separate concern (via `owl:sameAs` or similar).

## What it emits

This design has shipped. What NeoWiki actually emits — the IRI and namespace regime, the triples
for Subjects, Statements, Relations and page metadata, the per-page named graphs, and the export endpoints — is
the [RDF Export reference](../rdf/rdf-export.md); the [Person → EDM example](../rdf/person-to-edm.md) shows a real
page projected. A configured SPARQL store receives each save as a replace of that page's graph and each deletion as
a drop ([store configuration](../operations/installation.md#optional-sparql-graph-stores)); the measured cost of
that write path, and the targets it is held to, are in the
[performance reference](../operations/performance.md) and [ADR 29](../adr/029-scalability-targets.md).

## What This Does Not Cover

- **Ontology mapping.** The [Global Properties](GlobalProperties.md) document concluded that ontology alignment
  (e.g., "Person.Name maps to `foaf:name`") should happen via a separate ontology mapping, not by changing the data
  model. That mapping is designed in [Ontology Mapping](OntologyMapping.md). The projection described here emits
  NeoWiki-native predicates; an ontology mapping instead projects the data into standard-ontology terms. Ontology
  mappings need to be quite expressive: CIDOC-CRM alignment isn't just predicate renaming — it requires generating
  intermediate nodes that don't exist in NeoWiki's data. That node synthesis has shipped
  ([#1229](https://github.com/ProfessionalWiki/NeoWiki/pull/1229),
  [#1263](https://github.com/ProfessionalWiki/NeoWiki/pull/1263)); the
  [Person → EDM example](../rdf/person-to-edm.md) walks a worked case.
  At the ECHOLOT meeting in Bilbao (March 2026), the consortium agreed that wiki admins should be able to define
  mappings between ontologies they care about and the NeoWiki Schemas of their wiki. This confirmed the
  separate-mapping approach, and is why Q1, Q2 and Q4 resolved toward keeping the native projection minimal (see
  [Decided](#decided)). The plan is that NeoWiki provides the mapping
  mechanism, data modellers in the project create standard mapping + Schema bundles (e.g., for CIDOC-CRM), and users
  can optionally install those bundles where relevant.
- **Schema definitions as RDF.** Schemas could be expressed as RDFS/OWL classes with property constraints (similar
  to SHACL shapes). Valuable for validation and documentation, but a separate concern — see
  [Q10](#q10-schema-namespace-page).
- **RDF import.** This document covers the outbound direction (NeoWiki data to RDF). Importing RDF data into
  NeoWiki Subjects is a T3.2/T4.1 concern and has its own challenges (mapping external ontologies to NeoWiki
  Schemas).
- **RDF-star / RDF 1.2.** The grant (T3.2) and the D2.1 system spec refer to "native RDF/RDF*" import/export. The
  native projection deliberately targets standard RDF 1.1 (Relation reification, see Design Principle 3), and QLever,
  the primary target store, does not support RDF-star (Oxigraph has preliminary RDF 1.2 support). RDF-star is out of
  scope for now; it would only be revisited given a concrete need, and then at the import/export serialization layer
  rather than the triple store.

## Open Questions

Questions the implementation answered are listed under [Decided](#decided), keeping their original numbers so a
thread that cites "Q7" still resolves.

### Q6: Base URI conventions

Should the base URI be the wiki's URL, and is there a convention in the ECHOLOT/ECCCH context for how services
should mint URIs?

*Feedback: There is a URI policy being discussed within ECCCH. Aligning with it would be beneficial. To be
followed up on.*

*As built: the base URI is configurable — `$wgNeoWikiRdfBaseUri`, defaulting to the wiki's canonical URL
(`$wgCanonicalServer`); see the [RDF Export reference](../rdf/rdf-export.md). What stays open is which value an
ECCCH-integrated deployment should be given.*

### Q8: Writer's schema in RDF

NeoWiki Statements record the "writer's schema", the property type at write time
([ADR 11](../adr/011-include-writers-schema.md)). The projection omits it, as proposed: it is metadata about the
Statement rather than about the entity, and useful mainly for debugging and round-tripping. Open: whether a
"full export" mode should carry it. No issue tracks this.

### Q10: Schema namespace page

Should NeoWiki emit an RDFS/OWL definition for each Schema (as a class) and each Property Definition (as a property
with domain/range)? This would make the RDF self-describing. Tentative answer: yes, but as a separate enhancement,
not blocking the initial projection. Partner demand recorded (takin, 2026-07-03): an RDFS export of local Schemas is
wanted as an input for authoring ontology mappings — a wiki's Schemas are effectively its own ontology, and the
native projection should be able to say so in RDF. Tracked in
[#1163](https://github.com/ProfessionalWiki/NeoWiki/issues/1163). See also the generated shape exports in
[ShapeLanguages.md](ShapeLanguages.md).

## Decided

Answered by shipping or by partner feedback. Numbers are the original question numbers.

- **Q1 — property predicate scope.** Flat (`$base/prop/Name`), not scoped per Schema: more natural for RDF, and
  ontology alignment happens in the mapping either way. Recorded cost: a mapping rule reading the native projection
  needs an `rdf:type` constraint to select the right Schema's property (2026-07-03).
- **Q2 — standard vocabulary.** The native projection stays minimal — `rdf:type`, `rdfs:label`, and
  `dcterms:created`/`dcterms:modified`. Further standard-vocabulary alignment belongs to an ontology mapping.
- **Q3 — relation representation.** Wikibase-style reification alongside the direct triple, as specified
  ([reference](../rdf/rdf-export.md#projected-triples)); George Bruseker (takin) confirmed having both
  representations is handy. Residue: `relationType` was flagged as a confusing term
  ([#999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999)) and renaming it rides with the relations
  design pass ([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)), which may reshape the Relation node.
- **Q4 — CIDOC-CRM alignment.** An ontology-mapping concern, not a native-projection one (TIB, Kolja). The node
  synthesis it needs has since shipped ([#1229](https://github.com/ProfessionalWiki/NeoWiki/pull/1229),
  [#1263](https://github.com/ProfessionalWiki/NeoWiki/pull/1263)).
- **Q5 — named graph conventions.** No CH convention exists; per-page named graphs are fine for operational purposes.
  They record data origin only — chain-of-production provenance is the T2.4 model and a T3.4 plug-in, not the
  projection (see [ECHOLOT.md](ECHOLOT.md)).
- **Q7 — URI design for Properties.** Underscores: spaces in a name become underscores in the IRI local name
  (`Has_author`), with partner concurrence that either convention works (George Bruseker, takin, 2026-07-06).
- **Q9 — ordering of multi-valued properties.** Ordering loss is accepted; ordering is a display concern for Views.
  Where ordering is real data, model it explicitly so it stays recoverable in the export (George Bruseker, takin,
  2026-07-06).
