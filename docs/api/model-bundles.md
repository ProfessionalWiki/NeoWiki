---
title: Model Bundles
order: 11
---
# Model Bundles

A [Model Bundle](../glossary.md#model-bundle) carries Schemas, Layouts, Mappings and Subjects from one wiki to
others in one JSON file, which each wiki installs and later updates. This page gives the formats of that file, of the
catalogs that list bundles, and of the record a wiki keeps of each bundle it installs.
[Share a Data Model](../guide/share-a-data-model.md) says how to install, update and export bundles.

A wiki reads a bundle file or a catalog from its URL in the browser, so the server that publishes it has to let other
websites read it (CORS).

## File format

```json
{
    "bundle": { "description": "Controlled lists", "name": "Vocabularies", "version": "1.2" },
    "layouts": { "Gender card": { "schema": "Gender Types", "type": "infobox" } },
    "mappings": { "EDM": { "schemas": { ... }, "version": 1 } },
    "schemas": { "Gender Types": { "description": "", "propertyDefinitions": { ... } } },
    "subjects": {
        "sF1gXfWLuUzeLK6": { "label": "female", "pageTitle": "Female", "schema": "Gender Types", "statements": { ... } }
    }
}
```

| Field | Content |
|---|---|
| `bundle` | `name`, plus optional `version` and `description`, all strings. A file without `bundle` is a plain export. |
| `schemas` | Schema name → the Schema's page JSON, in the [Schema format](schema-format.md). |
| `layouts` | Layout name → the Layout's page JSON, which needs `schema` and `type`. |
| `mappings` | Mapping name → the Mapping's page JSON, in the [Mapping format](../authoring/mapping-format.md). |
| `subjects` | Subject ID → the Subject, as below. |

Every field is optional, but a file holds at least one item. Top-level keys the format does not name are ignored. Two
keys that title the same page, such as `Person` and `person`, make the file unreadable.

A bundle's name identifies it: installing a file whose bundle a wiki has installed updates that bundle. A name cannot
contain `# < > [ ] | { } /`, `~~~`, an HTML entity or a `%` followed by two hexadecimal digits, and takes at most 228
bytes in UTF-8.

### Subjects

A Subject is in the shape the [create endpoint](subject-format.md#creating-subjects) takes, and keeps its ID on every
wiki that installs it:

| Field | Required | Content |
|---|---|---|
| `schema` | Yes | The name of its Schema. |
| `statements` | Yes | Its [Statements](subject-format.md#statement-object), relations without their `id`. |
| `label` | No | Its label. |
| `dependentSubjects` | No | Its [Dependent Subjects](subject-format.md#dependent-subjects), each with `id`, `schema` and `statements`. |
| `pageTitle` | No | The title of its page, when it is the Main Subject of a page in the main namespace. |

A Subject new to the wiki gets a page of its own. On a page-first wiki that page takes the Subject's `pageTitle`, else
its label, else its ID, and a title that is taken gives way to the ID. On a subject-first wiki it takes the ID. When
the page titled by the ID exists but holds no Subjects and is no redirect, as the page of a removed Subject does, the
Subject goes on that page.

### Canonical form

Every object has its keys sorted, except a Schema's `propertyDefinitions`, whose order is the order its properties
are shown in. Arrays keep their order, except `dependentSubjects`, sorted by `id`. A Layout leaves out its empty
fields, as the Layout editor saves it.

Two versions of an item are equal when their canonical forms are, a Subject's `pageTitle` left out. A Subject from the
file is compared in the form the wiki would store it in.

## Catalogs

A catalog lists Model Bundles by the URLs of their files. It is a JSON file:

```json
{
    "bundles": [
        { "url": "https://example.org/core.json", "name": "Core", "description": "Agents, places and vocabularies" },
        { "url": "https://example.org/edm.json", "name": "EDM", "kind": "destination", "requires": [ "Core" ] }
    ]
}
```

| Field | Content |
|---|---|
| `url` | The address of the bundle's file. |
| `name` | The bundle's name. An install from the entry is refused when the file names another bundle. |
| `description` | Optional. |
| `kind` | `model`, the default, or `destination`. |
| `requires` | Optional. The names of other entries, which are installed with this one, before it, unless the wiki has them. |

Versions come from the files. An entry without a URL or a usable name, or of another kind, is left out. A wiki names
the catalogs it installs from in `$wgNeoWikiModelBundleCatalogs`
([Key settings](../operations/installation.md#key-settings)).

## Install record

Installing writes the page `MediaWiki:NeoWiki/Model bundles/<name>.json`:

```json
{
    "version": "1.2",
    "description": "Controlled lists",
    "source": "https://example.org/vocabularies.json",
    "items": [
        { "type": "schema", "key": "Gender Types", "item": { "description": "", "propertyDefinitions": { ... } } },
        {
            "type": "subject", "key": "sF1gXfWLuUzeLK6", "hash": "1c2ba782c97901",
            "label": "female", "schema": "Gender Types"
        }
    ],
    "declined": [ { "type": "subject", "key": "sKP0f3dGqNoAcVJ" } ]
}
```

`source` is the URL the bundle was read from, or `file`. Each item has a `type` (`schema`, `layout`, `mapping` or
`subject`) and a `key` (a name or a Subject ID). A Schema, Layout or Mapping holds the bundle's version of it as
`item`, in canonical form. A Subject holds a `hash` of the bundle's version, plus its `schema` and any `label`, which
name it once the wiki has deleted it. The record lists each item of the file that the wiki has, written or left as it
was, and each one the wiki deleted after an earlier version installed it. An item in conflict keeps the version
recorded before, if it had one. `declined` lists the items of the file that the wiki does not have because an install
or update left them out.

An [update](../guide/share-a-data-model.md#update-a-bundle) compares each item with its version in the record, which
then holds the new version. An item whose write failed keeps its previous version, and what the new version no longer
has leaves the record, removed or not.

Deleting the record page detaches the bundle: its items stay, owned by no bundle.
