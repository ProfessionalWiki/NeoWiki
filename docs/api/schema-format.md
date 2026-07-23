---
title: Schema Format
order: 2
---
# Schema JSON Format

Schemas are stored as the JSON content of pages in the Schema namespace (7474), and returned by the
[Schema REST endpoints](rest-api.md#schemas). For terms like Schema and Property Definition, see the
[Glossary](../glossary.md). A machine-readable JSON Schema for this format is at
[`schemaContentSchema.json`](../../src/Persistence/MediaWiki/schemaContentSchema.json); it checks structure only.
Per-type value constraints (`options`, ranges, string formats, `uniqueItems`) are enforced server-side and reported as
[validation codes](validation-codes.md).

## Top-Level Structure

```json
{
  "description": "Optional description of the schema",
  "propertyDefinitions": {
    "<property-name>": { ... },
    "<property-name>": { ... }
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `description` | string | No | Human-readable description of the schema |
| `propertyDefinitions` | object | Yes | Map of property names to property definition objects |

## Property Definition

Every property definition carries the common fields below plus the type-specific fields for its `type`.

### Common Fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `type` | string | Yes | - | The property type. See [Property Types](#property-types). |
| `description` | string | No | `""` | Human-readable description of the property |
| `required` | boolean | No | `false` | Whether a value is required for this property |
| `default` | varies | No | `null` | Default value when none is provided |

## Property Types

### Text (`text`)

Plain text values.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `multiple` | boolean | `false` | Allow multiple values |
| `uniqueItems` | boolean | `false` | Reject duplicate values (only with `multiple`) |
| `minLength` | number | `null` | Minimum trimmed length of each value |
| `maxLength` | number | `null` | Maximum trimmed length of each value |

```json
{
  "type": "text",
  "multiple": true,
  "uniqueItems": true,
  "maxLength": 50
}
```

### URL (`url`)

URL values.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `multiple` | boolean | `false` | Allow multiple values |
| `uniqueItems` | boolean | `false` | Reject duplicate values (only with `multiple`) |

### Number (`number`)

Numeric values (integer or float).

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `precision` | number | `null` | Number of decimal places for display |
| `minimum` | number | `null` | Minimum allowed value (inclusive) |
| `maximum` | number | `null` | Maximum allowed value (inclusive) |

```json
{
  "type": "number",
  "minimum": 0,
  "maximum": 100,
  "precision": 2
}
```

### Select (`select`)

A fixed set of options the user picks from.

```json
{
  "type": "select",
  "options": [
    { "id": "opt_draft",    "label": "Draft" },
    { "id": "opt_review",   "label": "Review" },
    { "id": "opt_approved", "label": "Approved" }
  ]
}
```

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `options` | `SelectOption[]` | `[]` | The allowed options to choose from |
| `multiple` | boolean | `false` | Allow selecting more than one |

Each `SelectOption`:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Stable identifier, unique within the property. Statements store this. |
| `label` | string | Yes | Display text, unique (case-insensitive, trimmed) within the property. |

On write, a Statement value may be an option `id`, a `label` (case-insensitive, trimmed), or a `{ "id", "label" }`
object; a mismatched `id`/`label` is rejected. Reads and display resolve stored `id`s back to labels via the current
Schema.

### Relation (`relation`)

References to other Subjects.

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `relation` | string | Yes | - | The relation type name |
| `targetSchema` | string | Yes | - | Name of the Schema that target Subjects must follow |
| `multiple` | boolean | No | `false` | Allow multiple relations |

```json
{
  "type": "relation",
  "relation": "Has product",
  "targetSchema": "Product",
  "multiple": true
}
```

### Boolean (`boolean`)

A true/false value. No type-specific fields; `default` may be `true`, `false`, or `null` (no default).

```json
{
  "type": "boolean",
  "default": false
}
```

### Date (`date`)

A calendar date, stored as a strict ISO 8601 `YYYY-MM-DD` string (no time or timezone; see
[`invalid-date`](validation-codes.md#invalid-date)). `minimum`, `maximum`, and any `default` use the same format.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `minimum` | string | `null` | Earliest allowed date |
| `maximum` | string | `null` | Latest allowed date |

### DateTime (`dateTime`)

A date and time, stored as a strict ISO 8601 / `xsd:dateTime` string with an explicit timezone offset or `Z`
(e.g. `2025-06-15T14:30:00Z`; see [`invalid-datetime`](validation-codes.md#invalid-datetime)). `minimum`, `maximum`,
and any `default` use the same format.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `minimum` | string | `null` | Earliest allowed datetime |
| `maximum` | string | `null` | Latest allowed datetime |

## REST API

`GET /neowiki/v0/schema/{schemaName}` wraps this format as `{ "schema": ... }`, or `{ "schema": null }` when the Schema
does not exist or you may not [read](rest-api.md#permissions) it. There is no write endpoint; create or edit a Schema by
editing its page in the Schema namespace.

## Complete Example

A "Company" schema with various property types:

```json
{
  "description": "A business entity",
  "propertyDefinitions": {
    "Founded at": {
      "type": "number",
      "description": "Year the company was founded"
    },
    "Websites": {
      "type": "url",
      "multiple": true
    },
    "Main product": {
      "type": "relation",
      "relation": "Has main product",
      "targetSchema": "Product"
    },
    "Products": {
      "type": "relation",
      "relation": "Has product",
      "targetSchema": "Product",
      "multiple": true
    },
    "Status": {
      "type": "select",
      "options": [
        { "id": "opt_active",    "label": "Active" },
        { "id": "opt_inactive",  "label": "Inactive" },
        { "id": "opt_acquired",  "label": "Acquired" },
        { "id": "opt_dissolved", "label": "Dissolved" }
      ],
      "required": true
    },
    "World domination progress": {
      "type": "number",
      "minimum": 0,
      "maximum": 100,
      "default": 0
    }
  }
}
```

## Related Documentation

- [Subject Format](subject-format.md) — format for the Subject data that follows Schemas.
