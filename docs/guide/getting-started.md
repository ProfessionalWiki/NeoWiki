---
title: Getting started
order: 1
---

# Getting started

In one sitting: from an empty page to structured, queryable data. Work on the public sandbox at
[neowiki.dev](https://neowiki.dev) — create an account first (anonymous editing is off), then edit freely; it is a
sandbox and resets periodically — or on [your own wiki](../operations/installation.md).

## 1. Create a Schema

A Schema describes a kind of thing: a Person, a Building, a Painting. Go to **Special:Schemas** and create one, then
use **Add property definition** for each fact you want to record, giving it a name and a Property Type — text,
number, date, relation, and so on.

## 2. Create a Subject

A Subject is one thing described with a Schema. Open any wiki page and pick **Create subject** from the page tools,
fill in the values, and save. The page's Main Subject renders automatically as an infobox, and the page's **Data**
tab lists every Subject on the page.

## 3. Render a View in wikitext

Source edit any page and add:

```
{{#view:}}
```

With no id it renders the page's Main Subject. [Parser Functions](../authoring/parser-functions.md) covers the
rest, and Layouts control which properties a View shows ([Glossary](../glossary.md#layout)).

## 4. Query

Add a query to any page. This one lists the stored pages, whatever your data model:

```
{{#cypher_raw: MATCH (p:Page) RETURN p.name }}
```

Needs a Neo4j backend, which the sandbox and the Docker install have.

## Where next

* [Author an ontology mapping](author-an-ontology-mapping.md) — publish your Subjects in EDM, CIDOC-CRM, or another
  vocabulary
* [Glossary](../glossary.md) — the vocabulary the UI and these docs share

Tell us what this guide is missing: https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
