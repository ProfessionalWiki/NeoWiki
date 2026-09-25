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
  "labelTemplate": "{Title}",
  "propertyDefinitions": {
    "<property-name>": { ... },
    "<property-name>": { ... }
  }
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `description` | string | No | Human-readable description of the schema |
| `labelTemplate` | string | No | How a Subject of the Schema is labelled when its `label` is absent; see [Label template](#label-template) |
| `propertyDefinitions` | object | Yes | Map of property names to property definition objects |

## Label template

`labelTemplate` is text in which each `{Property name}` stands for that property's first value in the Subject;
everything else is literal. `{Museum} attendance {Year}` labels a Subject with `Museum` "Rijksmuseum" and `Year` 2024
as "Rijksmuseum attendance 2024". A blank template is the same as none.

- A select value reads as its option's label, a monolingual text as its first text whatever its language, a number
  as stored whatever its `precision`, and a `date` or `dateTime` as stored, such as `2024-03-01`.
- A placeholder the Subject has no value for reads as nothing, and the text around it stays: `{Name} ({Born})` gives
  "Ada ()" for a Subject without `Born`. Runs of whitespace collapse to one space, and the label is trimmed. When no
  placeholder has a value, the template gives no label.
- A placeholder may name only a property of this Schema whose type stores text or numbers: any built-in type but
  `relation` and `boolean`. A property of a type not registered on the wiki reads as nothing. A Schema save that
  breaks this, by its template or by renaming, removing or retyping a property the template names, is rejected, as is
  a template naming no property.

The template label is computed on read and never stored: `label` stays absent, and
[`displayName`](subject-format.md#reading-subjects) carries it. REST reads a changed template at once and rendered
pages as they are re-parsed; the graph's `name` and `rdfs:label` take it when each page is next saved, or after a
[rebuild](../operations/maintenance.md#rebuilding-the-graph).

## Schema references

Wherever a Schema is named — a Subject's [`schema`](subject-format.md#subject-object) field, a relation property's
`targetSchema` — the value is a reference.

A string names a Schema of this wiki by its page title in the Schema namespace, without the namespace prefix. Any
spelling of that title is stored as the title itself — `person` as `Person`, `Person_name` as `Person name` — so a
reference read back can differ from the one written.

A Schema from another Source ([ADR 023](../adr/023-subject-sources.md)) is an object instead:

```json
{ "source": "otherwiki", "name": "Company" }
```

`source` is a source key, written as for a [Subject ID](subject-format.md#ids). Naming this wiki's own Source is the
same reference as the bare name, and is stored as the bare name.

A reference to a Source this wiki does not have resolves to no Schema, reported as
[`schema-not-found`](validation-codes.md#schema-not-found).

## Property Definition

Every property definition carries the common fields below plus the type-specific fields for its `type`.

### Common Fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `type` | string | Yes | - | The property type. See [Property Types](#property-types). |
| `description` | string | No | `""` | Human-readable description of the property |
| `required` | boolean or object | No | `false` | Whether a value is required. Accepts a [severity](#constraint-severity). |
| `default` | varies | No | `null` | Default value when none is provided |

## Constraint severity

Every Constraint carries a severity of `error` or `warning`, which decides whether violating it can
block a write. Write a Constraint either as the bare value, which keeps the default `warning`, or as
an object carrying the severity:

```json
{
  "type": "number",
  "required": { "severity": "error" },
  "minimum": 0,
  "maximum": { "value": 100, "severity": "error" }
}
```

Boolean Constraints (`required`, `uniqueItems`) take no `value` in the object form — writing the
object at all implies `true`. `multiple` constrains when it is `false`, so its object form carries
`"value": false`. Every other Constraint carries its value under `value`, including `options`, whose
`value` is the options array.

The Constraints that accept a severity are `required`, `multiple`, `minimum`, `maximum`, `minLength`,
`maxLength`, `uniqueItems`, and `options`. Only `select`, `relation` and `monolingualText` check
`multiple`, so a severity on it is inert elsewhere. Severity is a Constraint concept, so it does not
apply to Display Attributes such as `precision`, where it is discarded, nor to the shape-declaring
fields `type`, `relation`, and `targetSchema`, where it is rejected when the Schema is saved.

Canonical output emits the bare form whenever the severity is the default, so a Schema that sets no
severities round-trips unchanged. Which violation each Constraint produces, and the fixed severities
of the codes no Constraint backs, are covered in
[Validation codes](validation-codes.md#severity-blocking-and-enforcement).

## Property Types

### Text (`text`)

Plain text values.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `multiple` | boolean or object | `false` | Allow multiple values |
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

### Monolingual text (`monolingualText`)

Text values that each carry the language they are in ([ADR 34](../adr/034-monolingual-text-value-type.md)).

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `multiple` | boolean or object | `false` | Allow multiple values |
| `uniqueItems` | boolean | `false` | Reject duplicates, comparing text and language together (only with `multiple`) |
| `minLength` | number | `null` | Minimum trimmed length of each text |
| `maxLength` | number | `null` | Maximum trimmed length of each text |

```json
{
  "type": "monolingualText",
  "multiple": true,
  "uniqueItems": true
}
```

### URL (`url`)

URL values.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `multiple` | boolean or object | `false` | Allow multiple values |
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
| `multiple` | boolean or object | `false` | Allow selecting more than one |

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
| `targetSchema` | string or object | Yes | - | [Reference](#schema-references) to the Schema that target Subjects must follow |
| `multiple` | boolean or object | No | `false` | Allow multiple relations |

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
