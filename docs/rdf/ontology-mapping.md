---
title: Ontology Mapping
order: 2
---
# Ontology Mapping

NeoWiki stores data in its own native Schemas and projects it to RDF.

Users can project to established ontologies like EDM and Dublin Core by defining **ontology mappings**.

NeoWiki also comes with a [**native projection**](rdf-export.md), based on the native Schemas, available
without having to define an ontology mapping first.

The native projection and each ontology mapping are sibling projections of the same source data. Multiple
can be supported at the same time, letting API users choose their RDF format, and giving wiki admins the
option to realize multiple projections in separate SPARQL stores.

The design and its open questions are in
[planning/OntologyMapping.md](../planning/OntologyMapping.md); this page is the as-built reference for
the shipped v1. See the
[Person-to-EDM worked example](../examples/person-to-edm.md) for a complete walkthrough: a Person Schema projected to EDM, the native
and mapped output side by side, and the current gaps.

> **v1 is deliberately minimal and the stored format is provisional.** v1 covers only the *near-1:1
> tier* — term substitution: a target class for the Subject and one target predicate per mapped
> property. It does **not** synthesize the intermediate event nodes that CIDOC-CRM-style ontologies
> need. The mapping-formalism question is still open
> ([OntologyMapping.md Q1](../planning/OntologyMapping.md#open-questions), [#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995)),
> so the `"version": 1` format may change. It is versioned so a later tier can supersede it.

## Ontology Mappings are wiki pages

A Mapping is a page in the **`Mapping:` namespace** with content model `NeoWikiMapping` (JSON), edited
like a Schema or Layout page and gated by the `neowiki-mapping-edit` right. There is **one Mapping page
per target ontology**, and the page title is the target's name — the projection name you pass to the
export surfaces below ([ADR 17](../adr/017-names-as-identifiers.md)-style, just like Schema pages). The
page `Mapping:EDM`, for example, defines the `EDM` projection.

A single page holds an entry for **every mapped Schema**. You map a Schema to an ontology by adding an
entry to that ontology's page, not by creating a page. A Schema still maps to several ontologies — one
entry on each ontology's page — and the Schema is never changed to fit an ontology.

Uniqueness is **by construction**: a page cannot list the same Schema twice (they are JSON object keys),
and page titles are unique, so no save-time duplicate check is needed. The name **`native`** is reserved
for the built-in [native projection](rdf-export.md), so a `Mapping:Native` page is rejected on save.

## Format (version 1)

```json
{
    "version": 1,
    "prefixes": {
        "edm": "http://www.europeana.eu/schemas/edm/",
        "rdaGr2": "http://rdvocab.info/ElementsGr2/",
        "skos": "http://www.w3.org/2004/02/skos/core#"
    },
    "schemas": {
        "Person": {
            "subject": { "class": "edm:Agent" },
            "properties": {
                "Description": { "predicate": "skos:note", "lang": "en" },
                "Birth date":  { "predicate": "rdaGr2:dateOfBirth" },
                "Birth place": { "predicate": "rdaGr2:placeOfBirth" }
            }
        },
        "City": {
            "subject": { "class": "edm:Place" },
            "properties": {}
        }
    }
}
```

Top level:

| Field | Required | Meaning |
|---|---|---|
| `version` | yes | Format version. Must be `1`. |
| `prefixes` | no | Prefix label → namespace IRI, shared by every entry, used to expand the CURIEs below. |
| `schemas` | yes | Native Schema name → the entry that projects its Subjects (see below). May be empty. A Schema's existence is not checked at save time. |

Each **schema** entry (a value in `schemas`):

| Field | Required | Meaning |
|---|---|---|
| `subject.class` | yes | The `rdf:type` given to each Subject of the Schema. A CURIE or an absolute IRI. |
| `properties` | yes | NeoWiki property name → how to project it (see below). May be empty. |

Each **property** entry:

| Key | Required | Meaning |
|---|---|---|
| `predicate` | yes | Target predicate for the property's values. A CURIE or an absolute IRI. |
| `lang` | no | BCP-47-shaped language tag (`^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$`, e.g. `en`, `pt-BR`) applied to the produced literal **when it is a plain string** (text/select values). Ignored for typed literals (numbers, dates, …). Mutually exclusive with `datatype`. |
| `datatype` | no | Absolute IRI or CURIE that overrides the literal's datatype. For a `url` value, which otherwise projects as an IRI object, setting `datatype` forces a literal with that datatype. Mutually exclusive with `lang`. |

### CURIEs, IRIs, and safety

A `class`, `predicate`, or `datatype` is either a **CURIE** `prefix:local` whose prefix is declared in
`prefixes`, or an **absolute IRI** containing `://`. A CURIE with an undeclared prefix is rejected (it
is a typo, not a bare IRI). Non-authority IRI schemes (`urn:`, `mailto:`, …) are out of scope for v1.

Terms are expanded to exact ontology IRIs and are **never percent-encoded** — a Mapping must reproduce
the ontology's terms verbatim. A term (or a declared prefix namespace) that would expand to an IRI
containing an IRIREF-illegal character (`< > " { } | ^ \` backtick, space, control characters) is
**rejected at save time**. The `lang` tag is constrained the same way — it must be BCP-47-shaped, so it
cannot smuggle a datatype or a stray `"` into the serialized literal.

Both checks are re-applied at **projection time**, so a Mapping stored before validation existed (or
loaded via `importDump`) still cannot corrupt the output: a class, predicate, datatype, or prefix that
does not re-expand safely is dropped, and an invalid language tag falls back to a plain literal — each
with a logged warning. A bad stored Mapping degrades the projection rather than aborting the export.

## What gets emitted

For each Subject on a page whose Schema has an entry on the requested projection's Mapping page:

- `rdf:type <subject.class>`.
- `rdfs:label "<label>"` — always, so every projected entity is labelled.
- One triple per mapped property **value** (multi-valued properties repeat the predicate). Unmapped
  properties are **absent** — conformant output is the point.
- A **relation** value becomes a direct triple to the target Subject's IRI. No `neo:Relation`
  reification node and no relation qualifiers are projected (native-vocabulary constructs with no v1
  mapping).

Deliberate v1 boundaries:

- **Subject IRIs stay native** (`neo-subj:` under the wiki's RDF base URI): the entity is the wiki's
  own; only the *vocabulary* comes from the target ontology. Cross-linking to external entities
  (`owl:sameAs`, reconciliation) is later work.
- A **Subject whose Schema has no entry** on the Mapping page is absent entirely. Its IRI can still
  appear as the target of a relation from a mapped Subject — untyped — exactly as a missing Schema
  behaves in the native projection.
- **No page-metadata triples** are emitted (no page node, `neo:hasSubject`, etc.).
- Quads are placed in the **per-page named graph for this target** (`$base/graph/{target}/page/{id}`), like the
  native projection, so the same per-page sync infrastructure works for an ontology store — and because the graph
  is qualified by the target, sibling projections of a page can share one store.

## Selecting a projection

The RDF export surfaces take an optional `projection`:

`GET /rest.php/neowiki/v0/page/{pageId}/rdf?projection=EDM`

- `projection` is `native` (the default, unchanged behaviour) or the name of a Mapping page (its title).
- An unknown projection returns **`400`** listing the known projections.

```sh
# Native (default):
curl 'https://wiki.example/rest.php/neowiki/v0/page/42/rdf'
# EDM ontology projection:
curl 'https://wiki.example/rest.php/neowiki/v0/page/42/rdf?projection=EDM&format=turtle'
```

The bulk dump takes the same option:

```sh
php maintenance/run.php NeoWiki:DumpRdf --projection=EDM > dump.trig
```

## Authoring a Mapping

1. Create a page in the `Mapping:` namespace named after the target ontology — the title is the
   projection name, e.g. `Mapping:EDM`. If a page for that ontology already exists, edit it instead.
2. Declare the page-level `prefixes` you will use.
3. Add an entry under `schemas` for each Schema you want to project: give the Subject a `subject.class`
   and map the properties you want to publish. Only listed properties are projected.
4. Save. Structural errors and unresolvable/unsafe terms are reported on save; the reserved page name
   `native` is rejected.
5. Export a page of a mapped Schema with `?projection=<page title>` to see the result.
