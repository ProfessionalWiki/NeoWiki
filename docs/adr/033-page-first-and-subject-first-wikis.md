# Page-first and Subject-first Wikis

Date: 2026-09-18

Status: Draft

## Context

NeoWiki serves two kinds of wiki. In one the page comes first and Subjects hold data about it, as in Semantic
MediaWiki or BlueSpice; in the other the Subject comes first and the page only stores it, as in Wikibase, and
people create and view Subjects without caring about pages. User testing showed both kinds, and they want different
answers to the same two questions: where a new Subject goes, and where a link to a Subject leads.

## Decision

One wiki-level setting, `$wgNeoWikiSubjectFirst`, selects the mode. It is `false` by default, which is page-first.
It governs exactly these behaviours:

| Behaviour | Page-first | Subject-first |
|---|---|---|
| Creator: "Store the subject on" | shown; defaults to the current page; a new page needs a title | hidden; always a new page |
| Standalone target created inside the editor | non-main Subject on the edited Subject's page | its own page |
| Landing after save | the page | Special:Subject |
| Links to Subjects in infoboxes and views | the page | Special:Subject |
| Links to Subjects on the Data tab | relation values lead to the target page's Data tab with the row highlighted; the row title is not a link; "Open" leads to Special:Subject | Special:Subject |
| Search hits and Go | the page | Special:Subject |
| Concept URI in a browser | the page | Special:Subject |
| "Move" on the Data tab | offered | not offered |
| Deleting a Subject that is its page's only one | the Subject goes, the page stays | the page goes with it |
| Picker disambiguation line | page title, id on collision | id only |
| A Subject's own page | titled by the label, else by the id; in the main namespace | titled by the id; in the `Subject` namespace, or the one `$wgNeoWikiSubjectPageNamespace` names |
| A page title asked for on creation (`pageTitle`, `page=` naming a page that does not exist) | used, in the main namespace | refused |
| Moving a page titled by the id of a Subject on it | allowed | refused |

The mode changes defaults, landing surfaces, link targets and what titles a Subject's own page. Pages holding several
Subjects, and where a Dependent Subject lives — on its Host Subject's page ([ADR 28](028-relations-model.md)) — are
the same in both.

## Consequences

* One switch to document and test, in place of independent toggles whose combinations would each need explaining.
* `$wgNeoWikiDereferenceSubjectsToHostingPage` is gone: the mode answers its question.
* On a subject-first wiki a label edit renames nothing and two Subjects may share a label: the title neither
  drifts from the label nor needs namesakes told apart. The id still shows wherever MediaWiki prints a title that is
  not yet hooked — categories, What links here, history headings, edit summaries — which
  https://github.com/ProfessionalWiki/NeoWiki/issues/1503 tracks. Labelling every link on a page needs a source of
  labels at render time, an open decision.
* The Data tab as default view, generated labels, and per-schema or per-user overrides are separate decisions.
