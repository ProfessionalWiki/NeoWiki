# Parser Functions

NeoWiki provides three parser functions for use in wikitext.

For definitions of terms like Subject, Schema, and Layout, see the [Glossary](Glossary.md).

## `{{#view}}`

Renders a Subject as HTML on the page using a [View Type](Glossary.md#view-type) (currently
`infobox`). Optionally uses a [Layout](Glossary.md#layout) to control which properties are shown
and how.

### Syntax

```
{{#view: }}                         renders the current page's Main Subject
{{#view: <subjectId>}}              renders the specified Subject
{{#view: <subjectId> | <layout>}}   renders the specified Subject with the named Layout
{{#view:  | <layout>}}              renders the current page's Main Subject with the named Layout
```

### Behavior

- With no `subjectId`, renders the current page's Main Subject. Returns an empty string if the
  page has no Main Subject.
- With a `layoutName`, applies the Layout's display rules and view type. Without one, falls back
  to showing all properties in schema-defined order.
- Output is rendered client-side by the NeoWiki frontend (the parser function emits a placeholder).

### Examples

```
{{#view: }}
{{#view: s1abc5def6ghi78}}
{{#view: s1abc5def6ghi78 | CompanyOverview}}
{{#view:  | CompanyOverview}}
```

## `{{#neowiki_value}}`

Returns the value of a single property from a Subject, formatted as a string. Designed for inline
use in wikitext and for other extensions that need to read NeoWiki metadata via parser functions.

### Syntax

```
{{#neowiki_value: <propertyName> }}
{{#neowiki_value: <propertyName> | page=<pageName> }}
{{#neowiki_value: <propertyName> | subject=<subjectId> }}
{{#neowiki_value: <propertyName> | separator=<separator> }}
```

### Parameters

| Parameter | Description |
|-----------|-------------|
| `propertyName` (positional) | The name of the property to read. Required. |
| `page` | Read from the Main Subject of the named page. Defaults to the current page. |
| `subject` | Read from the Subject with the given ID. Takes precedence over `page`. |
| `separator` | Separator for multi-valued properties. Defaults to `, `. Trimmed by MediaWiki, so trailing whitespace is not preserved. |

### Output by property type

| Type | Output |
|------|--------|
| `text`, `url`, `select` | The string value. Multiple values joined with `separator`. |
| `number` | The number as a string. |
| `boolean` | `true` or `false`. |
| `relation` | The target Subject's label. Multiple targets joined with `separator`. Falls back to the target Subject ID if the label cannot be looked up. |

### Empty results

Returns an empty string when:

- The property name is empty
- The page has no Main Subject (or the named page does not exist)
- The Subject ID is invalid or not found
- The Subject has no statement for the property
- The value is empty

### Examples

```
Founded: {{#neowiki_value: Founded at}}
Status: {{#neowiki_value: Status | page=ACME Inc}}
Process owner: {{#neowiki_value: Process owner | subject=s1abc5def6ghi78}}
Tags: {{#neowiki_value: Tags | separator=;}}
```

A typical integration with another extension's parser function:

```
{{#read-confirmation: audience={{#neowiki_value: Target audience}}}}
```

## `{{#cypher_raw}}`

Executes a read-only Cypher query against the Neo4j graph database and returns the raw results
as JSON in a `<pre>` block. Primarily intended for development and debugging.

For end-user dashboards, prefer the upcoming table rendering (or use the
[Lua API](LuaAPI.md) with [`nw.query()`](LuaAPI.md) once Layer 3 lands) to format results.

### Syntax

```
{{#cypher_raw: <cypherQuery>}}
```

### Behavior

- Validates the query is read-only (rejects `CREATE`, `DELETE`, `MERGE`, `SET`, `REMOVE`, etc).
- Executes against a read-only Neo4j connection.
- Returns formatted JSON wrapped in a code block.
- Returns a styled error message in a `<div class="error">` for empty queries, write queries,
  query execution failures, or JSON encoding failures.

### Examples

```
{{#cypher_raw: MATCH (s:Subject) RETURN s.name LIMIT 10}}

{{#cypher_raw: MATCH (s:Subject) WHERE 'Company' IN labels(s) RETURN s.name, s.`Founded at`}}
```

## Related Documentation

- [Lua API](LuaAPI.md) — Programmatic access to the same data via `mw.neowiki`
- [Glossary](Glossary.md) — Definitions of Subject, Schema, Layout, View, etc.
- [SchemaFormat.md](SchemaFormat.md) — How Schemas and properties are defined
- [SubjectFormat.md](SubjectFormat.md) — How Subject data is stored
- [GraphModel.md](GraphModel.md) — Neo4j node and relationship structure (relevant for
  `{{#cypher_raw}}` queries)
