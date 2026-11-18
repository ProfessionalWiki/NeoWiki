---
title: Model Bundles
order: 11
---
# Model Bundles

A [Model Bundle](../glossary.md#model-bundle) carries Schemas, Layouts, Mappings and Subjects from one wiki to
others in one JSON file, which each wiki installs and later updates. Special:DataModel installs, updates and exports
bundles. It reads a file from your computer or from a URL, whose server has to let other websites read it (CORS), and
installs any file as a Model Bundle, under the name you give it when the file has none. The Import button of
Special:Schemas installs a file with `bundle` metadata the same way, and imports any other file's items without an
install record.

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

### Canonical form

Every object has its keys sorted, except a Schema's `propertyDefinitions`, whose order is the order its properties
are shown in. Arrays keep their order, except `dependentSubjects`, sorted by `id`. A Layout leaves out its empty
fields, as the Layout editor saves it.

Two versions of an item are equal when their canonical forms are, a Subject's `pageTitle` left out. A Subject is
compared as the wiki stores it.

## Exporting

Special:DataModel exports the Schemas and Mappings you choose, all of them unless you untick some: each Schema with
its Subjects if you switch that on, and each Mapping as it is. The Layouts of the chosen Schemas come with them unless
you switch that off. An export can hold Mappings alone. The Subjects of a Schema whose Subjects are dependent come with
their Host Subjects, and the Schema with them. Naming the export makes it a Model Bundle.

## Installing

Installing a bundle takes the `editsitejson` and `editinterface` rights besides the rights each write takes. Importing a
plain export takes only the latter.

The preview compares each item of the file with the wiki:

| Status | Meaning | Ticked by default |
|---|---|---|
| New | The wiki does not have it. Ticked, it is created. | Yes |
| Unchanged | The wiki has it as the file does. | Nothing to do |
| Differs | The wiki has it with other content. Ticked, the file's replaces it. | No |
| Conflict | It cannot be written; the preview says why. | Cannot be ticked |

An item is in conflict when it is:

- a Subject whose ID is held by a Subject of another Schema, or by a Dependent Subject of another Subject;
- a Subject with a Dependent Subject whose ID is held by another Subject;
- a Subject with a Dependent Subject whose Schema the wiki does not have as a Schema of Dependent Subjects, unless the
  file creates it;
- a Schema that would change whether its Subjects are dependent;
- owned by another installed bundle, when installing a bundle;
- on the wiki in a version that cannot be read.

A plain import also writes items an installed bundle owns; its preview names the bundle.

The ticked items are written one at a time: Schemas, then Layouts, Mappings and Subjects. A write that fails is
reported with its reason, and the others go on. A Schema, Layout or Mapping page edited since the preview is not
overwritten: unless MediaWiki can merge that edit into the write, the write fails. A relation to a Subject that
neither the file nor the wiki has, or a standalone Subject whose Schema the wiki lacks, does not stop a write.
Installing deletes nothing.

A new Subject gets a page of its own. On a page-first wiki that page takes the file's `pageTitle`, else the Subject's
label, else its ID, and a title that is taken gives way to the ID. On a subject-first wiki it takes the ID. When the
page titled by the ID exists but holds no Subjects and is no redirect, as the page of a removed Subject does, the
Subject goes on that page.

### Install record

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
    ]
}
```

`source` is the URL the bundle was read from, or `file`. Each item has a `type` (`schema`, `layout`, `mapping` or
`subject`) and a `key` (a name or a Subject ID). A Schema, Layout or Mapping holds the bundle's version of it as
`item`, in canonical form. A Subject holds a `hash` of the bundle's version, and the `label`, if it has one, and
`schema` that Special:DataModel shows it by once this wiki deleted it. The record lists each item of the file that the
wiki has, written or left as it was, and each one the wiki deleted after an earlier version installed it. An item in
conflict keeps the version recorded before, if it had one.

Once you expand a bundle, Special:DataModel compares each item it owns with its recorded version, and shows it as
unchanged, changed here or deleted here. For users who may install bundles, it also shows any other version at a
bundle's source URL. Each bundle links to its record page; deleting that page detaches the bundle: its items stay,
owned by no bundle.

## Updating

An update compares each item three ways: the version in the record, the wiki's version and the file's, whatever the
two version numbers.

| Case | Ticked, the update | Ticked by default |
|---|---|---|
| Changed in the bundle only | replaces it | Yes |
| Changed in the bundle and on the wiki | replaces the wiki's changes; the preview shows both versions | No |
| Changed alike in both, or in neither | | Nothing to do |
| Changed on the wiki only | | Nothing to do |
| New in the bundle | as on install | As on install |
| No longer in the bundle | removes it from the wiki | No |
| Deleted on the wiki and changed in the bundle | creates it again | No |
| Deleted on the wiki and not changed in the bundle | | Nothing to do |

Removal is offered whether or not the wiki changed the item, except for a Subject that Subjects outside the bundle
point to, and for a Schema whose Subjects are not all being removed. Removals come after the writes, Subjects first
and Schemas last.

The record then holds the new version. An item whose write failed keeps its previous version, and what the new version
no longer has leaves the record, removed or not.
