---
title: Ontology Mapping
order: 2
---
# Ontology Mapping

Alongside the built-in [native projection](rdf-export.md), which needs no mapping, you can project to an
established ontology such as EDM by defining an **ontology mapping**.

The native projection and each ontology mapping are sibling projections of the same source data. Several can
run at once: an export request selects one by name, and a SPARQL store can be configured for any of them.

The design and its open questions live in [planning/OntologyMapping.md](../planning/OntologyMapping.md); this
page is the as-built reference for the shipped v1. The
[Person-to-EDM worked example](../examples/person-to-edm.md) walks through a Person Schema projected to EDM,
with native and mapped output side by side and the current gaps.

> **v1 scope.** It covers only *near-1:1* term substitution — a target class per Subject and one target
> predicate per mapped property — and does **not** synthesize the intermediate event nodes that
> CIDOC-CRM-style ontologies need. The stored `"version": 1` format may change; see the
> [open questions](../planning/OntologyMapping.md#open-questions).

## Ontology Mappings are wiki pages

A Mapping is a page in the **`Mapping:` namespace** with content model `NeoWikiMapping` (JSON), gated by the
`neowiki-mapping-edit` right. There is **one Mapping page per target ontology**, and the page title is the
projection name you pass to the export surfaces: the page `Mapping:EDM` defines the `EDM` projection. The
`Special:Mappings` page lists every Mapping on the wiki.

A single page holds an entry for **every mapped Schema** — map a Schema to an ontology by adding an entry to
that ontology's page, not by creating a page. A Schema maps to several ontologies through one entry on each
ontology's page.

The name **`native`** is reserved for the built-in [native projection](rdf-export.md), so a `Mapping:Native`
page is rejected on save.

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
| `datatype` | no | Absolute IRI or CURIE that overrides the literal's datatype. Mutually exclusive with `lang`. |

### CURIEs, IRIs, and safety

A `class`, `predicate`, or `datatype` is either a **CURIE** `prefix:local` whose prefix is declared in
`prefixes`, or an **absolute IRI** containing `://`. A CURIE with an undeclared prefix is rejected;
non-authority schemes (`urn:`, `mailto:`, …) are out of scope for v1.

Terms are reproduced verbatim, never percent-encoded. A term or a declared prefix namespace that would expand
to an IRI containing an IRIREF-illegal character (`< > " { } | ^ \` backtick, space, control characters) is
**rejected at save time**, as is a `lang` tag that is not BCP-47-shaped and a property entry that sets both
`lang` and `datatype`.

The same checks re-run at **projection time**: a class, predicate, datatype, or prefix that does not re-expand
safely is dropped, an invalid language tag falls back to a plain literal, and each is logged. The projection
degrades rather than aborting the export.

## What gets emitted

For each Subject on a page whose Schema has an entry on the requested projection's Mapping page:

- `rdf:type <subject.class>`.
- `rdfs:label "<label>"` — the Subject's label, always.
- One triple per mapped property **value**; multi-valued properties repeat the predicate. Unmapped properties
  are absent.
- A **relation** value becomes a direct triple to the target Subject's IRI. No `neo:Relation` reification node
  and no relation qualifiers are projected.

v1 boundaries:

- **Subject IRIs stay native** (`neo-subj:`): only the vocabulary comes from the target ontology. Cross-linking
  to external entities (`owl:sameAs`, reconciliation) is later work.
- A **Subject whose Schema has no entry** on the Mapping page is absent, but its IRI can still appear as a
  bare IRI — no type, no label — as the target of a relation from a mapped Subject.
- **No page-metadata triples** are emitted (no page node, `neo:hasSubject`, etc.).
- Quads go in the per-page named graph for this target (`$base/graph/{target}/page/{id}`), where `{target}` is
  the projection name.

## Selecting a projection

The RDF export surfaces — the per-page and per-Subject endpoints and the `DumpRdf` bulk dump — take a
`projection` parameter whose value is a projection name — a Mapping page title without the `Mapping:`
prefix (`EDM`), or `native` for the built-in projection. See
[RDF Export](rdf-export.md#endpoint) for the contract.

## Authoring a Mapping

1. Create a page in the `Mapping:` namespace named after the target ontology (`Mapping:EDM`), or edit the
   existing one.
2. Declare the page-level `prefixes` you will use.
3. Add an entry under `schemas` for each Schema to project: give the Subject a `subject.class` and map the
   properties to publish.
4. Save. Structural errors and unresolvable or unsafe terms are reported on save.
5. Export a page of a mapped Schema with `?projection=EDM` (the page title without the `Mapping:` prefix).
