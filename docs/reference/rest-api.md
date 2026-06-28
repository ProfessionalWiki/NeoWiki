---
title: REST API
order: 3
---
# REST API

NeoWiki's REST API lives under `/neowiki/v0/*`, served from the wiki's REST entry point at `/rest.php`.
It exposes Subjects, Schemas, Layouts, and a read-only graph query endpoint as JSON over HTTP. The
tables below are the complete `v0` surface — no installation required to read them.

Requests and responses are JSON. Reads are public unless the wiki restricts them; writes require a
logged-in user with edit rights and a CSRF token. Path parameters are shown in `{braces}`.

## Endpoints

<!-- REST-ENDPOINTS:START — this table is the install-free REST reference. It is drift-checked against
     extension.json by tests/phpunit/EntryPoints/REST/RestApiDocsCoverageTest: keep exactly one row per
     registered route, with the path written exactly as in extension.json. -->

### Subjects

A Subject is a structured entity — a person, a product, an event. See the
[Subject format](subject-format.md) for the request and response body shape.

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/subject/{subjectId}` | Fetch one Subject by ID. Optional `revisionId`; optional `expand` of `page` and `relations`. |
| `PUT /neowiki/v0/subject/{subjectId}` | Replace a Subject's label and statements. |
| `DELETE /neowiki/v0/subject/{subjectId}` | Delete a Subject. |
| `POST /neowiki/v0/subject/validate` | Validate a proposed new Subject against its Schema without saving it. |
| `POST /neowiki/v0/subject/{subjectId}/validate` | Validate a proposed update to an existing Subject without saving it. |
| `GET /neowiki/v0/subject-labels` | Search Subject labels within a Schema; returns `id`/`label` pairs for autocomplete. |

### Pages and Subjects

Each wiki page carries one optional main Subject and an ordered list of child Subjects.

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/page/{pageId}/subjects` | List a page's main and child Subjects. Optional `expand` of `schemas` and `relations`. |
| `POST /neowiki/v0/page/{pageId}/mainSubject` | Create the page's main Subject. |
| `PUT /neowiki/v0/page/{pageId}/mainSubject` | Promote an existing child Subject to main, or clear the main Subject. |
| `POST /neowiki/v0/page/{pageId}/childSubjects` | Create a child Subject on the page. |
| `PUT /neowiki/v0/page/{pageId}/subjectsOrdering` | Reorder the page's child Subjects and set its main Subject. |

### Schemas

A Schema defines a Subject type: its properties, their types, and their constraints. See the
[Schema format](schema-format.md).

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/schemas` | List Schema summaries, paginated via `limit` and `offset`. |
| `GET /neowiki/v0/schema/{schemaName}` | Fetch one Schema's full definition by name. |
| `GET /neowiki/v0/schema-names/{search}` | Autocomplete Schema names by case-insensitive prefix. |

### Layouts

A Layout describes how a Subject of a given Schema is displayed.

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/layouts` | List Layout summaries, paginated via `limit` and `offset`. |
| `GET /neowiki/v0/layout/{layoutName}` | Fetch one Layout's full definition by name. |

### Query

| Endpoint | Description |
|---|---|
| `POST /neowiki/v0/query/cypher` | Run a read-only [Cypher query](query-api.md) against the graph backend. |

<!-- REST-ENDPOINTS:END -->

## Stability

Pre-1.0. Endpoints, payloads, and the emitted spec may change without notice until 1.0. Do not treat
`/neowiki/v0/*` as stable for third-party integrations yet.

## Machine-readable spec

The tables above are the human reference. For the full OpenAPI 3.0 contract — every parameter, body
field, and response — NeoWiki emits one at request time from the handler metadata via MediaWiki core's
`ModuleSpecHandler`. There is no hand-maintained spec file; it is generated.

The spec endpoints are not registered by default. Add this to `LocalSettings.php` to expose them:

```php
$wgRestAPIAdditionalRouteFiles[] = 'includes/Rest/specs.v0.json';
```

Then:

- **Full spec:** `/rest.php/specs/v0/module/-`
- **Discovery (list of modules):** `/rest.php/specs/v0/discovery`

On the local dev wiki: `http://localhost:8484/rest.php/specs/v0/module/-`.

Paste the emitted JSON into [editor.swagger.io](https://editor.swagger.io) or a similar viewer for a
visual browse.

### How the spec is built

`ModuleSpecHandler` combines two sources at request time:

- `extension.json` — the `RestRoutes` array (paths, HTTP methods).
- REST handler classes under `src/EntryPoints/REST/` — `getParamSettings()` and `getBodyParamSettings()`
  (param names, types, required flags, descriptions).

To add an endpoint: register its route in `extension.json`, set `PARAM_DESCRIPTION` on every parameter
and body field on the handler, and add a row to the [Endpoints](#endpoints) table above. The spec is
picked up automatically; the table row is enforced by the drift check below.

## Drift checks

Two CI tests keep this surface honest:

- `tests/phpunit/EntryPoints/REST/RestApiDocsCoverageTest` asserts the [Endpoints](#endpoints) table
  lists every route in `extension.json` and no others, so this page cannot silently fall out of sync
  with the registered routes.
- `tests/phpunit/EntryPoints/REST/ModuleSpecHandlerNeoWikiTest` asserts the generated OpenAPI spec
  matches the handlers:
  - Every route registered in `extension.json` appears in the emitted spec with the expected methods.
  - Every path or query parameter declared in `getParamSettings()` is rendered into the operation's `parameters`.
  - Every body field declared in `getBodyParamSettings()` is rendered into the operation's `requestBody`.
  - Every path or query parameter in the emitted spec carries a non-empty `description`.

What these catch: a new route that nobody documented (docs test), and the framework silently dropping a
declaration (spec test). What they do not catch: intentional removal of a declaration — that is covered
by the per-handler tests that exercise the affected behaviour.
