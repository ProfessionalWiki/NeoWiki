---
title: Restricted content
order: 5
---

# Restricted content

NeoWiki gates every read on the page that stores the data, with the reader's own rights, and has nothing finer than
the page ([ADR 27](../adr/027-access-control.md)). Page protection and `$wgNamespaceProtection` do not restrict
reading ([Permissions](../api/rest-api.md#permissions)).

## What each surface checks

| Surface | Gate | A denied reader gets |
|---|---|---|
| Subject, subject-label search, Schema, Layout, Mapping and RDF reads over [REST](../api/rest-api.md#permissions) | The storing page's `read` | Absent data, never `403` |
| `{{#view}}` | The storing page's `read`, per viewer over REST | Nothing rendered |
| `{{#neowiki_value}}` and the `nw.` accessors | The storing page's `read`, as the parsing user | Empty output, `nil`, or an empty table |
| `{{#cypher_raw}}`, `{{#sparql_raw}}`, `nw.query`, `nw.sparqlQuery` | The parsing user's [`neowiki-query`](../api/query-api.md#permissions), a whole-store read | An error box; Lua throws |
| `POST /query/cypher`, `POST /query/sparql` | The caller's `neowiki-query` | `403` |
| Graph-store status and rebuilds | The caller's `neowiki-admin` (`sysop` by default) | `403` |
| Subjects from a [Subject Source](../extending/subject-sources.md) | Nothing: no page to authorize against, so a Source must serve only what every reader may see | — |

On a wiki that requires login to read, `{{#view}}` renders nothing for anonymous readers on a page opened through
`$wgWhitelistRead`: they get no [REST](../api/rest-api.md#permissions) at all.

## Narrowing `neowiki-query`

NeoWiki grants the right to `*`. Withdrawing it helps only where anonymous readers can reach a query surface.

```php
$wgGroupPermissions['*']['neowiki-query'] = false;
$wgGroupPermissions['user']['neowiki-query'] = true;
```

Check `Special:ListGroupRights`, then load a page with a raw query while logged out. OAuth consumers and bot
passwords need no separate change.

Saves, job-queue parses and Parsoid renders run as the anonymous user, so a page using `{{#cypher_raw}}` or
`{{#sparql_raw}}` shows an error box in place of the results to readers without the right, and a page using
`nw.query` or `nw.sparqlQuery` shows a script error and joins the pages-with-script-errors category unless the
module wraps the call in `pcall`. Readers who hold the right get
[their own cached parse](../authoring/parser-functions.md) and see results.

## Where the protection stops

- **Cached output outlives a restriction.** A page that showed now-restricted data keeps showing it until it is
  edited or purged, or `$wgParserCacheExpireTime` (a day by default) elapses.
- **Per-account `read` is unsupported.** Parse-time output is cached per the reader's groups and wiki-level rights:
  exact for private wikis, namespace lockdowns, `$wgWhitelistRead` and group-configured ACL extensions, wrong for a
  hook granting `read` per account, IP or session, where a cached page can show one reader's data to another.
  Render Subjects through `{{#view}}` there; it fetches per viewer.
- **Stores and dumps hold restricted content in full.** Restricting a page removes nothing from a graph store, and a
  rebuild reprojects it; deleting the page does remove it. Do not expose a SPARQL store directly, and treat a
  [bulk dump](../api/rdf-export.md#bulk-dump) and any store backup as readable by whoever can reach it.
- **Derived data follows the anonymous parse.** On a wiki where anonymous users cannot read, a category or page
  property a template derives from a Subject value is never set.
