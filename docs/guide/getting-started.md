---
title: Getting started
order: 1
---

# Getting started

Turn a wiki page into structured, queryable data in a few minutes. Try it on the public sandbox at
[neowiki.dev](https://neowiki.dev) (create an account, then experiment freely: it is a sandbox and resets
periodically) or on [your own wiki](../operations/installation.md).

The place to start is **Overview** in the sidebar's **NeoWiki** section: **Special:NeoWiki** lists the wiki's
Schemas, lets you create a Subject with any of them, and links to the other NeoWiki pages.

## 1. Create a Schema

A Schema describes a kind of thing: a Person, a Building, a Painting. Go to **Special:Schemas** and create one, then
use **Add property definition** for each fact you want to record, giving it a name and a Property Type: text,
number, date, relation, and so on.

## 2. Create a Subject

A Subject is one thing described with a Schema.

1. Open the creator: **Create subject** in the sidebar, then pick a Schema — or the **Create** button on a Schema's
   own page, which names it (**Create Person** on `Schema:Person`).
2. Name it with the pencil beside its name, and fill in the values.
3. To point at a Subject that is not there yet, type a name in a relation field and pick **Create "Ada" as a new
   Person**. It opens in place, with the tree beside it to move back.
4. Under **Store the subject on**, choose **This page** where you opened the creator on one, **Another page** you
   pick, or **A new page** titled after the label or after the title you give there.
5. Save. Everything made along the way is saved with it.

A page's Main Subject renders automatically as an infobox, and its **Data** tab lets you view and edit all its
Subjects.

Any page can offer the same creator as a button: add
[`{{#create_subject: schema=Person}}`](../authoring/parser-functions.md#create_subject) to its wikitext.

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

This needs a Neo4j backend; the sandbox and the Docker install have one.

## Where next

* [Author an ontology mapping](author-an-ontology-mapping.md): publish your Subjects in EDM, CIDOC-CRM, or another
  vocabulary
* [Glossary](../glossary.md): the vocabulary the UI and these docs share

Tell us what this guide is missing: https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
