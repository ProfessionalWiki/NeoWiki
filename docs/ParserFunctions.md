# Parser Functions

NeoWiki provides three parser functions for use in wikitext.

| If you want to... | Use |
|-------------------|-----|
| Render a Subject visually on the page | [`{{#view}}`](#view) |
| Insert one property's value inline as text | [`{{#neowiki_value}}`](#neowiki_value) |
| Run a custom Cypher query and see the raw results | [`{{#cypher_raw}}`](#cypher_raw) |

For programmatic access from Lua modules, see the [Lua API](LuaAPI.md).

For definitions of terms like Subject, Schema, and Layout, see the [Glossary](Glossary.md).

## `{{#view}}`

Renders a Subject as HTML on the page using a [View Type](Glossary.md#view-type) (currently
`infobox`). Optionally uses a [Layout](Glossary.md#layout) to control which properties are shown
and how.

### Syntax

```
{{#view: }}                              renders the current page's Main Subject
{{#view: <subjectId>}}                   renders the specified Subject
{{#view: <subjectId> | <layoutName>}}    renders the specified Subject with the named Layout
{{#view:  | <layoutName>}}               renders the current page's Main Subject with the named Layout
```

### Parameters

| Parameter | Description |
|-----------|-------------|
| `subjectId` (positional) | Subject ID to render. Defaults to the current page's Main Subject. |
| `layoutName` (positional) | Layout to apply. Without one, all properties are shown in schema order. |

### Notes

- Returns an empty string if the resolved Subject does not exist.
- The View Type (e.g. `infobox`) is determined by the Layout. Only `infobox` is implemented today.
- Rendering happens client-side, so the Subject view appears once the page's JavaScript has loaded.

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
| `page` | Read from the Main Subject of the named page. Defaults to the current page. Ignored when `subject` is also passed. |
| `subject` | Read from the Subject with the given ID. Takes precedence over `page`. |
| `separator` | Separator for multi-valued properties. Defaults to `, `. |

### Output by property type

| Type | Output |
|------|--------|
| `text`, `url`, `select` | The string value. Multiple values joined with `separator`. |
| `number` | The number, e.g. `42` or `19.99`. |
| `boolean` | `true` or `false`. |
| `relation` | The target Subject's label. Multiple targets joined with `separator`. Falls back to the target Subject ID if the label cannot be looked up. |

Boolean and number values are always rendered, even for `false` and `0` — these are not treated
as "empty".

### Output is plain text

The output is HTML-escaped and not interpreted as wikitext. Links, templates, and HTML inside
property values render as literal characters. Useful when you want exactly what the user typed;
not useful when you want to emit links or styled markup.

When you pass the result to another parser function as an argument, that function also receives
HTML-encoded text — a value of `Engineers & Designers` arrives as `Engineers &amp; Designers`.

### Returns empty when

- The Subject does not exist on the page (or named page), or the Subject ID was not found.
- The Subject has no value for that property.
- The value is an empty collection (e.g. a multi-valued text property with no entries).

### Examples

```
Founded: {{#neowiki_value: Founded at}}
Status: {{#neowiki_value: Status | page=ACME Inc}}
Process owner: {{#neowiki_value: Process owner | subject=s1abc5def6ghi78}}
Tags: {{#neowiki_value: Tags | separator=;}}
```

Passing a value to another extension's parser function:

```
{{#read-confirmation: audience={{#neowiki_value: Target audience}}}}
```

## `{{#cypher_raw}}`

Executes a read-only Cypher query and returns the raw results as JSON in a code block. Mainly
useful for development and debugging.

For end-user dashboards, formatted query result rendering and a Lua `nw.query()` function are
planned (see [#736](https://github.com/ProfessionalWiki/NeoWiki/issues/736)).

### Syntax

```
{{#cypher_raw: <cypherQuery>}}
```

### Notes

- Only read queries are allowed. Anything that creates, modifies, or deletes data is rejected,
  including `CALL` (even for read-only procedures).
- Errors (rejected queries, syntax errors, the database being unavailable, etc.) render as a
  styled error message in place of the result.
- Output is HTML-escaped, so query results containing `<`, `>`, `&`, etc. display safely.
- The output is wrapped in `<pre><code class="json">` and the error message in
  `<div class="error">`, so you can target either with CSS.

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
