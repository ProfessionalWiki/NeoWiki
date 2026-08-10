---
title: Edit Notices
order: 3
---
# Edit Notices

A message shown to users before they edit a Subject. Create one as an interface message; NeoWiki ships none, so a
key resolves only once the corresponding `MediaWiki:` page exists.

These are separate from MediaWiki's own edit notices, which stay with the wikitext editors.

## Message keys

| Key | Applies to |
|---|---|
| `MediaWiki:Neowiki-editnotice-{ns}` | Every Subject edit in namespace `{ns}` |
| `MediaWiki:Neowiki-editnotice-{ns}-{dbkey}` | One page |
| `MediaWiki:Neowiki-editnotice-schema-{Schema}` | Every Subject of that Schema, wiki-wide |

`{ns}` is the numeric namespace ID, `0` for the main namespace. `{dbkey}` is the page title with spaces as
underscores and without the namespace prefix, so `Help:New York` is `12-New_York`.

Where the namespace has subpages enabled, a notice applies to the page and everything beneath it:
`Neowiki-editnotice-4-Handbook` also covers `Project:Handbook/Chapter1`. Elsewhere slashes become dashes, so
`Foo/Bar` in the main namespace is `0-Foo-Bar`.

Matching notices are shown broadest first: namespace, then page, then Schema.

## Content

The message is wikitext, rendered inside a message frame, so keep it to a sentence or two with inline formatting.
Magic words resolve against the page being edited, making `{{PAGENAME}}` the page the user is on.

Set a message to `-` to disable it. A message that renders to nothing is skipped rather than shown as an empty
frame.

## Example

Create `MediaWiki:Neowiki-editnotice-schema-Person`:

```
Check the [[Project:Naming|naming convention]] before changing a person's name.
```

Anyone editing a Person Subject now sees that notice.

## From an extension

Extensions add notices of their own through `addSubjectEditNoticeProvider` — see
[Extending NeoWiki](../extending/extending.md).
