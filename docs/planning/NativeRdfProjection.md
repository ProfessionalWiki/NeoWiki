# Native RDF Projection

Written 2026-02-23 by Jeroen De Dauw with help from Claude Opus 4.6

Status: Draft, incorporating feedback from ECHOLOT partners (Bilbao meeting March 2026 and async discussion)

Feedback: [discussion #999](https://github.com/ProfessionalWiki/NeoWiki/discussions/999).

## Purpose

This document specifies how NeoWiki's data model projects to RDF triples for the SPARQL plugin described in
[ADR 19](../adr/019-graph-database-architecture.md). The projection determines what RDF a triple store contains
and therefore what SPARQL queries users can write. It also defines the shape of RDF exports.

It has two jobs:

1. **Specify the native projection** — RDF in NeoWiki's own vocabulary: lossless, self-sufficient, and the default
   when no ontology mapping is configured.
2. **Specify the projection infrastructure shared by all projections** — the IRI and namespace regime, per-page
   named graphs, and the sync mechanism. An ontology store reuses all of this; only the triples inside each page's
   graph differ.

Everything specific to non-native targets — projecting into CIDOC-CRM, EDM, and other standard ontologies — lives in
[OntologyMapping.md](OntologyMapping.md), which builds on this document. Native and ontology projections are
siblings: a configured store holds exactly one projection and is queried in that projection's vocabulary, so the
SPARQL plugin is parameterized by projection rather than hardwired to the native one. Read this document first.

This is a strawman proposal. Many decisions here need input from partners with RDF and Linked Open Data expertise,
particularly regarding ontology alignment and cultural heritage conventions. [Open questions](#open-questions) are
collected at the end.

## Design Principles

1. **Simple queries should be simple.** The most common operation — looking up a Subject's properties or finding
   Subjects by property values — should require only basic triple patterns, not navigating reification structures.
2. **No information loss.** Everything in the [Subject format](../reference/subject-format.md) must be representable in RDF,
   including Relation IDs and Relation properties.
3. **Standard RDF 1.1.** No dependency on RDF-star/RDF 1.2, which is still a Working Draft and not supported by
   QLever. Can be adopted later as an optimization.
4. **Standard vocabulary where appropriate.** Use established predicates (`rdf:type`, `rdfs:label`) for standard
   concepts. Use a NeoWiki namespace for domain-specific terms.
5. **Per-wiki namespaces.** Each wiki instance mints its own entity and property URIs. Cross-wiki linking is a
   separate concern (via `owl:sameAs` or similar).

## Namespaces

Each NeoWiki instance uses a configurable base URI (`$base`), defaulting to the wiki's canonical URL. All NeoWiki
URIs live under this base.

| Prefix | URI pattern | Purpose | Example |
|--------|------------|---------|---------|
| `neo:` | `$base/ontology/` | NeoWiki vocabulary terms | `neo:Relation`, `neo:source` |
| `neo-subj:` | `$base/entity/` | Subject IRIs | `neo-subj:s0gje3k4m8n2p1q` |
| `neo-prop:` | `$base/prop/` | Property predicates (direct) | `neo-prop:Website` |
| `neo-schema:` | `$base/schema/` | Schema classes | `neo-schema:Person` |
| `neo-rel:` | `$base/relation/` | Relation node IRIs | `neo-rel:r0gje3k4m8n2p1s` |
| `neo-page:` | `$base/page/` | Page IRIs (also named graph IRIs) | `neo-page:12345` |

Standard prefixes used alongside:

| Prefix | URI |
|--------|-----|
| `rdf:` | `http://www.w3.org/1999/02/22-rdf-syntax-ns#` |
| `rdfs:` | `http://www.w3.org/2000/01/rdf-schema#` |
| `xsd:` | `http://www.w3.org/2001/XMLSchema#` |
| `dcterms:` | `http://purl.org/dc/terms/` |

## Projection

### Subjects

Each Subject becomes an RDF resource. Its Schema determines its `rdf:type`. Its label becomes `rdfs:label`.

```turtle
neo-subj:s0gje3k4m8n2p1q  a           neo-schema:Person ;
                           rdfs:label  "John Doe" .
```

### Statements (non-Relation)

Each Statement becomes one or more triples using the Property Name as predicate. Multi-valued properties
(e.g., a text property with multiple parts) produce multiple triples with the same predicate.

```turtle
neo-subj:s0gje3k4m8n2p1q  neo-prop:Website  "https://example.com"^^xsd:anyURI ;
                           neo-prop:Website  "https://johndoe.dev"^^xsd:anyURI ;
                           neo-prop:Age      42 ;
                           neo-prop:Active   true .
```

#### Value type mapping

| NeoWiki Value Type | RDF Datatype | Notes |
|--------------------|-------------|-------|
| `text` | `xsd:string` | Each part is a separate triple |
| `number` | `xsd:decimal` | Or `xsd:integer` when the value has no fractional part |
| `boolean` | `xsd:boolean` | |
| `url` | `xsd:anyURI` | Each part is a separate triple |

### Relations

Relations are the most complex part of the projection because they carry their own ID and optional properties,
similar to Wikibase qualifiers.

The projection uses a **two-layer approach** inspired by [Wikibase's RDF model](https://www.mediawiki.org/wiki/Wikibase/Indexing/RDF_Dump_Format):

**Layer 1 — Direct triples** for simple queries:

```turtle
neo-subj:s0gje3k4m8n2p1q  neo-prop:Has_author  neo-subj:s0gje3k4m8n2p1r .
```

**Layer 2 — Relation nodes** preserving the Relation ID and properties:

```turtle
neo-rel:r0gje3k4m8n2p1s  a                neo:Relation ;
                          neo:source        neo-subj:s0gje3k4m8n2p1q ;
                          neo:target        neo-subj:s0gje3k4m8n2p1r ;
                          neo:relationType  neo-prop:Has_author ;
                          neo-prop:Role     "Editor" ;
                          neo-prop:Since    2019 .
```

The direct triple (Layer 1) is always emitted, even when the Relation has no properties, so that simple queries
like `?person neo-prop:Has_author ?author` always work without navigating Relation nodes.

The Relation node (Layer 2) is always emitted too, because every Relation has an ID that must be preserved for
round-tripping. Queries that need Relation properties join through the Relation node.

### Pages

Page metadata is emitted as triples about the page resource. The page resource is also used as the named graph
IRI (see [Named Graphs](#named-graphs) below).

```turtle
neo-page:12345  a                 neo:Page ;
                neo:pageName      "John Doe" ;
                dcterms:created   "2024-01-15T10:30:00Z"^^xsd:dateTime ;
                dcterms:modified  "2024-06-20T14:22:00Z"^^xsd:dateTime ;
                neo:lastEditor    "JaneDoe" ;
                neo:category      "People" ;
                neo:category      "Scientists" ;
                neo:mainSubject   neo-subj:s0gje3k4m8n2p1q ;
                neo:hasSubject    neo-subj:s0gje3k4m8n2p1q ;
                neo:hasSubject    neo-subj:s0gje3k4m8n2p2r .
```

### Named Graphs

All triples from a single wiki page are placed in a named graph identified by the page IRI. This enables:

- **Efficient sync:** On page save, `DROP GRAPH <neo-page:12345>` then `INSERT DATA { GRAPH <neo-page:12345> { ... } }`
  replaces all triples for that page atomically.
- **Provenance:** The graph IRI tells you which wiki page the data came from.
- **Page deletion:** `DROP GRAPH <neo-page:12345>` removes all associated triples.

The page metadata triples themselves also live in the page's named graph.

### Complete Example

A page (ID 42) with a main Subject "ACME Corp" (Company) and a child Subject "Jane Smith" (Person),
where Jane is the CEO of ACME:

```trig
GRAPH neo-page:42 {
    # Page metadata
    neo-page:42  a                 neo:Page ;
                 neo:pageName      "ACME Corp" ;
                 dcterms:created   "2024-03-01T09:00:00Z"^^xsd:dateTime ;
                 dcterms:modified  "2025-11-15T16:45:00Z"^^xsd:dateTime ;
                 neo:lastEditor    "Admin" ;
                 neo:mainSubject   neo-subj:s0gje3k4m8n2p1q ;
                 neo:hasSubject    neo-subj:s0gje3k4m8n2p1q ;
                 neo:hasSubject    neo-subj:s0abc1def2ghi3j .

    # Main Subject: ACME Corp (Company)
    neo-subj:s0gje3k4m8n2p1q  a                neo-schema:Company ;
                               rdfs:label       "ACME Corp" ;
                               neo-prop:Website  "https://acme.example"^^xsd:anyURI ;
                               neo-prop:Founded  2019 ;
                               neo-prop:CEO      neo-subj:s0abc1def2ghi3j .

    # Relation node for CEO relation
    neo-rel:r0rel1ation2id3  a                neo:Relation ;
                             neo:source        neo-subj:s0gje3k4m8n2p1q ;
                             neo:target        neo-subj:s0abc1def2ghi3j ;
                             neo:relationType  neo-prop:CEO ;
                             neo-prop:Since    2022 .

    # Child Subject: Jane Smith (Person)
    neo-subj:s0abc1def2ghi3j  a           neo-schema:Person ;
                               rdfs:label  "Jane Smith" ;
                               neo-prop:Age  45 .
}
```

## Sync Mechanism

On each page save, the SPARQL plugin:

1. Maps the `Page` domain object to RDF triples (using the projection above).
2. Issues a SPARQL Update to the configured endpoint:
   ```sparql
   DROP SILENT GRAPH <neo-page:42> ;
   INSERT DATA {
       GRAPH <neo-page:42> {
           # ... all triples ...
       }
   }
   ```

On page deletion:
```sparql
DROP SILENT GRAPH <neo-page:42>
```

`DROP SILENT` avoids errors when the graph does not exist (e.g., first save of a new page).

## What This Does Not Cover

- **Ontology mapping.** The [Global Properties](GlobalProperties.md) document concluded that ontology alignment
  (e.g., "Person.Name maps to `foaf:name`") should happen via a separate ontology mapping, not by changing the data
  model. That mapping is designed in [Ontology Mapping](OntologyMapping.md). The projection described here emits
  NeoWiki-native predicates; an ontology mapping instead projects the data into standard-ontology terms. Note that
  ontology mappings need to be quite expressive: CIDOC-CRM alignment isn't just predicate renaming — it requires
  generating intermediate nodes that don't exist in NeoWiki's data. For example, a simple NeoWiki "Creator" relation
  from an Object to a Person would need to expand to `E22_Human-Made_Object → P108i_was_produced_by →
  E12_Production → P14_carried_out_by → E39_Actor` in CIDOC-CRM, creating the Production event node in RDF.
  At the ECHOLOT meeting in Bilbao (March 2026), the consortium agreed that wiki admins should be able to define
  mappings between ontologies they care about and the NeoWiki Schemas of their wiki. This confirms the
  separate-mapping approach and means several open questions below (Q1, Q2, Q4) are less critical for the native
  projection, since ontology alignment is handled separately. The plan is that NeoWiki provides the mapping
  mechanism, data modellers in the project create standard mapping + Schema bundles (e.g., for CIDOC-CRM), and users
  can optionally install those bundles where relevant.
- **Schema definitions as RDF.** Schemas could be expressed as RDFS/OWL classes with property constraints (similar
  to SHACL shapes). This is potentially valuable for validation and documentation, but is a separate concern.
- **RDF import.** This document covers the outbound direction (NeoWiki data to RDF). Importing RDF data into
  NeoWiki Subjects is a T3.2/T4.1 concern and has its own challenges (mapping external ontologies to NeoWiki
  Schemas).
- **RDF-star / RDF 1.2.** The grant (T3.2) and the D2.1 system spec refer to "native RDF/RDF*" import/export. The
  native projection deliberately targets standard RDF 1.1 (Relation reification, see Design Principle 3), and common
  target stores such as QLever do not support RDF-star. RDF-star is out of scope for now; it would only be
  revisited given a concrete need, and then at the import/export serialization layer rather than the triple store.

## Open Questions

### For ECHOLOT partners with RDF/LOD expertise

These questions need input from people experienced with RDF in cultural heritage contexts. TIB, KMA, TAKIN, and
OEAW are likely the right people.

**Q1: Property predicate scope.** NeoWiki properties are local to Schemas: "Name" in Person and "Name" in Company
are independent definitions. Should the RDF predicates reflect this (`$base/prop/Person/Name` vs
`$base/prop/Company/Name`) or use a flat namespace (`$base/prop/Name`) where same-named properties share a
predicate? The flat approach is more natural for RDF and enables cross-schema queries, but implies a semantic
equivalence that NeoWiki does not enforce. The scoped approach is faithful to the data model but unusual in RDF.

*Feedback: The scoped approach would not be objectionable but makes ontology mapping harder — it duplicates
properties and requires inference for mappings to function at a more general level. With the separate ontology
mapping confirmed (see above), this question is less critical for the native projection: NeoWiki-native predicates
are emitted regardless, and the ontology mapping translates to standard ontology terms. Tentative resolution: flat
namespace (`$base/prop/Name`), since it is more natural for RDF and the ontology mapping handles ontology
alignment.*

*Additional input (2026-07-03): when authoring ontology mappings, same-named properties across Schemas must be
disambiguated as (Schema, property) pairs regardless of predicate scope, and with a flat namespace any mapping rule
that reads the native projection needs an `rdf:type` constraint to select the right Schema's property. Recorded as a
cost of the flat resolution, not a reversal.*

**Q2: Standard vocabulary in the native projection.** The strawman uses `rdf:type`, `rdfs:label`, and `dcterms:created`
/ `dcterms:modified`. Should more standard predicates be used in the native projection (e.g., `foaf:name` for labels,
`dcterms:title` for page names)? Or should all standard vocabulary alignment happen in the ontology mapping?

*Less critical given the confirmed ontology mapping. Tentative resolution: keep the native projection minimal
with the current standard predicates (`rdf:type`, `rdfs:label`, `dcterms:created/modified`). Further standard
vocabulary alignment happens in the ontology mapping.*

**Q3: Relation representation.** The strawman uses Wikibase-style reification (a dedicated Relation node with
`source`, `target`, `relationType`, and properties). Is this the right approach for the CH/LOD community? Are
there conventions we should follow? Should we plan the data model with future RDF-star migration in mind?

**Q4: CIDOC-CRM alignment.** CIDOC-CRM is the dominant ontology in cultural heritage. It uses an event-centric
model (relationships mediated through events) which is quite different from NeoWiki's entity-property model. For
example, a simple "Creator" relation in NeoWiki would correspond to the CIDOC-CRM path
`E22_Human-Made_Object → P108i_was_produced_by → E12_Production → P14_carried_out_by → E39_Actor`, where the
Production event is an intermediate entity that doesn't exist in NeoWiki's data. Does this affect the native
projection, or is it purely an ontology-mapping concern?

*Feedback (TIB, Kolja, from Wikibase experience): Three approaches exist: (1) basic entity-property — simplest,
(2) event-centric — more performant for queries but data not visible on the subject's page, (3) qualifier-based
— familiar from Wikidata, all data on one page. The event-centric UX downside could be mitigated by displaying
related pages on a subject's page (like transclusion of the named graph up to a defined depth). For SPARQL, the
query limitations that SMW had with qualifiers do not occur. Tentative resolution: confirmed as an ontology-mapping
concern, not a native-projection concern. The native projection represents NeoWiki's native data model; CIDOC-CRM
event expansion happens in the ontology mapping.*

**Q5: Named graph conventions.** Per-page named graphs are proposed for operational reasons (efficient sync). Are
there CH/LOD conventions for named graph usage (e.g., per-source, per-dataset) that we should align with? Does
ECHOLOT's provenance model (T2.4) have implications for named graph design?

*Resolved: No CH conventions exist for named graph usage. Per-page named graphs are fine for operational
purposes. Note: per-page named graphs record only data origin (which page), not chain-of-production provenance;
the latter is handled by the provenance model (T2.4) and a dedicated provenance plug-in (T3.4) on top of NeoWiki's
extension points and MediaWiki versioning, not by the native projection. See [ECHOLOT.md](ECHOLOT.md).*

**Q6: Base URI conventions.** Should the base URI be the wiki's URL (e.g., `https://mywiki.example.org/`)? Is
there a convention in the ECHOLOT/ECCCH context for how services should mint URIs?

*Feedback: There is a URI policy being discussed within ECCCH. Aligning with it would be beneficial. To be
followed up on.*

**Q7: URI design for Properties.** Property Names can contain spaces and special characters (e.g., "Founded at",
"Has author"). What's the convention — URL-encode them (`Has%20author`), replace spaces with underscores
(`Has_author`), or something else?

*Feedback: Two suggestions received. (1) CamelCase — upper-case for classes (`BeautifulClass`), lower-case for
properties (`hasSomeFeature`). This is the most common RDF convention. (2) Underscores (`Has_author`) — more
practical for cultural heritage users less familiar with URL encoding. Both are viable; this is a convention
choice, not an architectural one.*

### Implementation decisions (can resolve ourselves)

**Q8: Property type in RDF.** NeoWiki Statements include the "writer's schema" (the property type at write time).
Should this be emitted as a triple on the Subject or Relation node? It's metadata about the statement, not about
the entity. Probably only useful for debugging / round-tripping. Tentative answer: omit from native projection, include
in a "full export" mode.

**Q9: Ordering of multi-valued properties.** NeoWiki stores multi-valued properties as ordered arrays. RDF triple
sets are unordered. Accept the ordering loss for the native projection? Or emit ordering information (adds complexity)?
Tentative answer: accept the loss; ordering is a display concern handled by Views.

*Feedback: Question raised whether ordering truly matters for some use cases (e.g., pages in a book). Tentative
answer unchanged: accept ordering loss in the native projection. If specific use cases require ordering, it can be
added later (e.g., via `rdf:List` or index properties).*

**Q10: Schema namespace page.** Should NeoWiki emit an RDFS/OWL definition for each Schema (as a class) and each
Property Definition (as a property with domain/range)? This would make the RDF self-describing. Tentative answer:
yes, but as a separate enhancement, not blocking the initial projection. Partner demand recorded (takin, 2026-07-03):
an RDFS export of local Schemas is wanted as an input for authoring ontology mappings — a wiki's Schemas are
effectively its own ontology, and the native projection should be able to say so in RDF. See also the generated shape
exports in [ShapeLanguages.md](ShapeLanguages.md).
