---
title: Person to EDM
order: 1
---
# Worked example: projecting a Person to EDM

This is an end-to-end walkthrough of the [Ontology Mapping](../rdf/ontology-mapping.md) v1
projection: a NeoWiki-native `Person` Schema, an EDM Mapping, and a demo page, projected into
[Europeana Data Model](https://pro.europeana.eu/page/edm-documentation) (EDM) RDF and compared with the
native projection.

It implements the EDM column of the shared "Neutral Person to Many Standards" toy model (takin, ECHOLOT
WP2/WP3) — the first end-to-end proof of the mapping mechanism proposed in
[planning/OntologyMapping.md](../planning/OntologyMapping.md). Everything here ships as
[demo data](../../DemoData/), so a fresh wiki with the demo data imported reproduces it — up to
instance-specific page IDs and base URI (and, in the page metadata, timestamps and last editor).

> **Scope: the near-1:1 tier only.** EDM is flat and property-centric, so the mapping is term
> substitution. The toy model's CIDOC-CRM column is event-based (birth becomes an `E67_Birth` node
> between the person and the date/place) and needs the intermediate-node **synthesis** that v1
> deliberately does not do — see [Known next tier](#known-next-tier-cidoc-crm) below.

## The toy model

| Neutral Person field | Example value | EDM target |
|---|---|---|
| Name | "Pablo Picasso" | `foaf:name` — **see the finding below**; v1 emits the name only as `rdfs:label` |
| Gender | "Male" | `rdaGr2:gender` literal |
| Birth Date | 1881-10-25 | `rdaGr2:dateOfBirth` (`xsd:date`) |
| Birth Location | Málaga (Getty TGN 7007942) | `rdaGr2:placeOfBirth` → an `edm:Place` |
| Description | biography text | `skos:note` literal |
| Source | "A Life of Picasso I…" (ISBN) | **n/a in EDM** — deliberately unmapped |

Prefixes: `edm: http://www.europeana.eu/schemas/edm/`, `rdaGr2: http://rdvocab.info/ElementsGr2/`,
`skos: http://www.w3.org/2004/02/skos/core#`.

## 1. The Schemas

The demo `Person` Schema ([`DemoData/Schema/Person.json`](../../DemoData/Schema/Person.json)) carries the
flat toy-model fields. It is deliberately **not** a parallel schema: the existing rich, event-based Bach
family data (birth as its own `Birth` Subject) keeps working; the flat `Birth date` / `Birth place`
fields are the form the near-1:1 EDM projection consumes.

```json
{
    "Gender":      { "type": "text" },
    "Birth date":  { "type": "date" },
    "Birth place": { "type": "relation", "relation": "Born in", "targetSchema": "City" },
    "Source":      { "type": "text" },
    "Description": { "type": "text" }
}
```

Birth Location is a **relation** to a `City` Subject. The demo `City` Schema
([`DemoData/Schema/City.json`](../../DemoData/Schema/City.json)) is used unchanged.

## 2. The Mapping

One Mapping page, **[`Mapping:EDM`](../../DemoData/Mapping/EDM.json)**, targets EDM and holds an entry
for every mapped Schema. The page title (`EDM`) is the projection name. Abbreviated to the two entries
this walkthrough uses (the shipped file also maps `Artwork` and `Artist`, and declares the extra
`dc`/`dcterms`/`foaf`/`xsd` prefixes those need):

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

`prefixes` are page-level, shared by every entry. The **City** entry maps **only the class**
(`edm:Place`), with no property mappings. For the birthplace use case, all EDM needs from Málaga is a labelled `edm:Place` that
`rdaGr2:placeOfBirth` can point at. The City's `Country`/`Population`/`Website` have no near-1:1 EDM
`Place` predicate in the flat tier (place geography would use `wgs84_pos`, outside the toy model), so
they are deliberately unmapped — see the [findings](#findings).

## 3. The demo page

[`Pablo_Picasso`](../../DemoData/Subject/Pablo_Picasso.json) is a `Person` main Subject with every
toy-model value, its `Birth place` relation pointing at [`Málaga`](../../DemoData/Subject/Málaga.json)
(a `City`).

## 4. Running the projection

Per page, via the [RDF export endpoint](../rdf/rdf-export.md#endpoint) (the `projection` query
parameter selects the vocabulary):

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

Real output from the demo wiki (Turtle; shared prefix header trimmed). **Native** projection of
`Pablo_Picasso` — NeoWiki's own vocabulary, lossless, with page metadata and the two-layer relation:

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

**EDM** projection of the same page — target vocabulary only, no page metadata, no relation
reification:

```turtle
neo-subj:s2picasso2aaaa2 a edm:Agent;
    rdfs:label "Pablo Picasso";
    skos:note "Spanish painter, sculptor, and printmaker who co-founded the Cubist movement…"@en;
    rdaGr2:gender "Male";
    rdaGr2:dateOfBirth "1881-10-25"^^xsd:date;
    rdaGr2:placeOfBirth neo-subj:s2birthcity2aa2.
```

**EDM** projection of `Málaga` (its own page) — typed `edm:Place`:

```turtle
neo-subj:s2birthcity2aa2 a edm:Place;
    rdfs:label "Málaga".
```

What changed, native → EDM:

- The Subject IRI is **unchanged** (`neo-subj:s2picasso2aaaa2`): the entity stays the wiki's own; only
  the *vocabulary* is EDM. Sibling projections mint identical IRIs.
- `rdf:type` `neo-schema:Person` → `edm:Agent`; `Málaga` → `edm:Place`.
- `rdfs:label` is kept verbatim (a standard term EDM also uses).
- Each mapped property's predicate is substituted: `neo-prop:Gender` → `rdaGr2:gender`, `Birth_date` →
  `rdaGr2:dateOfBirth`, `Description` → `skos:note` (now carrying `@en`).
- The **datatype is preserved** by the value mapper, not the Mapping: `"1881-10-25"^^xsd:date` is
  identical in both projections.
- The birth-place **relation** becomes a direct triple `rdaGr2:placeOfBirth neo-subj:s2birthcity2aa2`.
  Note the predicate is keyed on the **property name** `Birth place`, whereas the native predicate
  (`neo-prop:Born_in`) uses the **relation-type name** `Born in`.
- **Absent in EDM:** page metadata (`neo:Page`, `neo:hasSubject`), the `neo:Relation` reification node,
  the `Source` property (unmapped), and any native-only property. Conformant EDM is the point.

### Per-page vs bulk: the relation target's type

In the **per-page** EDM export of `Pablo_Picasso`, `neo-subj:s2birthcity2aa2` (Málaga) appears as the
object of `rdaGr2:placeOfBirth` but is **untyped** — its `a edm:Place` triple lives in Málaga's own
page graph. The **bulk** `DumpRdf --projection=EDM` contains both named graphs, so across the dataset
Málaga is a typed `edm:Place`:

```trig
<.../graph/EDM/page/115> { neo-subj:s2picasso2aaaa2 a edm:Agent; … rdaGr2:placeOfBirth neo-subj:s2birthcity2aa2 }
<.../graph/EDM/page/116> { neo-subj:s2birthcity2aa2 a edm:Place; rdfs:label "Málaga" }
```

The native projection of the same pages lands in sibling `.../graph/native/page/{id}` graphs, so both projections can
share one triple store.

(The dump also projects the other demo `Person`/`City` Subjects — Bach persons as `edm:Agent`, the
other cities as `edm:Place` — since the Mapping applies to every Subject of its Schema.)

## Findings

The point of this exercise is to surface gaps honestly. From doing it:

1. **The name is only `rdfs:label`; `foaf:name` cannot be emitted (v1 format gap).** A NeoWiki Subject's
   name is its built-in **label**, not a property. The projector always emits it as `rdfs:label`, and
   v1 has **no facility to map the label to an additional predicate**, so the toy model's
   `Name → foaf:name` cannot be produced. Adding a redundant "Name" property purely to force `foaf:name`
   out would duplicate the label and misrepresent the model, so we did not. This is a real limitation of
   the v1 format: it needs a way to say "also emit the label as `<predicate>`" (and, more generally, to
   map the label per target). Recorded for the mapping-formalism discussion
   ([#996](https://github.com/ProfessionalWiki/NeoWiki/discussions/996)).
2. **A `select` value would project its option id, not its label.** `Gender` is a controlled
   vocabulary, so a `select` property is the natural model. But a select value is stored as the option
   **id**, and the v1 value mapper emits that id as the literal — so `rdaGr2:gender` would carry an
   opaque `o1…` id, not `"Male"`. We used a **text** property to get a meaningful literal. A
   select→label (or select→`skos:Concept` IRI) projection is future work.
3. **EDM's flat `Place` vocabulary is thin.** The City entry maps only the class. Country/Population/Website
   have no obvious near-1:1 EDM `Place` predicate without stretching semantics; coordinates would need a
   geo vocabulary (`wgs84_pos`) beyond the toy model. Class-only is the honest v1 result and is exactly
   what the birthplace reference needs.
4. **Relation predicates key on the property name, literal predicates likewise.** The mapping's
   `properties` keys are **property names** (`Birth place`), while the native projection's relation
   predicate uses the **relation-type name** (`Born in`). Authors of a Mapping must use property names,
   not relation-type names — worth stating in the format reference if it trips people up.
5. **Untyped relation targets in a per-page export are expected.** As above; only the bulk dump (or a
   store holding all graphs) types the target. Consumers that need the target's type must load its graph.

None of these blocked the exercise; the near-1:1 EDM projection works end to end. (1) is the one that is
a genuine v1 **format** gap rather than a deliberate boundary.

## Querying via SPARQL

v1 ships the projection and the export surfaces (endpoint + dump). It does **not** yet load the RDF into
a triple store — the pluggable SPARQL store that would let you run EDM SPARQL against this data is
[#586](https://github.com/ProfessionalWiki/NeoWiki/issues/586), which consumes the
`newRdfProjection(name)` seam this work exposes. Until then, feed the `DumpRdf --projection=EDM` output
into any SPARQL engine (e.g. a local QLever) to query the EDM projection. Once #586 lands, a store can be
configured to hold the `EDM` projection directly and be queried in EDM terms.

## Known next tier: CIDOC-CRM

The toy model's CIDOC-CRM column is **out of scope for v1**. CIDOC-CRM mediates birth through an event:
a person's birth date and place hang off a synthesized `E67_Birth` node
(`E21_Person → P98i_was_born → E67_Birth → P4_has_time-span / P7_took_place_at`). That node has no
counterpart in NeoWiki's flat data; the mapping must **mint** it, coordinating several flat fields onto
one shared node. Expressing that path expansion and node synthesis is the hard tier
([OntologyMapping.md](../planning/OntologyMapping.md) Q2), and the open mapping-formalism question
([OntologyMapping.md Q1](../planning/OntologyMapping.md#open-questions),
[#995](https://github.com/ProfessionalWiki/NeoWiki/issues/995)) is precisely about choosing a formalism
that can. EDM proves the mechanism end to end; CIDOC-CRM is the next tier it must grow into.
