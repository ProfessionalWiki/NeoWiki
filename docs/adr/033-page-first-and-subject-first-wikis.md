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
| Standalone target created inside the editor | non-main Subject on the host page | its own page |
| Landing after save | the page | Special:Subject |
| Links to Subjects in infoboxes and views | the page | Special:Subject |
| Links to Subjects on the Data tab | relation values lead to the target page's Data tab with the row highlighted; the row title is not a link; "Open" leads to Special:Subject | Special:Subject |
| Search hits and Go | the page | Special:Subject |
| Concept URI in a browser | the page | Special:Subject |
| "Move" on the Data tab | offered | not offered |
| Deleting a Subject that is its page's only one | the Subject goes, the page stays | the page goes with it |
| Picker disambiguation line | page title, id on collision | id only |

The mode changes defaults, landing surfaces and link targets only. The REST API, parser-function parameters such as
an explicit `page=` on `{{#create_subject}}`, pages holding several Subjects, and where a component Subject lives — on
its host's page ([ADR 28](028-relations-model.md)) — are the same in both.

## Consequences

* One switch to document and test, in place of independent toggles whose combinations would each need explaining.
* `$wgNeoWikiDereferenceSubjectsToHostingPage` is gone: the mode answers its question.
* The Data tab as default view, a namespace for id-titled pages, generated labels, and per-schema or per-user
  overrides are separate decisions.

## Open question

Under review; the decision above is the starting point.

- **What titles a Subject's page in a subject-first wiki.** Above, the label titles it, and the Subject's id only
  where the label cannot or the title is taken, so page renames stay allowed and a label edit leaves the title
  behind. The alternative is the id as the title, as Wikibase does: uniform titles, no namesake handling, renames
  and moves refused as an invariant, at the cost of every MediaWiki surface that prints a title showing the id
  unless link rendering and display titles are hooked.
