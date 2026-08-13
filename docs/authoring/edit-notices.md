---
title: Edit Notices
order: 3
---
# Edit Notices

Interface messages shown to the user in the Subject editor, the Subject creator, and Manage subjects. NeoWiki ships
none, so nothing appears until you create one.

## Add a notice

Create the interface message whose key matches the scope you want:

| Create | Shown when editing |
|---|---|
| `MediaWiki:Neowiki-editnotice-{ns}` | Any Subject in namespace `{ns}` |
| `MediaWiki:Neowiki-editnotice-{ns}-{dbkey}` | A Subject on one page |
| `MediaWiki:Neowiki-editnotice-schema-{Schema}` | A Subject of one Schema, anywhere |

`{ns}` is the numeric namespace ID, `0` for the main namespace. `{dbkey}` and `{Schema}` use underscores for spaces,
and `{dbkey}` drops the namespace prefix, so `Help:New York` is `12-New_York` and a Schema named `Control Document`
is `schema-Control_Document`.

Where the namespace has subpages enabled, a notice covers the page and everything beneath it:
`Neowiki-editnotice-4-Handbook` also applies to `Project:Handbook/Chapter1`. Elsewhere slashes become dashes, so
`Foo/Bar` in the main namespace is `0-Foo-Bar`.

To warn anyone editing a Person Subject, create `MediaWiki:Neowiki-editnotice-schema-Person`:

```
Check the [[Project:Naming|naming convention]] before changing a person's name.
```

## Write the content

Magic words resolve against the page being edited, so `{{PAGENAME}}` is that page's name.

To style a notice, target it in CSS by its key: each one is wrapped in
`<div class="ext-neowiki-edit-notice" data-mw-neowiki-editnotice-key="...">`.

## When several notices match

All of them are shown, in this order: namespace, page, Schema, then any contributed by extensions. In the Subject
creator the Schema notice appears only once a Schema has been chosen.

## Known limitation

Notices follow the page being viewed, not the page that stores the Subject. A Subject rendered elsewhere with
`{{#view: <subjectId>}}` (see [Parser Functions](parser-functions.md)) therefore shows the viewing page's
notices.

## From an extension

Extensions can add notices of their own — see [Extending NeoWiki](../extending/extending.md#edit-notices).
