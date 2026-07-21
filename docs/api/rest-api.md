---
title: REST API
order: 1
---

# REST API

NeoWiki's REST API lives under `/rest.php/neowiki/v0/*`. Requests and responses are JSON; the RDF export endpoints
return TriG or Turtle instead.

By default, reads are public and writes require a logged-in user with `edit` rights and a CSRF token; wiki
configuration may require more.

Every endpoint is also published as a complete [OpenAPI 3.0 description](#full-specification).

## Permissions

The Subject, page-subjects, subject-labels, Schema, Layout, RDF export, and entity-dereference read endpoints enforce
the caller's per-page `read` permission; page protection and `$wgNamespaceProtection` do not restrict them, because
MediaWiki's `read` action ignores both. When you may not read a page they respond as if the data were absent — a `null`
value, an empty list, or a `404` — never a `403`. `GET /subject-labels` omits the labels of Subjects whose page you
cannot read; because that filter runs per result, it caps `limit` at 50.

Subject write endpoints require per-page `edit` permission and answer `403` on denial.

The Cypher query endpoint is gated only by the `neowiki-query` right, with no per-page filtering (see
[Query API](query-api.md)).

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
| `GET /neowiki/v0/subject/{subjectId}/rdf` | Export one Subject as RDF. `format` is `trig` (default) or `turtle`; `projection` is `native` (default) or an ontology target. See [RDF export](../rdf/rdf-export.md). |
| `GET /neowiki/v0/entity/{subjectId}` | Dereference a Subject's concept URI. `303` to the Subject's RDF (`Accept: application/trig` or `text/turtle`) or to the hosting page (otherwise). See [Dereferencing subject IRIs](../rdf/rdf-export.md#dereferencing-subject-iris). |
| `PUT /neowiki/v0/subject/{subjectId}` | Replace a Subject's label and statements. |
| `DELETE /neowiki/v0/subject/{subjectId}` | Delete a Subject. |
| `POST /neowiki/v0/subject/validate` | Check whether a new Subject is valid, without saving it. Returns `{violations: [...]}` — see [Validation codes](validation-codes.md). |
| `POST /neowiki/v0/subject/{subjectId}/validate` | Check whether a change to a Subject is valid, without saving it. Returns `{violations: [...]}` — see [Validation codes](validation-codes.md). |
| `POST /neowiki/v0/subject-ids` | Mint a batch of unused Subject IDs to assign on create, e.g. to wire relations across an interlinked import. Body `count` (1–1000). |
| `GET /neowiki/v0/subject-labels` | Find Subjects of a Schema by label; returns `id`/`label` pairs. Query: `schema` (required), `search` (label prefix), `limit`. |

### Pages and Subjects

A page holds one optional main Subject and an ordered list of child Subjects. These endpoints create
Subjects and arrange them.

| Endpoint | Description |
|---|---|
| `GET /neowiki/v0/page/{pageId}/subjects` | List a page's main and child Subjects. `expand` with `schemas` or `relations`. |
| `GET /neowiki/v0/page/{pageId}/rdf` | Export the page's Subjects and metadata as RDF. `format` is `trig` (default) or `turtle`; `projection` is `native` (default) or the name of a Mapping page. See [RDF export](../rdf/rdf-export.md) and [Ontology Mapping](../rdf/ontology-mapping.md). |
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

## The `expand` parameter

The Subject read and page-subjects read endpoints take an optional multi-valued `expand` query parameter (pipe-separated,
e.g. `?expand=schemas|relations`) that embeds related data in the response. On the Subject read, `page` adds the page fields
described in [Subject format](subject-format.md#reading-subjects) to each returned Subject. On the page-subjects read,
`schemas` adds a top-level `schemas` map from Schema name to the [Schema format](schema-format.md) body of every Schema
the returned Subjects use. Both endpoints accept `relations`, which shapes the response differently on each endpoint.
Per-Subject objects follow [Subject format](subject-format.md) — page fields are trimmed from the examples below.

`expand=relations` resolves every relation-type Statement value (each holds a `target` Subject ID) to the full target
Subject; match a relation value's `target` against the resolved Subjects to look one up. A `target` that does not
resolve to a Subject you can read is silently omitted — the relation value itself is unchanged.

On the **Subject read**, the targets are merged into the same `subjects` map as the requested Subject, which
`requestedId` identifies:

```json
{
  "requestedId": "sEpfwJLnxyQy6vR",
  "subjects": {
    "sEpfwJLnxyQy6vR": {
      "id": "sEpfwJLnxyQy6vR",
      "label": "Rijksmuseum",
      "schema": "Museum",
      "statements": {
        "City": { "type": "relation", "value": [ { "id": "rEpfwJLoEB5UuQS", "target": "sEpfwJLnuwcxvuJ" } ] }
      }
    },
    "sEpfwJLnuwcxvuJ": { "id": "sEpfwJLnuwcxvuJ", "label": "Amsterdam", "schema": "City", "statements": { ... } }
  }
}
```

On the **page-subjects read**, the page's own Subjects stay in `subjects` and the resolved targets go in a separate
top-level `referencedSubjects` map keyed by Subject ID:

```json
{
  "pageId": 93,
  "mainSubjectId": "sEpfwJLnxyQy6vR",
  "subjects": {
    "sEpfwJLnxyQy6vR": { "id": "sEpfwJLnxyQy6vR", "label": "Rijksmuseum", "schema": "Museum", "statements": { ... } },
    "sEpfwJLAtndAaaA": { ... }
  },
  "referencedSubjects": {
    "sEpfwJLnuwcxvuJ": { "id": "sEpfwJLnuwcxvuJ", "label": "Amsterdam", "schema": "City", "statements": { ... } }
  }
}
```

A target already among the page's own Subjects (a relation to another Subject on the same page) is not repeated in
`referencedSubjects`. When `relations` is requested but nothing resolves, `referencedSubjects` is an empty array
(`[]`).

## Stability

**Pre-1.0.** Endpoints and payloads may change without notice. Don't build third-party integrations on
`/neowiki/v0/*` yet.

## Full specification

The OpenAPI 3.0 description carries every endpoint's parameters, request bodies, and responses. Browse it live on the
demo wiki: [neowiki.dev/w/rest.php/specs/v0/module/-](https://neowiki.dev/w/rest.php/specs/v0/module/-). Paste that JSON
into [editor.swagger.io](https://editor.swagger.io) or any OpenAPI viewer. On your own wiki, register the spec routes
first — `$wgRestAPIAdditionalRouteFiles[] = 'includes/Rest/specs.v0.json';` in `LocalSettings.php` — then fetch
`/rest.php/specs/v0/module/-`.
