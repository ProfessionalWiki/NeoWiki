---
title: Author an ontology mapping
order: 2
---

# Author an ontology mapping

A Mapping page defines an ontology projection of your wiki's Subjects: the same data expressed in EDM, CIDOC-CRM, or
another vocabulary. The built-in native projection needs no mapping.

The format is specified in [Ontology Mapping](../rdf/ontology-mapping.md), and
[Person to EDM](../rdf/person-to-edm.md) walks one end to end.

## 1. Create the Mapping

Go to **Special:Mappings** and choose **Create**. The name you give becomes the projection name, and the page
`Mapping:<Name>` is created pre-filled with:

```json
{"version": 1, "prefixes": {}, "schemas": {}}
```

Editing needs the `neowiki-mapping-edit` right, which logged-in users have by default.

## 2. Write the mapping

There is no form-based editor: the content is JSON, edited in the normal page editor and syntax-highlighted when the
CodeEditor extension is installed. Saving validates the mapping and reports errors, and the page's read view shows a
summary of the mapped Schemas and prefixes.

Keys under `schemas` are your wiki's Schema names, and the keys under a Schema entry's `properties` are that Schema's
property names.

The fastest start is copying a shipped example: `Mapping:EDM` (flat term substitution) or `Mapping:CIDOC-CRM`
(synthesizes intermediate event nodes). Read their raw JSON on the sandbox —
[EDM](https://neowiki.dev/wiki/Mapping:EDM?action=raw),
[CIDOC-CRM](https://neowiki.dev/wiki/Mapping:CIDOC-CRM?action=raw) — or
[in the repository](https://github.com/ProfessionalWiki/NeoWiki/tree/master/DemoData/Mapping).

## 3. Use the projection

Exports are produced on demand from current data, so a saved Mapping — or an edit to one — takes effect right away.

* On any page with Subjects, the **Data** tab offers per-projection RDF downloads, as Turtle or TriG.
* Programmatically: `rest.php/neowiki/v0/page/{id}/rdf?projection=<Name>`
* In bulk: `php maintenance/run.php NeoWiki:DumpRdf --projection=<Name>`

## Live SPARQL querying is separate

To query the projection over SPARQL, configure it as a store entry in `$wgNeoWikiSparqlStores`, where
[several projections can share one store](../operations/installation.md#several-projections-in-one-store).

After a Mapping change such a store keeps the old vocabulary until it is rebuilt. **Special:GraphStores** flags it as
[stale](../operations/maintenance.md#stale-stores) and offers **Rebuild**; `$wgNeoWikiAutoRebuildOnMappingChange`
automates it.

This guide is new and deliberately short. Tell us where it falls short:
https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
