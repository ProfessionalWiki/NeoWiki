---
title: Ontology Mapping
order: 2
---
# Ontology Mapping

Alongside the built-in [native projection](rdf-export.md), which needs no mapping, you can project to an
established ontology such as EDM or CIDOC-CRM by defining an **ontology mapping**.

The native projection and each ontology mapping are sibling projections of the same source data. Several can
run at once: an export request selects one by name, and a SPARQL store can hold
[several side by side](../operations/installation.md#several-projections-in-one-store).

The design and its open questions live in [planning/OntologyMapping.md](../planning/OntologyMapping.md); this
page is the as-built reference for the shipped format. The
[Person-to-EDM worked example](person-to-edm.md) walks through a Person Schema projected to EDM, with native
and mapped output side by side.

> **Scope.** A mapping reshapes the data as well as the vocabulary: it can **synthesize** the intermediate
> event nodes a target like CIDOC-CRM routes its paths through, and it can **contract** structure a flat
> target does not want by contributing a Subject's values to the Subject it points at. Out of scope: RDF
> **import** (a mapping drives export only) and contributions across more than one relation hop. The stored
> `"version": 1` format may change; see the [open questions](../planning/OntologyMapping.md#open-questions).

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
        "crm": "http://www.cidoc-crm.org/cidoc-crm/",
        "rdaGr2": "http://rdvocab.info/ElementsGr2/"
    },
    "schemas": {
        "Person": {
            "subject": { "class": "crm:E21_Person", "labelPredicate": "rdaGr2:nameOfThePerson" },
            "nodes": {
                "birth": { "class": "crm:E67_Birth", "linkPredicate": "crm:P98i_was_born" },
                "birthTimespan": {
                    "class": "crm:E52_Time-Span",
                    "linkPredicate": "crm:P4_has_time-span",
                    "parent": "birth"
                }
            },
            "properties": {
                "Birth date":  { "predicate": "crm:P82_at_some_time_within", "node": "birthTimespan" },
                "Birth place": { "predicate": "crm:P7_took_place_at", "node": "birth" }
            }
        },
        "Birth": {
            "contributions": {
                "Brought into life": {
                    "Date":          { "predicate": "rdaGr2:dateOfBirth" },
                    "Took place at": { "predicate": "rdaGr2:placeOfBirth" }
                }
            }
        }
    }
}
```

Top level:

| Field | Required | Meaning |
|---|---|---|
| `version` | yes | Format version. Must be `1`. |
| `prefixes` | no | Prefix label → namespace IRI, shared by every entry, used to expand the CURIEs below. |
| `schemas` | yes | Native Schema name → the entry that projects its Subjects (see below). May be empty. A Schema's existence is not checked at save time; the page's read view shows a missing Schema as a red link. |

Each **schema** entry (a value in `schemas`) needs at least one of `subject` and `contributions`:

| Field | Required | Meaning |
|---|---|---|
| `subject` | with `properties`/`nodes` | How the Subject itself projects (see below). Leave it out for an entry that only contributes to other Subjects. |
| `nodes` | no | Node key → an intermediate node the projection synthesizes (see below). Keys match `^[A-Za-z_][A-Za-z0-9_-]*$` and appear in the node's IRI. At most 64. |
| `properties` | no | NeoWiki property name → how to project it (see below). At most 500. |
| `contributions` | no | Relation name → values this Schema sends to the Subjects that relation points at (see below). At most 32. |

Each **subject** entry:

| Field | Required | Meaning |
|---|---|---|
| `class` | yes | The `rdf:type` given to each Subject of the Schema. A CURIE or an absolute IRI. |
| `labelPredicate` | no | An **additional** predicate carrying the Subject's label, for a target ontology with a label term of its own (`foaf:name`, …). `rdfs:label` is emitted either way. |

Each **node** entry (a value in `nodes`):

| Field | Required | Meaning |
|---|---|---|
| `class` | yes | The `rdf:type` given to each instance of the node. |
| `linkPredicate` | yes | Predicate of the triple between the node instance and its anchor — the parent node's instance, or the Subject. |
| `linkDirection` | no | `toNode` (default) emits `<anchor> <linkPredicate> <node>`; `fromNode` emits `<node> <linkPredicate> <anchor>`. |
| `parent` | no | Another node key, making this node hang off that node instead of off the Subject. |
| `per` | no | `subject` (default) for one instance shared by every property attached to it, or `value` for one instance per value. A `per: "value"` node takes at most one property and cannot be a `parent`. |

Each **property** entry:

| Key | Required | Meaning |
|---|---|---|
| `predicate` | yes | Target predicate for the property's values. A CURIE or an absolute IRI. |
| `node` | no | A node key. The property's values then attach to that node's instance instead of to the Subject. |
| `lang` | no | BCP-47-shaped language tag (`^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$`, e.g. `en`, `pt-BR`) applied to the produced literal **when it is a plain string** (text/select values). Ignored for typed literals (numbers, dates, …). Mutually exclusive with `datatype`. |
| `datatype` | no | Absolute IRI or CURIE that overrides the literal's datatype. For a `url` value, which otherwise projects as an IRI object, setting `datatype` forces a literal with that datatype. Mutually exclusive with `lang`. |

Each **contribution** (an entry in `contributions`) is keyed by the name of a relation-typed property on
*this* Schema, and every Subject that property points at receives the contributed values. The value is a
non-empty map (at most 100 entries) of *this* Schema's property names to `predicate`, plus optional `lang` /
`datatype` as above. `node` is not allowed: the triples are about the target Subject.

### CURIEs, IRIs, and safety

A `class`, `linkPredicate`, `predicate`, `labelPredicate`, or `datatype` is either a **CURIE** `prefix:local` whose
prefix is declared in `prefixes`, or an **absolute IRI** containing `://`. A CURIE with an undeclared prefix is
rejected; non-authority schemes (`urn:`, `mailto:`, …) are out of scope.

Terms are reproduced verbatim, never percent-encoded. A term or a declared prefix namespace that would expand
to an IRI containing an IRIREF-illegal character (`< > " { } | ^ \` backtick, space, control characters) is
**rejected at save time**, as is a `lang` tag that is not BCP-47-shaped and a property entry that sets both
`lang` and `datatype`.

The same checks re-run at **projection time**: a class, predicate, datatype, or prefix that does not re-expand
safely is dropped, an unusable node takes everything below it with it, an invalid language tag falls back to a
plain literal, and each is logged. The projection degrades rather than aborting the export.

## What gets emitted

For each Subject on a page whose Schema has an entry on the requested projection's Mapping page:

- `rdf:type <subject.class>`, and the `labelPredicate` triple when one is set.
- `rdfs:label "<label>"` — the Subject's label, always.
- One triple per mapped property **value**, on the Subject or on the node the entry attaches it to;
  multi-valued properties repeat the predicate. Unmapped properties are absent.
- A **relation** value becomes a direct triple to the target Subject's IRI. No `neo:Relation` reification node
  and no relation qualifiers are projected.
- A node instance, with its `rdf:type` and its link triple, **only where a value reached it** — pulling in
  every node on the way down to it, so the output carries no empty event nodes.
- One triple per contributed value, **about each target** of the contribution's relation.

Boundaries:

- **Subject IRIs stay native** (`neo-subj:`): only the vocabulary comes from the target ontology. Cross-linking
  to external entities (`owl:sameAs`, reconciliation) is later work.
- A **Subject whose Schema has no entry** on the Mapping page is absent, but its IRI can still appear as a
  bare IRI — no type, no label — as the target of a relation from a mapped Subject.
- **Contributed triples live in the contributing page's graph**, not the target's, so a per-page export of the
  target does not carry them; a SPARQL store or a bulk dump holding both graphs does. Same rule as a relation
  target's own type and label — see [Per-page vs bulk](person-to-edm.md#per-page-vs-bulk-the-relation-targets-type).
- **No page-metadata triples** are emitted (no page node, `neo:hasSubject`, etc.).
- Quads go in the per-page named graph for this target (`$base/graph/{target}/page/{id}`), where `{target}` is
  the projection name.

### Synthesized-node IRIs

A node instance's IRI is derived from the data, so re-projecting a page produces the same document and the
per-page store sync stays correct:

| Node | IRI |
|---|---|
| `per: "subject"` | `$base/node/{subjectId}/{key}`, each nesting level appending its own key: `$base/node/{subjectId}/{parentKey}/{key}` |
| `per: "value"`, on a relation property | `$base/node/{relationId}` — keyed on the Relation's persistent ID, so the node is the same in every projection |
| `per: "value"`, on a literal property | `$base/node/{subjectId}/{key}/{position}` — the node's own key only, not its parent chain |

They are emitted in full rather than abbreviated: they have no `neo-` prefix of their own.

The position-based case is stable only for unchanged data: reordering or removing a property's values
renumbers the instances after the change.

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
   properties to publish. Where the target routes a property through an intermediate node, declare the node
   and point the property at it. Where the target wants a related Subject's structure flattened onto it, add a
   contribution to the Schema that holds the structure.
4. Save. Structural errors, unresolvable or unsafe terms, and node references that do not resolve are reported
   on save.
5. Export a page of a mapped Schema with `?projection=EDM` (the page title without the `Mapping:` prefix).
