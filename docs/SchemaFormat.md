# Schema JSON Format

This document describes the JSON format used to store Schema data on pages in the Schema namespace (7474) and
returned by the REST API.

For definitions of terms like Schema and Property Definition, see the [Glossary](Glossary.md).

A JSON Schema for validation is available at
[`src/Persistence/MediaWiki/schemaContentSchema.json`](../src/Persistence/MediaWiki/schemaContentSchema.json).

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

Each property definition in `propertyDefinitions` has common fields and type-specific fields.

### Common Fields

All property types share these fields:

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `type` | string | Yes | - | The property type. See Property Types below. |
| `description` | string | No | `""` | Human-readable description of the property |
| `required` | boolean | No | `false` | Whether a value is required for this property |
| `default` | varies | No | `null` | Default value when none is provided |

## Property Types

### Text (`text`)

Plain text values.

```json
{
  "type": "text"
}
```

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `multiple` | boolean | `false` | Allow multiple values |
| `uniqueItems` | boolean | `false` | Require unique values (only meaningful when `multiple` is true) |

Example with options:

```json
{
  "type": "text",
  "multiple": true,
  "uniqueItems": true,
  "required": true,
  "description": "Tags for this item"
}
```

### URL (`url`)

URL values.

```json
{
  "type": "url"
}
```

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `multiple` | boolean | `false` | Allow multiple values |
| `uniqueItems` | boolean | `false` | Require unique values |

### Number (`number`)

Numeric values (integer or float).

```json
{
  "type": "number"
}
```

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `precision` | number | `null` | Number of decimal places for display |
| `minimum` | number | `null` | Minimum allowed value |
| `maximum` | number | `null` | Maximum allowed value |

Example with constraints:

```json
{
  "type": "number",
  "minimum": 0,
  "maximum": 100,
  "precision": 2,
  "description": "Percentage value"
}
```

### Relation (`relation`)

References to other Subjects.

```json
{
  "type": "relation",
  "relation": "Has author",
  "targetSchema": "Person"
}
```

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `relation` | string | Yes | - | The relation type name (used in Neo4j as relationship type) |
| `targetSchema` | string | Yes | - | Name of the schema that target subjects must follow |
| `multiple` | boolean | No | `false` | Allow multiple relations |

Example:

```json
{
  "type": "relation",
  "relation": "Has product",
  "targetSchema": "Product",
  "multiple": true,
  "description": "Products made by this company"
}
```

## Reserved Property Types

The following types are defined in the JSON schema but not yet implemented:

- `email` - Email addresses
- `phoneNumber` - Phone numbers
- `date` - Date values
- `time` - Time values
- `dateTime` - Combined date and time
- `duration` - Time durations
- `currency` - Monetary values
- `progress` - Progress indicators
- `checkbox` - Boolean checkbox

## REST API

### Reading Schemas

`GET /rest.php/neowiki/v0/schema/{schemaName}`

Returns the schema wrapped in a response object:

```json
{
  "schema": {
    "description": "...",
    "propertyDefinitions": { ... }
  }
}
```

Returns `{"schema": null}` if the schema is not found.

### Searching Schema Names

`GET /rest.php/neowiki/v0/schema-names/{search}`

Returns a list of schema names matching the search term.

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

- [ADR 006: Schemas](adr/006_Schemas.md)
- [ADR 009: Move Away from JSON Schema](adr/009_Move_Away_from_JSON_Schema.md)
- [ADR 017: Names as Identifiers](adr/017_Names_as_Identifiers.md)
- [SubjectFormat.md](SubjectFormat.md) - Format for Subject data that follows schemas
