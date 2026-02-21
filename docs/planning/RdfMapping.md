# RDF Mapping Design

Written 2026-02-21 by Jeroen De Dauw with help from Claude Code, Opus 4.6

Status: Early draft for discussion with ECHOLOT partners

## Purpose

This document proposes how NeoWiki's data model maps to RDF triples for the SPARQL plugin described in
[ADR 19](../adr/019_Graph_Database_Architecture.md). The mapping determines what RDF a triple store contains
and therefore what SPARQL queries users can write. It also defines the shape of RDF exports.

This is a strawman proposal. Many decisions here need input from partners with RDF and Linked Open Data expertise,
particularly regarding ontology alignment and cultural heritage conventions. [Open questions](#open-questions) are
collected at the end.

## Design Principles

1. **Simple queries should be simple.** The most common operation — looking up a Subject's properties or finding
   Subjects by property values — should require only basic triple patterns, not navigating reification structures.
2. **No information loss.** Everything in the [Subject format](../SubjectFormat.md) must be representable in RDF,
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
| `nw:` | `$base/entity/` | Subject IRIs | `nw:s0gje3k4m8n2p1q` |
| `nwp:` | `$base/prop/` | Property predicates (direct) | `nwp:Website` |
| `nws:` | `$base/schema/` | Schema classes | `nws:Person` |
| `nwr:` | `$base/relation/` | Relation node IRIs | `nwr:r0gje3k4m8n2p1s` |
| `nwpage:` | `$base/page/` | Page IRIs (also named graph IRIs) | `nwpage:12345` |

Standard prefixes used alongside:

| Prefix | URI |
|--------|-----|
| `rdf:` | `http://www.w3.org/1999/02/22-rdf-syntax-ns#` |
| `rdfs:` | `http://www.w3.org/2000/01/rdf-schema#` |
| `xsd:` | `http://www.w3.org/2001/XMLSchema#` |
| `dcterms:` | `http://purl.org/dc/terms/` |

## Mapping

### Subjects

Each Subject becomes an RDF resource. Its Schema determines its `rdf:type`. Its label becomes `rdfs:label`.

```turtle
nw:s0gje3k4m8n2p1q  a            nws:Person ;
                     rdfs:label   "John Doe" .
```

### Statements (non-Relation)

Each Statement becomes one or more triples using the Property Name as predicate. Multi-valued properties
(e.g., a text property with multiple parts) produce multiple triples with the same predicate.

```turtle
nw:s0gje3k4m8n2p1q  nwp:Website      "https://example.com"^^xsd:anyURI ;
                     nwp:Website      "https://johndoe.dev"^^xsd:anyURI ;
                     nwp:Age          42 ;
                     nwp:Active       true .
```

#### Value type mapping

| NeoWiki Value Type | RDF Datatype | Notes |
|--------------------|-------------|-------|
| `text` | `xsd:string` | Each part is a separate triple |
| `number` | `xsd:decimal` | Or `xsd:integer` when the value has no fractional part |
| `boolean` | `xsd:boolean` | |
| `url` | `xsd:anyURI` | Each part is a separate triple |

### Relations

Relations are the most complex part of the mapping because they carry their own ID and optional properties,
similar to Wikibase qualifiers.

The mapping uses a **two-layer approach** inspired by [Wikibase's RDF model](https://www.mediawiki.org/wiki/Wikibase/Indexing/RDF_Dump_Format):

**Layer 1 — Direct triples** for simple queries:

```turtle
nw:s0gje3k4m8n2p1q  nwp:Has_author  nw:s0gje3k4m8n2p1r .
```

**Layer 2 — Relation nodes** preserving the Relation ID and properties:

```turtle
nwr:r0gje3k4m8n2p1s  a                nw:Relation ;
                      nw:source        nw:s0gje3k4m8n2p1q ;
                      nw:target        nw:s0gje3k4m8n2p1r ;
                      nw:relationType  nwp:Has_author ;
                      nwp:Role         "Editor" ;
                      nwp:Since        2019 .
```

The direct triple (Layer 1) is always emitted, even when the Relation has no properties, so that simple queries
like `?person nwp:Has_author ?author` always work without navigating Relation nodes.

The Relation node (Layer 2) is always emitted too, because every Relation has an ID that must be preserved for
round-tripping. Queries that need Relation properties join through the Relation node.

### Pages

Page metadata is emitted as triples about the page resource. The page resource is also used as the named graph
IRI (see [Named Graphs](#named-graphs) below).

```turtle
nwpage:12345  a                nw:Page ;
              nw:pageName      "John Doe" ;
              dcterms:created  "2024-01-15T10:30:00Z"^^xsd:dateTime ;
              dcterms:modified "2024-06-20T14:22:00Z"^^xsd:dateTime ;
              nw:lastEditor    "JaneDoe" ;
              nw:category      "People" ;
              nw:category      "Scientists" ;
              nw:mainSubject   nw:s0gje3k4m8n2p1q ;
              nw:hasSubject    nw:s0gje3k4m8n2p1q ;
              nw:hasSubject    nw:s0gje3k4m8n2p2r .
```

### Named Graphs

All triples from a single wiki page are placed in a named graph identified by the page IRI. This enables:

- **Efficient sync:** On page save, `DROP GRAPH <nwpage:12345>` then `INSERT DATA { GRAPH <nwpage:12345> { ... } }`
  replaces all triples for that page atomically.
- **Provenance:** The graph IRI tells you which wiki page the data came from.
- **Page deletion:** `DROP GRAPH <nwpage:12345>` removes all associated triples.

The page metadata triples themselves also live in the page's named graph.

### Complete Example

A page (ID 42) with a main Subject "ACME Corp" (Company) and a child Subject "Jane Smith" (Person),
where Jane is the CEO of ACME:

```trig
GRAPH nwpage:42 {
    # Page metadata
    nwpage:42  a                nw:Page ;
               nw:pageName      "ACME Corp" ;
               dcterms:created  "2024-03-01T09:00:00Z"^^xsd:dateTime ;
               dcterms:modified "2025-11-15T16:45:00Z"^^xsd:dateTime ;
               nw:lastEditor    "Admin" ;
               nw:mainSubject   nw:s0gje3k4m8n2p1q ;
               nw:hasSubject    nw:s0gje3k4m8n2p1q ;
               nw:hasSubject    nw:s0abc1def2ghi3j .

    # Main Subject: ACME Corp (Company)
    nw:s0gje3k4m8n2p1q  a            nws:Company ;
                         rdfs:label   "ACME Corp" ;
                         nwp:Website  "https://acme.example"^^xsd:anyURI ;
                         nwp:Founded  2019 ;
                         nwp:CEO      nw:s0abc1def2ghi3j .

    # Relation node for CEO relation
    nwr:r0rel1ation2id3  a                nw:Relation ;
                          nw:source        nw:s0gje3k4m8n2p1q ;
                          nw:target        nw:s0abc1def2ghi3j ;
                          nw:relationType  nwp:CEO ;
                          nwp:Since        2022 .

    # Child Subject: Jane Smith (Person)
    nw:s0abc1def2ghi3j  a            nws:Person ;
                         rdfs:label   "Jane Smith" ;
                         nwp:Age      45 .
}
```

## Sync Mechanism

On each page save, the SPARQL plugin:

1. Maps the `Page` domain object to RDF triples (using the mapping above).
2. Issues a SPARQL Update to the configured endpoint:
   ```sparql
   DROP SILENT GRAPH <nwpage:42> ;
   INSERT DATA {
       GRAPH <nwpage:42> {
           # ... all triples ...
       }
   }
   ```

On page deletion:
```sparql
DROP SILENT GRAPH <nwpage:42>
```

`DROP SILENT` avoids errors when the graph does not exist (e.g., first save of a new page).

## What This Does Not Cover

- **Ontology mapping layer.** The [Global Properties](GlobalProperties.md) document concluded that ontology
  alignment (e.g., "Person.Name maps to `foaf:name`") should happen via a separate mapping layer, not by changing
  the data model. That layer is a separate design effort. The RDF mapping described here emits NeoWiki-native
  predicates; the ontology mapping layer would emit additional triples using standard vocabulary terms.
- **Schema definitions as RDF.** Schemas could be expressed as RDFS/OWL classes with property constraints (similar
  to SHACL shapes). This is potentially valuable for validation and documentation, but is a separate concern.
- **RDF import.** This document covers the outbound direction (NeoWiki data to RDF). Importing RDF data into
  NeoWiki Subjects is a T3.2/T4.1 concern and has its own challenges (mapping external ontologies to NeoWiki
  Schemas).

## Open Questions

### For ECHOLOT partners with RDF/LOD expertise

These questions need input from people experienced with RDF in cultural heritage contexts. TIB, KMA, TAKIN, and
OEAW are likely the right people.

**Q1: Property predicate scope.** NeoWiki properties are local to Schemas: "Name" in Person and "Name" in Company
are independent definitions. Should the RDF predicates reflect this (`$base/prop/Person/Name` vs
`$base/prop/Company/Name`) or use a flat namespace (`$base/prop/Name`) where same-named properties share a
predicate? The flat approach is more natural for RDF and enables cross-schema queries, but implies a semantic
equivalence that NeoWiki does not enforce. The scoped approach is faithful to the data model but unusual in RDF.

**Q2: Standard vocabulary in the base mapping.** The strawman uses `rdf:type`, `rdfs:label`, and `dcterms:created`
/ `dcterms:modified`. Should more standard predicates be used in the base mapping (e.g., `foaf:name` for labels,
`dcterms:title` for page names)? Or should all standard vocabulary alignment happen in the ontology mapping layer?

**Q3: Relation representation.** The strawman uses Wikibase-style reification (a dedicated Relation node with
`source`, `target`, `relationType`, and properties). Is this the right approach for the CH/LOD community? Are
there conventions we should follow? Should we plan the data model with future RDF-star migration in mind?

**Q4: CIDOC-CRM alignment.** CIDOC-CRM is the dominant ontology in cultural heritage. It uses an event-centric
model (relationships mediated through events) which is quite different from NeoWiki's entity-property model. Does
this affect the base RDF mapping, or is it purely an ontology mapping layer concern?

**Q5: Named graph conventions.** Per-page named graphs are proposed for operational reasons (efficient sync). Are
there CH/LOD conventions for named graph usage (e.g., per-source, per-dataset) that we should align with? Does
ECHOLOT's provenance model (T2.4) have implications for named graph design?

**Q6: Base URI conventions.** Should the base URI be the wiki's URL (e.g., `https://mywiki.example.org/`)? Is
there a convention in the ECHOLOT/ECCCH context for how services should mint URIs?

**Q7: URI design for Properties.** Property Names can contain spaces and special characters (e.g., "Founded at",
"Has author"). What's the convention — URL-encode them (`Has%20author`), replace spaces with underscores
(`Has_author`), or something else?

### Implementation decisions (can resolve ourselves)

**Q8: Property type in RDF.** NeoWiki Statements include the "writer's schema" (the property type at write time).
Should this be emitted as a triple on the Subject or Relation node? It's metadata about the statement, not about
the entity. Probably only useful for debugging / round-tripping. Tentative answer: omit from base mapping, include
in a "full export" mode.

**Q9: Ordering of multi-valued properties.** NeoWiki stores multi-valued properties as ordered arrays. RDF triple
sets are unordered. Accept the ordering loss for the base mapping? Or emit ordering information (adds complexity)?
Tentative answer: accept the loss; ordering is a display concern handled by Views.

**Q10: Schema namespace page.** Should NeoWiki emit an RDFS/OWL definition for each Schema (as a class) and each
Property Definition (as a property with domain/range)? This would make the RDF self-describing. Tentative answer:
yes, but as a separate enhancement, not blocking the initial mapping.
