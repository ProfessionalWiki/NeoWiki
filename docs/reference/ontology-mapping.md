---
title: Ontology Mapping
order: 9
---
# Ontology Mapping

NeoWiki stores data in its own native Schemas and projects it to RDF. The **native projection**
([RDF export](rdf-export.md)) uses NeoWiki-native vocabulary. An **ontology mapping** projects the
same data into an established cultural-heritage ontology instead (EDM, Dublin Core, …), so the RDF is
directly interoperable. The native projection and each ontology mapping are **sibling projections** of
the same source data, selected per request (or, later, per SPARQL store).

The design and its open questions are in
[planning/OntologyMapping.md](../planning/OntologyMapping.md); this page is the as-built reference for
the shipped v1.

> **v1 is deliberately minimal and the stored format is provisional.** v1 covers only the *near-1:1
> tier* — term substitution: a target class for the Subject and one target predicate per mapped
> property. It does **not** synthesize the intermediate event nodes that CIDOC-CRM-style ontologies
> need. The mapping-formalism question is still open
> ([OntologyMapping.md Q1](../planning/OntologyMapping.md#open-questions), [#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995)),
> so the `"version": 1` format may change. It is versioned precisely so a later tier can supersede it.

## Mappings are wiki pages

A Mapping is a page in the **`Mapping:` namespace** with content model `NeoWikiMapping` (JSON), edited
like a Schema or Layout page and gated by the `neowiki-mapping-edit` right. Each Mapping binds **one
Schema** to **one target ontology**. A Schema can have several Mappings (one per target); the Schema is
never changed to fit an ontology.

**One Mapping per (Schema, target).** Saving a Mapping whose `(schema, target)` pair another Mapping
page already claims is rejected. (The check is lookup-based, so a concurrent double-save could still
slip a duplicate through before production; if that happens, the projector picks the alphabetically
first Mapping page and logs a warning.)

## Format (version 1)

```json
{
    "version": 1,
    "schema": "Person",
    "target": "edm",
    "prefixes": {
        "edm": "http://www.europeana.eu/schemas/edm/",
        "dc": "http://purl.org/dc/elements/1.1/"
    },
    "subject": { "class": "edm:ProvidedCHO" },
    "properties": {
        "Name":    { "predicate": "dc:title", "lang": "en" },
        "Website": { "predicate": "edm:isShownAt" },
        "Author":  { "predicate": "dc:creator" }
    }
}
```

| Field | Required | Meaning |
|---|---|---|
| `version` | yes | Format version. Must be `1`. |
| `schema` | yes | Name of the native Schema this Mapping applies to. |
| `target` | yes | Short identifier of the target ontology/profile (`[A-Za-z][A-Za-z0-9_-]*`). This is the value passed to the `projection` parameter below. |
| `prefixes` | no | Prefix label → namespace IRI, used to expand the CURIEs below. |
| `subject.class` | yes | The `rdf:type` given to each Subject of the Schema. A CURIE or an absolute IRI. |
| `properties` | yes | NeoWiki property name → how to project it (see below). May be empty. |

Each **property** entry:

| Key | Required | Meaning |
|---|---|---|
| `predicate` | yes | Target predicate for the property's values. A CURIE or an absolute IRI. |
| `lang` | no | Language tag applied to the produced literal **when it is a plain string** (text/select values). Ignored for typed literals (numbers, dates, …). Mutually exclusive with `datatype`. |
| `datatype` | no | Absolute IRI or CURIE that overrides the literal's datatype. Mutually exclusive with `lang`. |

### CURIEs, IRIs, and safety

A `class`, `predicate`, or `datatype` is either a **CURIE** `prefix:local` whose prefix is declared in
`prefixes`, or an **absolute IRI** containing `://`. A CURIE with an undeclared prefix is rejected (it
is a typo, not a bare IRI). Non-authority IRI schemes (`urn:`, `mailto:`, …) are out of scope for v1.

Terms are expanded to exact ontology IRIs and are **never percent-encoded** — a Mapping must reproduce
the ontology's terms verbatim. A term (or a declared prefix namespace) that would expand to an IRI
containing an IRIREF-illegal character (`< > " { } | ^ \` backtick, space, control characters) is
**rejected at save time**, so a Mapping can never corrupt the projected document.

## What gets emitted

For each Subject on a page whose Schema has a Mapping for the requested target:

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
- A **Subject whose Schema has no Mapping** for the target is absent entirely. Its IRI can still appear
  as the target of a relation from a mapped Subject — untyped — exactly as a missing Schema behaves in
  the native projection.
- **No page-metadata triples** are emitted (no page node, `neo:hasSubject`, etc.).
- Quads are placed in the **per-page named graph**, like the native projection, so the same per-page
  sync infrastructure works for an ontology store.

## Selecting a projection

The RDF export surfaces take an optional `projection`:

`GET /rest.php/neowiki/v0/page/{pageId}/rdf?projection=edm`

- `projection` is `native` (the default, unchanged behaviour) or a target that some Mapping page
  declares.
- An unknown target returns **`400`** listing the known projections.

```sh
# Native (default):
curl 'https://wiki.example/rest.php/neowiki/v0/page/42/rdf'
# EDM ontology projection:
curl 'https://wiki.example/rest.php/neowiki/v0/page/42/rdf?projection=edm&format=turtle'
```

The bulk dump takes the same option:

```sh
php maintenance/run.php NeoWiki:DumpRdf --projection=edm > dump.trig
```

## Authoring a Mapping

1. Create a page in the `Mapping:` namespace (any title — the title is just the Mapping's name).
2. Declare the `prefixes` you will use, set the `schema` and `target`, and give the Subject a
   `subject.class`.
3. Map the properties you want to publish. Only listed properties are projected.
4. Save. Structural errors, unresolvable/unsafe terms, and a duplicate `(schema, target)` are reported
   on save.
5. Export a page of that Schema with `?projection=<target>` to see the result.
