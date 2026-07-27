---
title: Subject Format
order: 3
---
# Subject JSON Format

Subject data is [stored as JSON](../adr/002-store-data-as-json.md). The REST API returns and accepts the same
shapes; the fields each endpoint adds or ignores are under [REST API](#rest-api).

For Subject, Statement, and Value, see the [Glossary](../glossary.md).

## Top-level structure

A page holds one optional main Subject and zero or more child Subjects
([ADR 007](../adr/007-multiple-subjects-per-page.md)), all in one `subjects` map with `mainSubject` pointing at
the main one.

```json
{
  "mainSubject": "<subject-id>",
  "subjects": {
    "<subject-id>": { ... }
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `mainSubject` | string | No | ID of the page's main Subject. Omitted or `null` when the page has none. |
| `subjects` | object | No | Map of Subject ID to [Subject object](#subject-object). Omitted or empty when the page has no Subjects. |

## Subject object

```json
{
  "label": "Professional Wiki GmbH",
  "schema": "Company",
  "statements": {
    "<property-name>": { ... }
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `label` | string | Yes | Human-readable label for the Subject. |
| `schema` | string | Yes | Name of the Schema the Subject follows (a page in the Schema namespace). |
| `statements` | object | No | Map of property name to [Statement object](#statement-object). Omitted when the Subject has none. |

A property mapped to `null` instead of a Statement object is skipped when the JSON is read.

## Statement object

```json
{
  "propertyType": "number",
  "value": 2019
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `propertyType` | string | Yes | The property's type when the value was written — the writer's schema ([ADR 011](../adr/011-include-writers-schema.md)). |
| `value` | varies | Yes | The value, shaped by `propertyType`. See [Value formats](#value-formats). |

## Value formats

`propertyType` holds the property type name, which fixes the `value` shape:

| `propertyType` | `value` |
|----------------|---------|
| `text`, `url`, `select`, `date`, `dateTime` | Array of strings, one per value part. |
| `number` | A single number (integer or float). |
| `boolean` | A single boolean. |
| `relation` | Array of [relation objects](#relations). |

A multi-part `text` value:

```json
{ "propertyType": "text", "value": [ "First value", "Second value" ] }
```

Every registered PropertyType uses one of these four `value` shapes. A `propertyType` whose PropertyType is not
registered — its extension disabled — keeps the raw value that was stored
([`unregistered-type`](validation-codes.md#unregistered-type)).

### Relations

Each `relation` value is an array of objects pointing at other Subjects:

```json
{
  "propertyType": "relation",
  "value": [
    { "id": "r1demo5rrrrrrr1", "target": "s1demo4sssssss1" }
  ]
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | ID of this relation. |
| `target` | string | Yes | ID of the target Subject. |
| `properties` | object | No | Key-value relation properties. Present only when non-empty. |

With relation properties:

```json
{
  "id": "r1demo5rrrrrrr1",
  "target": "s1demo4sssssss1",
  "properties": {
    "role": "CEO",
    "since": 2019
  }
}
```

## IDs

Subject and Relation IDs are 15-character nanoid-style strings, lexicographically sortable by creation time.
Subject IDs start with `s` (`s1demo5sssssss1`), Relation IDs with `r` (`r1demo5rrrrrrr1`). See
[ADR 014](../adr/014-improved-id-format.md).

## REST API

### Reading Subjects

`GET /rest.php/neowiki/v0/subject/{subjectId}` returns a top-level `requestedId` and a `subjects` map; each
Subject gains an `id` field.

- `?expand=page` adds `pageId`, `pageTitle`, and `pageNamespaceId` to each Subject. `pageTitle` is the full page
  title with namespace prefix (e.g. `Help:Installation`); `pageNamespaceId` is the canonical MediaWiki namespace
  ID (e.g. `0` for the main namespace, `12` for Help).
- `?expand=relations` embeds the Subjects this one's relations target; see
  [REST API](rest-api.md#the-expand-parameter) for the shape.
- `?revisionId=` returns the Subject as of that MediaWiki revision; an unknown or unreadable revision returns `404`.

### Creating Subjects

`POST /rest.php/neowiki/v0/page/{pageId}/mainSubject` and `.../childSubjects` create a Subject on a page. The body
takes `label`, `schema`, and [`statements`](#statement-object) (all required), plus an optional `comment` edit summary.

The server mints the Subject ID unless you pass one:

| Field | Required | Notes |
|-------|----------|-------|
| `id` | No | Subject ID to assign. Well-formed (`400` otherwise) and unused (`409` otherwise). Pre-mint a batch with `POST /rest.php/neowiki/v0/subject-ids` to wire relations before their targets exist. |

### Writing Subjects

`PUT /rest.php/neowiki/v0/subject/{subjectId}` replaces the Subject's label and statements:

```json
{
  "label": "Updated Label",
  "statements": {
    "Founded at": {
      "propertyType": "number",
      "value": 2019
    }
  },
  "comment": "Optional edit summary"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `label` | Yes | Non-empty after `trim`. |
| `statements` | Yes | Map of property name to Statement; omitted names are deleted. Pass `{}` to clear all. |
| `comment` | No | Edit summary. |

On every endpoint that takes `statements`, an entry without `propertyType`, or whose value is empty for its type, is
dropped without error. For schema/value validation outcomes see [Validation Codes](validation-codes.md).

A relation may omit `id`; the server generates one. The Subject's `id`, `schema`, and page fields are immutable
and ignored if sent.

## Complete example

A page about Berlin with a main Subject and a child Subject for population data:

```json
{
  "mainSubject": "s1demo2sssssss1",
  "subjects": {
    "s1demo2sssssss1": {
      "label": "Berlin",
      "schema": "City",
      "statements": {
        "Country": {
          "propertyType": "text",
          "value": ["Germany"]
        }
      }
    },
    "s1demo2sssssss2": {
      "label": "Latest",
      "schema": "Population",
      "statements": {
        "Population": {
          "propertyType": "number",
          "value": 3677472
        },
        "Date": {
          "propertyType": "text",
          "value": ["2020-12-31"]
        },
        "References": {
          "propertyType": "url",
          "value": ["https://example.com/Pop2020"]
        }
      }
    }
  }
}
```
