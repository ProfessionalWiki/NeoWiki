---
title: Author an ontology mapping
order: 2
---

# Author an ontology mapping

A Mapping page defines an ontology projection of your wiki's Subjects: the same data expressed in EDM, CIDOC-CRM, or
another vocabulary. The format is specified in [Ontology Mapping](../rdf/ontology-mapping.md), and
[Person to EDM](../rdf/person-to-edm.md) walks one end to end.

## 1. Create the Mapping

Go to **Special:Mappings** and choose **Create**. The name you give becomes the projection name, and the page
`Mapping:<Name>` is created with an empty mapping.

## 2. Write the mapping

The fastest start is copying a shipped example and swapping in your own Schema and property names: `Mapping:EDM`
(flat term substitution) or `Mapping:CIDOC-CRM` (synthesizes intermediate event nodes). Read their raw JSON on the
sandbox — [EDM](https://neowiki.dev/wiki/Mapping:EDM?action=raw),
[CIDOC-CRM](https://neowiki.dev/wiki/Mapping:CIDOC-CRM?action=raw) — or
[in the repository](https://github.com/ProfessionalWiki/NeoWiki/tree/master/DemoData/Mapping).

There is no form-based editor: the content is JSON, edited in the normal page editor. Saving validates the mapping
and reports errors, and the page's read view shows a summary of the mapped Schemas and prefixes.

## 3. See the projection

Exports are produced on demand from current data, so a saved Mapping — or an edit to one — takes effect right away.
On any page with Subjects, the **Data** tab offers per-projection RDF downloads, as Turtle or TriG. Per-Subject
exports, the REST endpoint, and the bulk dump are in [RDF Export](../rdf/rdf-export.md).

## 4. Query the projection over SPARQL

Add the projection as a store entry in
[`$wgNeoWikiSparqlStores`](../operations/installation.md#several-projections-in-one-store). After a Mapping change,
such a store keeps the old vocabulary until it is rebuilt: **Special:GraphStores** flags it as
[stale](../operations/maintenance.md#stale-stores) and offers **Rebuild**.

Tell us what this guide is missing: https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
