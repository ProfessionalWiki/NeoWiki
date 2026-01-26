# Subject JSON Format

This document describes the JSON format used to store Subject data in MediaWiki revision slots and returned by
the REST API (GET endpoints). The same format is used for write operations (POST/PATCH) with minor differences
noted below.

For definitions of terms like Subject, Statement, and Value, see the [Glossary](Glossary.md).

## Overview

Subject data is stored as JSON in a dedicated MediaWiki revision slot. Each page can contain multiple Subjects:
one optional "main subject" and zero or more "child subjects".

## Top-Level Structure

```json
{
  "mainSubject": "<subject-id>",
  "subjects": {
    "<subject-id>": { ... },
    "<subject-id>": { ... }
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `mainSubject` | string | No | ID of the main subject on this page. If omitted, the page has no main subject. |
| `subjects` | object | No | Map of subject IDs to subject objects. If omitted, the page has no subjects. |

## Subject Object

Each subject in the `subjects` map has the following structure:

```json
{
  "label": "Professional Wiki GmbH",
  "schema": "Company",
  "statements": {
    "<property-name>": { ... },
    "<property-name>": { ... }
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `label` | string | Yes | Human-readable label for the subject |
| `schema` | string | Yes | Name of the Schema this subject follows (page name in the Schema namespace) |
| `statements` | object | No | Map of property names to statement objects. If omitted, the subject has no statements. |

## Statement Object

Each statement represents a property value and includes the "writer's schema" - the property type at the time
the value was written. This allows the system to handle schema changes gracefully.

```json
{
  "type": "<property-type>",
  "value": <value>
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | The property type when this value was written (writer's schema). See Value Formats below. |
| `value` | varies | Yes | The actual value. Format depends on `type`. |

## Value Formats by Type

### Text (`text`)

Array of strings.

```json
{
  "type": "text",
  "value": ["Germany"]
}
```

```json
{
  "type": "text",
  "value": ["First value", "Second value"]
}
```

### URL (`url`)

Array of strings (URLs).

```json
{
  "type": "url",
  "value": ["https://professional.wiki"]
}
```

```json
{
  "type": "url",
  "value": ["https://professional.wiki", "https://wikibase.consulting"]
}
```

### Number (`number`)

Single numeric value (integer or float).

```json
{
  "type": "number",
  "value": 2019
}
```

```json
{
  "type": "number",
  "value": 3.14159
}
```

### Relation (`relation`)

Array of Relation objects, each pointing to another subject.

```json
{
  "type": "relation",
  "value": [
    {
      "id": "r1demo5rrrrrrr1",
      "target": "s1demo4sssssss1"
    }
  ]
}
```

| Field | Type | Required | Description |
|-------|------|-------------|-------------|
| `id` | string | Yes | Unique identifier for this relation |
| `target` | string | Yes | Subject ID of the target subject |
| `properties` | object | No | Key-value pairs of relation properties. Only included when non-empty. |

Relation properties example:

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

## Empty and Null Values

- If `mainSubject` is omitted or `null`, the page has no main subject
- If `subjects` is omitted, it defaults to an empty object (no subjects)
- If `statements` is omitted, the subject has no statements
- Individual statements set to `null` are skipped during deserialization

## ID Formats

### Subject IDs

Subject IDs are 15-character nanoid-style identifiers that are lexicographically sortable by creation time.
They start with `s`.

Example: `s1demo5sssssss1`

### Relation IDs

Relation IDs follow the same format as subject IDs but start with `r`.

Example: `r1demo5rrrrrrr1`

See [ADR 014](adr/014_Improved_ID_Format.md) for details on the ID format.

## REST API

### Reading Subjects

`GET /rest.php/neowiki/v0/subject/{subjectId}`

Returns the same statement format as storage, with additional fields:
- `requestedId`: The ID that was requested
- Each subject includes `id`, `pageId`, and `pageTitle` fields

### Writing Subjects

`PATCH /rest.php/neowiki/v0/subject/{subjectId}`

The request body uses `propertyType` instead of `type` for statements:

```json
{
  "label": "Updated Label",
  "statements": {
    "Founded at": {
      "propertyType": "number",
      "value": 2019
    },
    "Unwanted Property": null
  }
}
```

Setting a statement to `null` removes it. Relation IDs can be omitted for new relations.

## Complete Example

A page about Berlin with multiple subjects (main subject + child subjects for population data):

```json
{
  "mainSubject": "s1demo2sssssss1",
  "subjects": {
    "s1demo2sssssss1": {
      "label": "Berlin",
      "schema": "City",
      "statements": {
        "Country": {
          "type": "text",
          "value": ["Germany"]
        }
      }
    },
    "s1demo2sssssss2": {
      "label": "Latest",
      "schema": "Population",
      "statements": {
        "Population": {
          "type": "number",
          "value": 3677472
        },
        "Date": {
          "type": "text",
          "value": ["2020-12-31"]
        },
        "References": {
          "type": "url",
          "value": ["https://example.com/Pop2020"]
        }
      }
    }
  }
}
```

## Related Documentation

- [ADR 002: Store Data as JSON](adr/002_Store_Data_as_JSON.md)
- [ADR 004: Use Dedicated Slot](adr/004_Use_Dedicated_Slot.md)
- [ADR 007: Multiple Subjects Per Page](adr/007_Multiple_Subjects_Per_Page.md)
- [ADR 011: Include Writer's Schema](adr/011_Include_Writers_Schema.md)
- [ADR 014: Improved ID Format](adr/014_Improved_ID_Format.md)
