---
title: REST API
order: 3
---

<!-- DOC INTENT — read before editing.
Audience: developers deciding if NeoWiki's API fits, and developers doing a specific task with it.
Job: let them scan what the API can do and find the right endpoint fast.
Keep out: how the API/spec is built, CI/test mechanics, generator internals — those live in code and tests.
Order: group by resource; keep a resource's operations together; lead with the common reads; cross-link, don't restate.
Voice: terse — every sentence earns its place; link to the format docs instead of repeating them.
-->

# REST API

NeoWiki's REST API lives under `/rest.php/neowiki/v0/*` and uses JSON. It covers Subjects (structured
entities) with their Schemas and Layouts, plus a read-only Cypher endpoint for querying the graph.

Reads are public unless the wiki restricts them. Writes require a logged-in user with edit rights and a
CSRF token.

## Endpoints

<!-- REST-ENDPOINTS:START — drift-checked against extension.json by
     tests/phpunit/EntryPoints/REST/RestApiDocsCoverageTest. Add or remove a row whenever you add or
     remove a RestRoute, writing the path exactly as in extension.json. -->

### Subjects

Read, change, and validate Subjects. New Subjects are created on a page — see
[Pages and Subjects](#pages-and-subjects). For the body shape, see [Subject format](subject-format.md).

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/subject/{subjectId}` | Fetch a Subject. Optional `revisionId`; `expand` with `page` or `relations`. |
| `PUT /neowiki/v0/subject/{subjectId}` | Replace a Subject's label and statements. |
| `DELETE /neowiki/v0/subject/{subjectId}` | Delete a Subject. |
| `POST /neowiki/v0/subject/validate` | Check whether a new Subject is valid, without saving it. |
| `POST /neowiki/v0/subject/{subjectId}/validate` | Check whether a change to a Subject is valid, without saving it. |
| `GET /neowiki/v0/subject-labels` | Find Subjects of a Schema by label; returns `id`/`label` pairs. |

### Pages and Subjects

A page holds one optional main Subject and an ordered list of child Subjects. These endpoints create
Subjects and arrange them.

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/page/{pageId}/subjects` | List a page's main and child Subjects. `expand` with `schemas` or `relations`. |
| `POST /neowiki/v0/page/{pageId}/mainSubject` | Create the page's main Subject. |
| `PUT /neowiki/v0/page/{pageId}/mainSubject` | Promote a child Subject to main, or clear it. |
| `POST /neowiki/v0/page/{pageId}/childSubjects` | Create a child Subject on the page. |
| `PUT /neowiki/v0/page/{pageId}/subjectsOrdering` | Reorder child Subjects and set the main Subject. |

### Schemas

A Schema defines a Subject type and its properties. For the body shape, see
[Schema format](schema-format.md).

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/schemas` | List Schemas. Paginated with `limit` and `offset`. |
| `GET /neowiki/v0/schema/{schemaName}` | Fetch a Schema by name. |
| `GET /neowiki/v0/schema-names/{search}` | Find Schema names by prefix. |

### Layouts

A Layout defines how a Subject is displayed.

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/layouts` | List Layouts. Paginated with `limit` and `offset`. |
| `GET /neowiki/v0/layout/{layoutName}` | Fetch a Layout by name. |

### Query

| Endpoint | Description |
|---|---|
| `POST /neowiki/v0/query/cypher` | Run a read-only Cypher query against the graph. See [Query API](query-api.md). |

<!-- REST-ENDPOINTS:END -->

## Stability

**Pre-1.0.** Endpoints and payloads may change without notice. Don't build third-party integrations on
`/neowiki/v0/*` yet.

## Full specification

Every endpoint also publishes a complete OpenAPI 3.0 description — parameters, request bodies, and
responses — generated from the live handlers.

Browse it live on the demo wiki:
[neowiki.dev/w/rest.php/specs/v0/module/-](https://neowiki.dev/w/rest.php/specs/v0/module/-).
Paste that JSON into [editor.swagger.io](https://editor.swagger.io) or any OpenAPI viewer.

To expose it on your own wiki, register the spec routes in `LocalSettings.php`:

```php
$wgRestAPIAdditionalRouteFiles[] = 'includes/Rest/specs.v0.json';
```

Then fetch `/rest.php/specs/v0/module/-` on your wiki.
