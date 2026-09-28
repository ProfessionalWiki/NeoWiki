# Page-first and Subject-first Wikis

Date: 2026-09-18

Status: Accepted (2026-09-28)

## Context

NeoWiki serves two kinds of wiki. In one the page comes first and Subjects hold data about it, as in Semantic
MediaWiki or BlueSpice; in the other the Subject comes first and the page only stores it, as in Wikibase, and
people create and view Subjects without caring about pages. User testing showed both kinds, and they want different
answers to the same two questions: where a new Subject goes, and where a link to a Subject leads.

## Decision

One wiki-level setting, `$wgNeoWikiSubjectFirst`, selects the mode. It is `false` by default, which is page-first.

The mode answers both questions, and the one they raise:

* Where a new Subject goes: on a page-first wiki wherever its author says, the page it is created from by default;
  on a subject-first wiki on a page of its own.
* Where a link to a Subject leads: on a page-first wiki to the page that stores it; on a subject-first wiki to the
  Subject itself, on Special:Subject.
* What a Subject's own page is: on a page-first wiki an ordinary page, titled by the label unless its author chose a
  title, and moved like any other; on a subject-first wiki a page in a `Subject` namespace, titled by the id, which
  no move changes.

Every surface that must differ between the two kinds of wiki follows the mode; none gets a setting of its own.
Which surface does what is listed with the setting under
[Choosing page-first or subject-first](../operations/installation.md#choosing-page-first-or-subject-first). A second
setting, or a change to what the mode answers, is a new decision.

The data, its RDF, and pages holding several Subjects are the same in both.

## Consequences

* One switch to document and test, in place of independent toggles whose combinations would each need explaining.
* `$wgNeoWikiDereferenceSubjectsToHostingPage` is gone: the mode answers its question.
* Switching a wiki's mode moves nothing: existing pages keep their titles and namespaces, and new Subjects and
  links to Subjects follow the new mode.
* On a subject-first wiki a label edit renames nothing and two Subjects may share a label. The id still shows
  wherever MediaWiki prints a title that is not yet hooked, which
  https://github.com/ProfessionalWiki/NeoWiki/issues/1503 tracks. Labelling every link on a page needs a source of
  labels at render time, an open decision.
* The Data tab as default view, generated labels, and per-schema or per-user overrides are open decisions of their
  own.
