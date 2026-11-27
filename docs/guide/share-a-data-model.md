---
title: Share a Data Model
order: 5
---

# Share a Data Model

Special:DataModel installs the [Data Model](../glossary.md#data-model) of another wiki from its
[Model Bundles](../glossary.md#model-bundle), updates it when the bundles change, and exports your own. It comes with
the Model Bundles extension, which your wiki's administrator
[loads besides NeoWiki](../operations/installation.md#2-load-the-extension). Installing a bundle takes the
`editsitejson` and `editinterface` rights besides the rights each write takes, such as
`neowiki-controlled-subject-edit` for [Controlled Subjects](../glossary.md#controlled-subject). Special:Schemas imports
Schemas only, and refers a file that holds more to Special:DataModel.

## Set up a new wiki

Special:DataModel/setup gives a wiki its first Data Model, in steps:

1. How the wiki works: page-first or subject-first, and the data languages. Every first install on a wiki without
   Subjects starts here. Only what you change is saved, on
   [MediaWiki:NeoWiki](../operations/installation.md#on-wiki-settings), as the install's first write, or when you
   finish after starting empty.
2. A model from the catalogs, or none.
3. The destinations whose requirements the model and the installed bundles meet.
4. One preview of the chosen bundles and those they require, then the install.

Special:NeoWiki, Special:Schemas and Special:DataModel offer the setup to users who may install bundles while the wiki
names a catalog and has no Schemas and no installed bundle.

## Install a bundle

Special:DataModel installs a bundle from a file on your computer, from a URL, or from a catalog the wiki names. It
installs any file as a Model Bundle, under the name you give it when the file has none.

The preview compares each item of the file with the wiki:

| Status | Meaning | Ticked by default |
|---|---|---|
| New | The wiki does not have it. Ticked, it is created. | Yes |
| Unchanged | The wiki has it as the file does. | Nothing to do |
| Differs | The wiki has it with other content. Ticked, the file's version replaces it. | No |
| Conflict | It cannot be written; the preview says why. | Cannot be ticked |

An item is in conflict when it is:

- a Subject whose ID is held by a Subject of another Schema, or by a Dependent Subject of another Subject;
- a Subject with a Dependent Subject whose ID is held by another Subject;
- a Subject with a Dependent Subject whose Schema the wiki does not have as a Schema of Dependent Subjects, unless the
  file creates it;
- a Schema that would change whether its Subjects are dependent;
- owned by another installed bundle;
- on the wiki in a version that cannot be read.

The ticked items are written one at a time: Schemas, then Layouts, Mappings and Subjects. A write that fails is
reported with its reason, and the others go on. A Schema, Layout or Mapping page edited since the preview is not
overwritten: unless MediaWiki can merge that edit into the write, the write fails. A relation to a Subject that
neither the file nor the wiki has, or a standalone Subject whose Schema the wiki lacks, does not stop a write.
Installing deletes nothing. Each new Subject gets [a page of its own](../api/model-bundles.md#subjects).

## Update a bundle

A bundle installed from a URL offers **Update**, which reads its file there again, and every bundle offers
**Update from file**. For users who may install bundles, Special:NeoWiki counts the installed bundles that have another
version at their URL. An update compares each item three ways: the version the bundle installed, the wiki's version and
the file's, whatever the two version numbers.

| Case | Ticked, the update | Ticked by default |
|---|---|---|
| Changed in the bundle only | replaces it | Yes |
| Changed in the bundle and on the wiki | replaces the wiki's changes; the preview shows both versions | No |
| Changed alike in both, or in neither | | Nothing to do |
| Changed on the wiki only | | Nothing to do |
| New in the bundle | as on install | As on install |
| Left unticked before, and not on the wiki, shown as Left out earlier | creates it | No |
| No longer in the bundle | removes it from the wiki | No |
| Deleted on the wiki and changed in the bundle | creates it again | No |
| Deleted on the wiki and not changed in the bundle | | Nothing to do |

Removal is offered whether or not the wiki changed the item, except for a Subject that Subjects outside the bundle
point to, and for a Schema whose Subjects are not all being removed. Removals come after the writes, Subjects first
and Schemas last.

## See what the wiki changed

Once you expand a bundle, Special:DataModel compares each item it owns with the version installed, and shows it as
unchanged, changed here or deleted here. For users who may install bundles, it also shows any other version at a
bundle's source URL. Each bundle links to its [install record](../api/model-bundles.md#install-record); deleting that
page detaches the bundle.

## Export

Special:DataModel exports the Schemas and Mappings you choose, all of them unless you untick some: each Schema with
its Subjects if you switch that on, and each Mapping as it is. The Layouts of the chosen Schemas come too, unless you
untick them. An export can hold Mappings alone. Dependent Subjects come with their Host Subjects, and their Schemas
with them. Naming the export makes it a Model Bundle.
