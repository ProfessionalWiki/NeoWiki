---
title: Subject-first wikis
order: 2
---
# Subject-first wikis

NeoWiki is page-first by default. You can [change it](operations/installation.md#choosing-page-first-or-subject-first)
to subject-first, which is a Wikibase-like mode for use cases where wiki pages are not important.

| Behaviour | Page-first | Subject-first |
|---|---|---|
| Where a new Subject is stored | on the page its creator is working on, or another they choose | on a new page of its own, by default in the `Subject` namespace |
| Title of a Subject's page | chosen by whoever creates the page, the label by default; can be changed | the Subject's id; cannot be changed |
| Where links to a Subject lead, and where saving one lands | the page it is stored on | Special:Subject |
| Pages named beside Subjects, in Subject tables and the editor | shown | hidden |
| Moving a Subject to another page | offered on the Data tab | not offered |
| Deleting the last Subject on a page | the page stays | the page goes too |

Switching an existing wiki to the other mode moves nothing: pages keep their titles and namespaces, and new Subjects and
all links follow the new mode.
