---
title: RDF Export
order: 1
---
# RDF Export

NeoWiki projects its data to RDF in its own vocabulary — the **native projection**. Each wiki page becomes a named
graph holding its page metadata and its Subjects (one RDF resource each) with their Statements and Relations.

The model is specified in [NativeRdfProjection.md](../planning/NativeRdfProjection.md); this page is the as-built
reference for the export surface.

For an end-to-end example comparing the native and ontology-mapped output, see the
[Person-to-EDM worked example](../examples/person-to-edm.md).

## Configuration

| Setting | Default | Purpose |
|---|---|---|
| `$wgNeoWikiRdfBaseUri` | the wiki's canonical URL (`$wgCanonicalServer`) | Base URI under which all NeoWiki IRIs are minted. |
| `$wgNeoWikiSubjectDereferenceTarget` | `page` | Where a browser dereferencing a Subject IRI lands: the hosting `page`, or its `data-tab` row. |

## IRI scheme

All NeoWiki IRIs live under `$base` (`$wgNeoWikiRdfBaseUri`). Standard vocabulary (`rdf:`, `rdfs:`, `xsd:`,
`dcterms:`) is used for standard concepts.

| Prefix | Namespace | Used for |
|---|---|---|
| `neo:` | `$base/ontology/` | NeoWiki vocabulary terms (`neo:Page`, `neo:Relation`, `neo:hasSubject`, `neo:source`, …) |
| `neo-subj:` | `$base/entity/` | Subject IRIs (`neo-subj:s1demo8aaaaaab5`) |
| `neo-prop:` | `$base/prop/` | Property and Relation-type predicates (`neo-prop:Has_author`) |
| `neo-schema:` | `$base/schema/` | Schema classes (`neo-schema:Person`) |
| `neo-rel:` | `$base/relation/` | Relation node IRIs (`neo-rel:r1demo8aaaaaaD6`) |
| `neo-page:` | `$base/page/` | Page resource IRIs (`neo-page:42`) — the subject of the page-metadata triples |

Each page's named-graph IRI is `$base/graph/{projection}/page/{id}`. The `{projection}` segment is `native` or a
Mapping page name (e.g. `EDM`), encoded like the names below — see [Ontology Mapping](ontology-mapping.md). The page
*resource* IRI (`neo-page:42`)
stays projection-independent and appears inside the triples.

Property, Schema, and Relation-type names, and Relation-property keys, form the local part of their IRI: spaces become
underscores (`Has author` → `neo-prop:Has_author`), and characters illegal in an IRI (`%`, `< > " { } | ^ \`, backtick,
control characters) are percent-encoded. Non-ASCII Unicode is kept raw. **Caveat:** the space→underscore step collides
when a name already contains an underscore — `Has author` and `Has_author` share the `neo-prop:Has_author` IRI, which
the native projection accepts. The base URI is trusted admin config and is not encoded.

Value types map to `xsd` datatypes: `text`/`select` → `xsd:string`, `url` → `xsd:anyURI`, `number` → `xsd:decimal`
(or `xsd:integer` when fractionless), `boolean` → `xsd:boolean`, `date` → `xsd:date`, `dateTime` → `xsd:dateTime`.
Extensions map their own property types via
[`addRdfValueMapper`](../extending/extending.md#contributing-rdf-value-mappers). A Statement whose property type has no
registered mapper — including an unregistered type — is omitted from the projection.

A Subject whose Schema cannot be loaded (for example, its Schema page was deleted) is omitted from the projection; a
warning is logged for each.

## Endpoint

RDF is served per page or per Subject. Both take the same `projection` and `format` query parameters. `projection`
selects the vocabulary: `native` (the default, described here) or the name of a Mapping page — see
[Ontology Mapping](ontology-mapping.md); an unknown projection returns `400`. `format` picks the serialization,
falling back to the `Accept` header, then to TriG; a value other than `trig` or `turtle` returns `400`:

| `format` | `Accept` | Content-Type | Named graph |
|---|---|---|---|
| `trig` (default) | `application/trig` | `application/trig; charset=utf-8` | yes |
| `turtle` | `text/turtle` | `text/turtle; charset=utf-8` | no (same triples, no graph wrapper) |

### Page

`GET /rest.php/neowiki/v0/page/{pageId}/rdf`

Returns the page's projection: its page metadata and every Subject on it, in the page's named graph. Returns `404`
when the page does not exist, carries no NeoWiki Subject data, or is not readable by the caller.

```sh
curl 'https://wiki.example/rest.php/neowiki/v0/page/42/rdf?format=turtle'
```

### Subject

`GET /rest.php/neowiki/v0/subject/{subjectId}/rdf`

Returns one Subject's projection: exactly the triples the page export emits for that Subject — its outbound
description, including the native relation reification — with none of the page-metadata triples, in the hosting page's
named graph. Inbound relations pointing at the Subject from elsewhere are not included.

Returns `404` when the Subject does not exist or is on a page the caller may not read — the two are indistinguishable.
A malformed Subject ID returns `400`. A readable Subject whose Schema has no mapping for the requested ontology target
projects to an empty graph — a `200`, not a `404`.

```sh
curl 'https://wiki.example/rest.php/neowiki/v0/subject/s1demo8aaaaaab5/rdf?projection=EDM'
```

### Dereferencing subject IRIs

Every Subject's `neo-subj:` IRI — `$base/entity/{subjectId}` — is a dereferenceable concept URI. A `GET`
content-negotiates it and answers `303 See Other` with an absolute `Location`:

| `Accept` | Redirects to |
|---|---|
| `application/trig` | the Subject's TriG RDF (`.../subject/{id}/rdf?format=trig`) |
| `text/turtle` | the Subject's Turtle RDF (`.../subject/{id}/rdf?format=turtle`) |
| `text/html`, `*/*`, absent, anything else | the Subject's hosting page |

TriG wins when both RDF types are acceptable; the RDF redirects use the native projection. A Subject that is absent or
on a page the caller may not read returns one indistinguishable `404`; a malformed id `400`.

The HTML target is the Subject's hosting page by default, or that page's Data tab (`?action=subjects`) opened on the
Subject's row (`#{subjectId}`) when `$wgNeoWikiSubjectDereferenceTarget` is `data-tab`.

The negotiator is always reachable at the REST path, which needs no server configuration:

```sh
curl -H 'Accept: text/turtle' 'https://wiki.example/rest.php/neowiki/v0/entity/s1demo8aaaaaab5'
```

To make the bare `neo-subj:` IRI dereference, route `/entity/{id}` to that REST path with an internal proxy — a plain
rewrite leaves the path unchanged, so MediaWiki's REST router never matches it. The dev image ships this on Apache
(`mod_proxy` + `mod_proxy_http`); its `/w/` is the dev image's `$wgScriptPath`, which a different install replaces with
its own `rest.php` path:

```apache
RewriteRule ^/?entity/(.+)$ http://127.0.0.1/w/rest.php/neowiki/v0/entity/$1 [P,L]
```

This applies when `$wgNeoWikiRdfBaseUri` is the wiki's own host (the default); an external or institutional base URI is
the operator's own routing concern.

### Finding these exports

These exports are surfaced in the UI. The Data tab (`?action=subjects`) links to each Subject's JSON and per-projection
Turtle/TriG, and the same for the whole page. Pages that carry NeoWiki data also emit `<link rel="alternate">`
autodiscovery tags (Turtle and TriG, native projection) in the HTML head.

## Bulk dump

`maintenance/DumpRdf.php` streams the projection of **every** subject page to stdout as TriG, one named graph per page.
Progress goes to stderr. It defaults to the native projection; `--projection=<name>`
selects an ontology projection by its Mapping page name (see [Ontology Mapping](ontology-mapping.md)).

```sh
php maintenance/run.php NeoWiki:DumpRdf > dump.trig
php maintenance/run.php NeoWiki:DumpRdf --projection=EDM > dump-edm.trig
```

## Not covered here

- SPARQL store and live sync (the projection feeds them, but they are a separate concern).
- RDF import.
- RDFS/OWL self-description of Schemas.
