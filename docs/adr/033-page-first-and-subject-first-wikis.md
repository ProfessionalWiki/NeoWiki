# Page-first and Subject-first Wikis

Date: 2026-09-18

Status: Accepted (2026-09-28)

## Context

NeoWiki serves two kinds of wiki. In one the page comes first and Subjects hold data about it, as in Semantic
MediaWiki or BlueSpice; in the other the Subject comes first and the page only stores it, as in Wikibase, and
people create and view Subjects without caring about pages. User testing showed both kinds, and they want different
answers to the same two questions: where a new Subject goes, and where a link to a Subject leads.

## Decision

A wiki runs in one of two modes, page-first or subject-first. `$wgNeoWikiSubjectFirst` selects which; the default
is page-first.

| | Page-first | Subject-first |
|---|---|---|
| A new Subject goes | where its author puts it, by default on the page they are on | on a page of its own |
| A link to a Subject leads | to the page that stores it | to the Subject itself, on Special:Subject |
| A Subject's own page is | an ordinary page, titled by the label unless the author picks a title; it can be moved | in the `Subject` namespace by default, titled by the id; it cannot be moved |

A surface that must behave differently in the two modes follows this setting and gets none of its own; the
installation page lists which surface does what under
[Choosing page-first or subject-first](../operations/installation.md#choosing-page-first-or-subject-first). A second
setting, or a change to what the mode decides, needs a new ADR.

Stored data and RDF output are the same on both, and a page may hold several Subjects on both.

## Consequences

* One switch to document and test, instead of separate toggles whose combinations would each need explaining.
* `$wgNeoWikiDereferenceSubjectsToHostingPage` is gone. Where a concept URI leads in a browser now follows the mode.
* Switching an existing wiki's mode moves nothing. Pages keep their titles and namespaces; new Subjects, and all
  links to Subjects, follow the new mode.
* On a subject-first wiki, editing a label renames nothing, and two Subjects may share a label. The id still shows
  wherever MediaWiki prints a page title NeoWiki has not hooked yet, tracked in
  https://github.com/ProfessionalWiki/NeoWiki/issues/1503.
* The Data tab as default view, generated labels, a source of labels for links at render time, and per-schema or
  per-user overrides are open decisions of their own.
