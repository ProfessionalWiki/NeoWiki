---
title: Subject-first wikis
order: 2
---
# Subject-first wikis

A NeoWiki wiki runs in one of two modes, page-first or subject-first, for all its Subjects. Both store each Subject on a
wiki page, with full version history.

**Page-first**, the default: each page is about one thing, and its Subjects hold data about it, as in Semantic
MediaWiki. Links to a Subject open its page. For wikis built around articles.

**Subject-first**: each new Subject gets a page of its own that only stores it, as in Wikibase. Links to a Subject open
it on Special:Subject rather than on its page. For wikis that are mostly data, such as catalogues. You can still write
articles and show Subjects in them.

| Behaviour | Page-first | Subject-first |
|---|---|---|
| Where a new Subject is stored | on the page its creator is working on, or another they choose | on a new page of its own, by default in the `Subject` namespace |
| Title of a Subject's page | chosen by whoever creates the page, the label by default; can be changed | the Subject's id; cannot be changed |
| Where links to a Subject lead, and where you land after saving one | the page it is stored on | Special:Subject |
| Which page a Subject is on, in Subject tables and the editor | shown | hidden |
| Moving a Subject to another page | offered on the Data tab | not offered |
| Deleting the last Subject on a page | the page stays | the page goes too |

Administrators can [change the mode](operations/installation.md#choosing-page-first-or-subject-first). Switching an
existing wiki to the other mode moves nothing: pages keep their titles and namespaces, while new Subjects and links to
every Subject follow the new mode.
