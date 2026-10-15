---
title: Subject-first wikis
order: 2
---
# Subject-first wikis

A NeoWiki wiki runs in one of two modes, page-first or subject-first, for all its Subjects. Both store each Subject on a
wiki page, with full version history.

**Page-first**, the default: each page is about one thing, and its Subjects hold data about it, as in Semantic
MediaWiki. Links to a Subject open its page. For wikis built around articles.

**Subject-first**: as in Wikibase, each new Subject gets a page of its own, which shows it and where links to it lead.
For wikis that are mostly data, such as catalogues. You can still write articles and show Subjects in them.

| Behaviour | Page-first | Subject-first |
|---|---|---|
| Where a new Subject is stored | on the page its creator is working on, or another they choose | on a new page of its own, by default in the `Subject` namespace |
| Title of a Subject's page | chosen by whoever creates the page, the label by default; can be changed | the Subject's id; cannot be changed |
| How a page shows its Main Subject | as an infobox | in full, below any text, as on Special:Subject |
| Where links to a Subject lead, and where you land after saving one | the page it is stored on | its page when that page shows it in full, else Special:Subject |
| Which page a Subject is on, in Subject tables and the editor | shown | hidden |
| Moving a Subject to another page | offered on the Data tab | not offered |
| Deleting the last Subject on a page | the page stays | the page goes too |

In both modes, a [Dependent Subject](glossary.md#dependent-subject) is stored on its Host Subject's page.

Administrators can [change the mode](operations/installation.md#choosing-page-first-or-subject-first). Switching an
existing wiki to the other mode moves nothing: pages keep their titles and namespaces, and everything else the mode
decides follows the new mode.
