---
title: Person to EDM
order: 1
---
# Worked example: projecting a Person to EDM

End-to-end walkthrough of the [Ontology Mapping](../rdf/ontology-mapping.md) v1 projection: a NeoWiki-native `Person`
Schema, an EDM Mapping, and a demo page projected into
[Europeana Data Model](https://pro.europeana.eu/page/edm-documentation) (EDM) RDF, shown against the native projection.

It projects the EDM column of a small "person to many standards" toy model. Everything here ships as
[demo data](../../DemoData/), so a wiki with the demo data imported (`php maintenance/run.php NeoWiki:ImportDemoData`)
reproduces it — up to instance-specific page IDs and base URI (and, in the page metadata, timestamps and last editor).

> **Scope: the near-1:1 tier only.** Event-based ontologies like CIDOC-CRM need the intermediate-node synthesis that v1
> does not do — see [Known next tier: CIDOC-CRM](#known-next-tier-cidoc-crm).

## The toy model

| Neutral Person field | Example value | EDM target |
|---|---|---|
| Name | "Pablo Picasso" | `foaf:name` *(not in v1 — see [Findings](#findings))* |
| Gender | "Male" | `rdaGr2:gender` literal |
| Birth date | 1881-10-25 | `rdaGr2:dateOfBirth` (`xsd:date`) |
| Birth place | Málaga | `rdaGr2:placeOfBirth` → an `edm:Place` |
| Description | biography text | `skos:note` literal |
| Source | "A Life of Picasso I…" (ISBN) | **n/a in EDM** — unmapped |

Prefixes: `edm: http://www.europeana.eu/schemas/edm/`, `rdaGr2: http://rdvocab.info/ElementsGr2/`,
`skos: http://www.w3.org/2004/02/skos/core#`.

## 1. The Schemas

The demo `Person` Schema ([`DemoData/Schema/Person.json`](../../DemoData/Schema/Person.json)) carries the flat toy-model
fields. Abbreviated (property descriptions and the multi-valued `Also known as` field omitted):

```json
{
    "propertyDefinitions": {
        "Gender":      { "type": "text" },
        "Birth date":  { "type": "date" },
        "Birth place": { "type": "relation", "relation": "Born in", "targetSchema": "City" },
        "Source":      { "type": "text" },
        "Description": { "type": "text" }
    }
}
```

The demo `City` Schema ([`DemoData/Schema/City.json`](../../DemoData/Schema/City.json)) is used unchanged.

## 2. The Mapping

One Mapping page, **[`Mapping:EDM`](../../DemoData/Mapping/EDM.json)**, holds an entry per mapped Schema; its title
(`EDM`) is the projection name (see [Ontology Mapping](../rdf/ontology-mapping.md) for the format). Abbreviated to the
two entries this walkthrough uses — the shipped file also maps `Artwork` and `Artist` and declares the
`dc`/`dcterms`/`foaf`/`xsd` prefixes those need:

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
                "Gender":      { "predicate": "rdaGr2:gender" },
                "Birth date":  { "predicate": "rdaGr2:dateOfBirth" },
                "Birth place": { "predicate": "rdaGr2:placeOfBirth" },
                "Description": { "predicate": "skos:note", "lang": "en" }
            }
        },
        "City": {
            "subject": { "class": "edm:Place" },
            "properties": {}
        }
    }
}
```

`prefixes` are page-level. The `City` entry maps **only the class** (`edm:Place`).

## 3. The demo page

[`Pablo_Picasso`](../../DemoData/Subject/Pablo_Picasso.json) is a `Person` main Subject carrying every toy-model value,
its `Birth place` relation pointing at [`Málaga`](../../DemoData/Subject/Málaga.json) (a `City`).

## 4. Running the projection

Per page, via the [RDF export endpoint](../rdf/rdf-export.md#endpoint) — the `projection` query parameter selects the
vocabulary:

```sh
# native (default):
curl '.../rest.php/neowiki/v0/page/<id>/rdf?format=turtle'
# EDM:
curl '.../rest.php/neowiki/v0/page/<id>/rdf?projection=EDM&format=turtle'
```

In bulk, via the dump script (one named graph per page):

```sh
php maintenance/run.php NeoWiki:DumpRdf --projection=EDM > dump-edm.trig
```

## 5. Native vs EDM output

Real output from the demo wiki (Turtle; shared prefix header trimmed — the `neo*` CURIEs are the
[IRI scheme](../rdf/rdf-export.md#iri-scheme)). The **native** projection of `Pablo_Picasso` —
NeoWiki's own vocabulary, lossless, with page metadata and the two-layer relation:

```turtle
<.../page/115> a neo:Page;
    neo:pageName "Pablo Picasso";
    dcterms:created "2026-07-11T23:22:20Z"^^xsd:dateTime;
    dcterms:modified "2026-07-11T23:22:20Z"^^xsd:dateTime;
    neo:lastEditor "NeoWiki";
    neo:mainSubject neo-subj:s2picasso2aaaa2;
    neo:hasSubject neo-subj:s2picasso2aaaa2.
neo-subj:s2picasso2aaaa2 a neo-schema:Person;
    rdfs:label "Pablo Picasso";
    neo-prop:Description "Spanish painter, sculptor, and printmaker who co-founded the Cubist movement…";
    neo-prop:Gender "Male";
    neo-prop:Birth_date "1881-10-25"^^xsd:date;
    neo-prop:Source "A Life of Picasso I: The Prodigy: 1881-1906 by John Richardson (ISBN 978-0375711497)";
    neo-prop:Born_in neo-subj:s2birthcity2aa2.
neo-rel:r2picasso2city2 a neo:Relation;
    neo:source neo-subj:s2picasso2aaaa2;
    neo:target neo-subj:s2birthcity2aa2;
    neo:relationType neo-prop:Born_in.
```

The **EDM** projection of the same page — target vocabulary only, no page metadata, no relation reification:

```turtle
neo-subj:s2picasso2aaaa2 a edm:Agent;
    rdfs:label "Pablo Picasso";
    skos:note "Spanish painter, sculptor, and printmaker who co-founded the Cubist movement…"@en;
    rdaGr2:gender "Male";
    rdaGr2:dateOfBirth "1881-10-25"^^xsd:date;
    rdaGr2:placeOfBirth neo-subj:s2birthcity2aa2.
```

The **EDM** projection of `Málaga` (its own page) — a typed `edm:Place`:

```turtle
neo-subj:s2birthcity2aa2 a edm:Place;
    rdfs:label "Málaga".
```

What changed, native → EDM:

- The Subject IRI is unchanged (`neo-subj:s2picasso2aaaa2`): the entity stays the wiki's own, only the vocabulary is
  EDM.
- `rdf:type` and each mapped predicate are substituted; `rdfs:label` is a shared term, kept verbatim.
- The datatype comes from the value mapper, not the Mapping: `"1881-10-25"^^xsd:date` is identical in both. A Mapping
  property may override it with `datatype`.
- The birth-place relation becomes one direct triple. Its EDM predicate keys on the **property name** `Birth place`
  (`rdaGr2:placeOfBirth`); the native predicate keys on the **relation-type name** `Born in` (`neo-prop:Born_in`).
- Absent from EDM: page metadata, the `neo:Relation` reification node, and unmapped properties (`Source`, and any
  native-only property).

### Per-page vs bulk: the relation target's type

In the **per-page** EDM export of `Pablo_Picasso`, `neo-subj:s2birthcity2aa2` (Málaga) is the object of
`rdaGr2:placeOfBirth` but appears as a **bare IRI** — no `a edm:Place` type, no `rdfs:label`: those triples live in
Málaga's own page graph. The **bulk** `DumpRdf --projection=EDM` holds both named graphs, so across the dataset Málaga
is a typed, labelled `edm:Place`:

```trig
<.../graph/EDM/page/115> { neo-subj:s2picasso2aaaa2 a edm:Agent; … rdaGr2:placeOfBirth neo-subj:s2birthcity2aa2 }
<.../graph/EDM/page/116> { neo-subj:s2birthcity2aa2 a edm:Place; rdfs:label "Málaga" }
```

The native projection of the same pages lands in sibling `.../graph/native/page/{id}` graphs, so both projections can
share one triple store. A consumer that needs a relation target's type or label must load the target's graph.

## Findings

1. **The name projects only as `rdfs:label`; `foaf:name` is unreachable in v1.** A Subject's name is its built-in label,
   not a property; the projector always emits it as `rdfs:label`, and v1 has no facility to also map the label to
   another predicate. The toy model's `Name → foaf:name` therefore cannot be produced. Tracked at
   [#996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996).
2. **A `select` value projects its stored option id, not its label.** The v1 value mapper emits the stored id, so a
   `select` `Gender` would carry an opaque `o1…` id instead of `"Male"`. The demo uses a `text` property to get a
   meaningful literal; a select→label (or select→`skos:Concept` IRI) projection is later work.

## Querying via SPARQL

Configure a SPARQL store for the `EDM` projection and each save is projected into it, queryable through the SPARQL
read surface — see [Ontology Mapping](../rdf/ontology-mapping.md) and [RDF Export](../rdf/rdf-export.md). For an ad-hoc
load, feed the `DumpRdf --projection=EDM` output into any SPARQL engine (e.g. a local QLever).

## Known next tier: CIDOC-CRM

The toy model's CIDOC-CRM column is out of scope for v1. CIDOC-CRM mediates birth through a synthesized `E67_Birth`
event node with no counterpart in NeoWiki's flat data, which v1's term substitution cannot mint. Choosing a formalism
that can is the open mapping-formalism question ([OntologyMapping.md Q1](../planning/OntologyMapping.md#open-questions),
[#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995)).
</content>
</invoke>
