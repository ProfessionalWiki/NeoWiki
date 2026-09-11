---
title: Restricted content
order: 5
---

# Restricted content

What NeoWiki restricts, and what it does not, on a wiki where some content is readable only by certain users or
groups. Page protection and `$wgNamespaceProtection` do not restrict reading; see
[Permissions](../api/rest-api.md#permissions).

## What limits each surface

Enforced per page:

| Surface | What limits it |
|---|---|
| REST reads | The page's `read`. |
| REST writes | The page's `edit`. |
| `{{#view}}` | The source page's `read`. |
| `{{#neowiki_value}}` and the `nw.` accessors | The parsing user's `read`. |

Enforced wiki-wide, or not at all:

| Surface | What limits it |
|---|---|
| `POST /query/cypher`, `POST /query/sparql` | The caller's [`neowiki-query`](../api/query-api.md#permissions). |
| `{{#cypher_raw}}`, `{{#sparql_raw}}`, `nw.query`, `nw.sparqlQuery` | The parsing user's `neowiki-query`. |
| Graph-store status and rebuilds | The caller's `neowiki-admin`. |
| Subjects from a [Subject Source](../extending/subject-sources.md) | Nothing. They have no page here to authorize against, so a Source must serve only what every reader may see. |
| Graph stores, RDF dumps and their backups | Nothing. They hold restricted content in full. |

## Choosing who may query

NeoWiki grants `neowiki-query` to everyone (`*`) by default. Removing it helps only where anonymous readers can
reach a query surface — on a wiki they cannot read at all, they cannot reach one either. To remove it:

```php
$wgGroupPermissions['*']['neowiki-query'] = false;
```

Grant it to whichever groups should keep it, and check the result on `Special:ListGroupRights`. OAuth consumers
and bot passwords need no separate change.

## What readers see

After narrowing `neowiki-query`:

- A page using `{{#cypher_raw}}` or `{{#sparql_raw}}` shows an error box where the results were, to anyone who no
  longer holds the right.
- A page using `nw.query` or `nw.sparqlQuery` shows a script error and joins the wiki's pages-with-script-errors
  category, because the save and job-queue parses run as an anonymous user. A module can avoid that by wrapping
  the call in `pcall`.
- Readers who still hold the right see results as before.

Whether or not you narrow it:

- Restricting a page does not clear what is already cached. A page showing that data keeps showing it until it is
  edited or purged, or `$wgParserCacheExpireTime` elapses. Purge it to apply the change at once.
- Where readers who share the same groups may read different pages — because `read` is granted per account, per
  IP or per session — a cached page can show one reader's data to another. Render Subjects through `{{#view}}`
  there: it fetches per viewer instead of reading at parse time.
- On a wiki where anonymous users cannot read, a category or page property that a template derives from a Subject
  value is never set. That is expected, not a fault to report.

## Stores and dumps

Restricting a page removes nothing from a store, and a rebuild reprojects it, so a store that has once held
restricted content keeps holding it. Do not expose a SPARQL store directly on a wiki with restricted content, and
treat a [bulk dump](../api/rdf-export.md#bulk-dump) and any store backup as readable by whoever can reach it.

The model behind all of this is [ADR 27: Access Control](../adr/027-access-control.md).
