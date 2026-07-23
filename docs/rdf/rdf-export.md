---
title: RDF Export
order: 1
---
# RDF Export

NeoWiki projects its data to RDF in its own vocabulary — the **native projection**. Each wiki page
becomes a named graph containing its page metadata, its Subjects (one RDF resource each), their
Statements, and their Relations. The projection is lossless and self-sufficient.

The model is specified in [NativeRdfProjection.md](../planning/NativeRdfProjection.md); this page is
the as-built reference for the export surface (config, IRI scheme, endpoint, script). The SPARQL
store and live sync, ontology mappings, and RDF import are separate, later concerns.

For an end-to-end example that exports a page as RDF and compares the native and ontology-mapped
output, see the [Person-to-EDM worked example](../examples/person-to-edm.md).

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
| `neo-page:` | `$base/page/` | Page resource IRIs (`neo-page:42`) — the subject of the page-metadata triples |
| `neo-graph:` | `$base/graph/{projection}/page/` | Named-graph IRIs, qualified by projection (`$base/graph/native/page/42`) |

The `{projection}` segment of the named-graph IRI is `native` or a Mapping page name (e.g. `EDM`), encoded like the
Property and Schema names below, so sibling projections of a page write disjoint graphs and can share one triple
store — see [Ontology Mapping](ontology-mapping.md). The page *resource* IRI (`neo-page:42`) stays
projection-independent and keeps appearing inside the triples.

Property and Schema names form the local part of their IRI: spaces become underscores
(e.g. `Has author` → `neo-prop:Has_author`), and any character that is illegal in an IRI (`%`, the
specials `< > " { } | ^ \`, backtick, and control characters) is percent-encoded so an authored name
can never break out of its IRI or forge extra triples. Non-ASCII Unicode is kept raw, so multilingual
names stay readable. **Caveat:** the space→underscore step collides when a name already contains an
underscore (`Has author` and `Has_author` share the `neo-prop:Has_author` predicate IRI). The native
projection accepts this. (The base URI is trusted admin config and is not encoded.)

A `url` value projects as an **IRI object** (`<https://…>`); a value that is not a valid absolute IRI
falls back to an `xsd:anyURI` literal, so nothing is lost. The other value types map to `xsd` datatypes:
`text`/`select` → `xsd:string`, `number` → `xsd:decimal` (or `xsd:integer` when fractionless), `boolean`
→ `xsd:boolean`, `date` → `xsd:date`, `date-time` → `xsd:dateTime`. Extensions map their own property
types via [`addRdfValueMapper`](../extending/extending.md#contributing-rdf-value-mappers).

A Subject whose Schema is unavailable (for example, its Schema page was deleted) is omitted from the
projection entirely — the same graceful degradation as the Neo4j projection — so the two stores always
describe the same set of entities. A warning is logged for each omitted Subject.

## Endpoint

RDF is served per page or per Subject. Both take the same `projection` and `format` query parameters.
The `projection` selects the vocabulary: `native` (the default, described here) or the name of a
Mapping page — see [Ontology Mapping](ontology-mapping.md); an unknown projection returns `400`. The
`format` picks the serialization, falling back to the `Accept` header, then to TriG:

| `format` | `Accept` | Content-Type | Named graph |
|---|---|---|---|
| `trig` (default) | `application/trig` | `application/trig; charset=utf-8` | yes |
| `turtle` | `text/turtle` | `text/turtle; charset=utf-8` | no (same triples, no graph wrapper) |

### Page

`GET /rest.php/neowiki/v0/page/{pageId}/rdf`

Returns the page's projection: its page metadata and every Subject on it, in the page's named graph.
Returns `404` when the page does not exist or has no NeoWiki Subject data.

```sh
curl 'https://wiki.example/rest.php/neowiki/v0/page/42/rdf?format=turtle'
```

### Subject

`GET /rest.php/neowiki/v0/subject/{subjectId}/rdf`

Returns one Subject's projection: exactly the triples the page export emits for that Subject — its
outbound description, including the native relation reification — with none of the page-metadata
triples, in the hosting page's named graph. Inbound relations pointing at the Subject from elsewhere
are not included.

Returns `404` when the Subject does not exist or is on a page the caller may not read — the two are
indistinguishable. A malformed Subject ID returns `400`. A readable Subject whose Schema has no mapping
for the requested ontology target projects to an empty graph — a `200`, not a `404`.

```sh
curl 'https://wiki.example/rest.php/neowiki/v0/subject/s1demo8aaaaaab5/rdf?projection=EDM'
```

### Finding these exports

These exports are surfaced in the UI: the Data tab (`?action=subjects`) links to each Subject's JSON and
per-projection Turtle/TriG, and to the same for the whole page. Pages that carry NeoWiki data also
advertise the page export through `<link rel="alternate">` autodiscovery tags (Turtle and TriG, native
projection) in the HTML head, so Linked Data tooling can locate the data without reading this reference.

## Bulk dump

`maintenance/DumpRdf.php` streams the projection of **every** subject page to stdout as TriG, one named
graph per page. Progress goes to stderr so stdout stays a clean RDF document. It defaults to the native
projection; `--projection=<name>` selects an ontology projection by its Mapping page name (see
[Ontology Mapping](ontology-mapping.md)).

```sh
php maintenance/run.php NeoWiki:DumpRdf > dump.trig
php maintenance/run.php NeoWiki:DumpRdf --projection=EDM > dump-edm.trig
```

## Not covered here

- SPARQL store and live sync (the projection feeds them, but they are a separate concern).
- RDF import.
- RDFS/OWL self-description of Schemas.
