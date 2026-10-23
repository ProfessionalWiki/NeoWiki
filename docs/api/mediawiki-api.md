---
title: MediaWiki API
order: 9
---

# MediaWiki API

Your wiki also serves MediaWiki's [Action API](https://www.mediawiki.org/wiki/API:Main_page) at `/api.php`. You sign in
once for both APIs; see [Authentication](rest-api.md#authentication). MediaWiki
[client libraries](https://www.mediawiki.org/wiki/API:Client_code) exist for most languages.

An edit to a Subject, Schema, Layout, or Mapping is an edit to its page and shows in page history and recent changes.

| To | Use |
|---|---|
| Find a page's ID from its title, for the `{pageId}` endpoints | [`action=query&titles=`](https://www.mediawiki.org/wiki/API:Query) |
| List a page's revisions with editor and time, then read a Subject as of one with [`?revisionId=`](subject-format.md#reading-subjects) | [`prop=revisions`](https://www.mediawiki.org/wiki/API:Revisions) |
| Poll for what changed since your last sync, deletions included | [`list=recentchanges`](https://www.mediawiki.org/wiki/API:RecentChanges) |
| List the wiki's accounts and their groups | [`list=allusers`](https://www.mediawiki.org/wiki/API:Allusers) |
| Search pages, including by their Subjects' values | [`list=search`](https://www.mediawiki.org/wiki/API:Search); for which values it matches, see [Making Subjects searchable](../operations/maintenance.md#making-subjects-searchable) |
| Create, edit, move, or delete a page | [`action=edit`](https://www.mediawiki.org/wiki/API:Edit), [`action=move`](https://www.mediawiki.org/wiki/API:Move), [`action=delete`](https://www.mediawiki.org/wiki/API:Delete) |

Rendered page HTML from the Action API leaves out the Subjects that [Views](../glossary.md#view) and
`{{#main_subject}}` show. Read and write Subjects through NeoWiki's [REST API](rest-api.md), not through a page's
revision slots.

To see every module your wiki offers, open `Special:ApiSandbox` on it.
