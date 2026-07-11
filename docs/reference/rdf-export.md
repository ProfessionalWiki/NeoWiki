---
title: RDF Export
order: 8
---
# RDF Export

NeoWiki projects its data to RDF in its own vocabulary — the **native projection**. Each wiki page
becomes a named graph containing its page metadata, its Subjects (one RDF resource each), their
Statements, and their Relations. The projection is lossless and self-sufficient.

The model is specified in [NativeRdfProjection.md](../planning/NativeRdfProjection.md); this page is
the as-built reference for the export surface (config, IRI scheme, endpoint, script). The SPARQL
store and live sync, ontology mappings, and RDF import are separate, later concerns.

## Configuration

| Setting | Default | Purpose |
|---|---|---|
| `$wgNeoWikiRdfBaseUri` | the wiki's canonical URL (`$wgCanonicalServer`) | Base URI under which all NeoWiki IRIs are minted. |

The base URI is wiki-level on purpose: sibling projections (native and, later, ontology-mapped)
describe the same entities, so their Subject IRIs must be identical across stores. Set it explicitly
to align with an institutional URI policy.

## IRI scheme

All NeoWiki IRIs live under `$base` (`$wgNeoWikiRdfBaseUri`). Standard vocabulary (`rdf:`, `rdfs:`,
`xsd:`, `dcterms:`) is used for standard concepts.

| Prefix | Namespace | Used for |
|---|---|---|
| `neo:` | `$base/ontology/` | NeoWiki vocabulary terms (`neo:Page`, `neo:Relation`, `neo:hasSubject`, `neo:source`, …) |
| `neo-subj:` | `$base/entity/` | Subject IRIs (`neo-subj:s1demo8aaaaaab5`) |
| `neo-prop:` | `$base/prop/` | Property and Relation-type predicates (`neo-prop:Has_author`) |
| `neo-schema:` | `$base/schema/` | Schema classes (`neo-schema:Person`) |
| `neo-rel:` | `$base/relation/` | Relation node IRIs (`neo-rel:r1demo8aaaaaaD6`) |
| `neo-page:` | `$base/page/` | Page IRIs, which are also the named-graph IRIs (`neo-page:42`) |

Property and Schema names form the local part of their IRI by substituting spaces with underscores
(e.g. `Has author` → `neo-prop:Has_author`). **Caveat:** a name that already contains an underscore
collides with the space-substituted form of the corresponding spaced name (`Has author` and
`Has_author` share a predicate IRI). The native projection accepts this.

Value types map to `xsd` datatypes: `text`/`select` → `xsd:string`, `url` → `xsd:anyURI`, `number` →
`xsd:decimal` (or `xsd:integer` when fractionless), `boolean` → `xsd:boolean`, `date` → `xsd:date`,
`date-time` → `xsd:dateTime`. Extensions map their own property types via
[`addRdfValueMapper`](extending.md#contributing-rdf-value-mappers).

## Endpoint

`GET /rest.php/neowiki/v0/page/{pageId}/rdf`

Returns the page's native projection. The format is chosen by the `format` query parameter, falling
back to the `Accept` header, then to TriG:

| `format` | `Accept` | Content-Type | Named graph |
|---|---|---|---|
| `trig` (default) | `application/trig` | `application/trig` | yes |
| `turtle` | `text/turtle` | `text/turtle` | no (same triples, no graph wrapper) |

Returns `404` when the page does not exist or has no NeoWiki Subject data.

```sh
curl 'https://wiki.example/rest.php/neowiki/v0/page/42/rdf?format=turtle'
```

## Bulk dump

`maintenance/DumpRdf.php` streams the native projection of **every** subject page to stdout as TriG,
one named graph per page. Progress goes to stderr so stdout stays a clean RDF document.

```sh
php maintenance/run.php NeoWiki:DumpRdf > dump.trig
```

## Not covered here

- SPARQL store and live sync (the projection feeds them, but they are a separate concern).
- Ontology mappings (CIDOC-CRM, EDM, …), which project into standard-ontology terms instead.
- RDF import.
- RDFS/OWL self-description of Schemas.
