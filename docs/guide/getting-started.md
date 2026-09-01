---
title: Getting started
order: 1
---

# Getting started

In one sitting you will define a Schema, put a Subject on a page, render it, and query it. Work on the public sandbox
at [neowiki.dev](https://neowiki.dev) — it resets periodically, so edit freely — or on
[your own wiki](../operations/installation.md).

## 1. Create a Schema

A Schema describes a kind of thing: a Person, a Building, a Painting. Go to **Special:Schemas** and create one, then
use **Add property definition** for each fact you want to record, giving it a name and a Property Type — text, number,
date, relation, and so on. The [Glossary](../glossary.md) defines these concepts.

## 2. Create a Subject

A Subject is one thing described with a Schema. Open any wiki page and pick **Create subject** from the page tools.
The dialog lets you use an existing Schema or create one in the flow. Fill in the values and save.

## 3. See the data

The page's Main Subject renders automatically as an infobox; the **Data** tab lists every Subject on the page.

## 4. Render a View in wikitext

Source edit any page and add:

```
{{#view:}}
```

With no id it renders the page's Main Subject. [Parser Functions](../authoring/parser-functions.md) covers the rest.

## 5. Query

Add a query to any page. This one lists the stored pages, whatever your data model:

```
{{#cypher_raw: MATCH (p:Page) RETURN p.name }}
```

It needs the Neo4j backend, which the demo and Docker stacks include.

## Where next

* [Author an ontology mapping](author-an-ontology-mapping.md) — publish your Subjects in EDM, CIDOC-CRM, or another
  vocabulary
* [Keep your wiki current](keep-your-wiki-current.md) — upgrade an evaluation wiki
* [Glossary](../glossary.md) — the vocabulary the UI and these docs share

This guide is new and deliberately short. Tell us where it falls short:
https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
