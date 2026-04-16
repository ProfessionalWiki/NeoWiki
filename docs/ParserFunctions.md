# Parser Functions

NeoWiki provides three parser functions for use in wikitext.

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
| `subjectId` (positional) | Subject ID to render. Defaults to the current page's Main Subject. Trimmed; not validated server-side (the frontend handles missing/invalid Subjects). |
| `layoutName` (positional) | Layout to apply. Without one, all properties are shown in schema order. Trimmed. |

### Behavior

- With no `subjectId`, renders the current page's Main Subject. Returns an empty string if the
  page has no Main Subject.
- With a `layoutName`, applies the Layout's display rules and view type. Without one, falls back
  to showing all properties in schema-defined order. The View Type is determined by the Layout
  (currently only `infobox` is implemented); there is no `view_type=` parameter on `{{#view}}`.
- Output is rendered client-side by the NeoWiki frontend. The parser function emits a placeholder
  element of the form `<div class="ext-neowiki-view" data-mw-neowiki-subject-id="..." data-mw-neowiki-layout-name="...">`
  with `noparse` and `isHTML` set, and the frontend replaces it during page load.

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
| `propertyName` (positional) | The name of the property to read. Required. Trimmed; whitespace-only values return empty. |
| `page` | Read from the Main Subject of the named page. Defaults to the current page. Silently ignored when `subject` is also passed. |
| `subject` | Read from the Subject with the given ID. Takes precedence over `page`. Subject IDs are 15 characters starting with `s` (Crockford-style alphabet); invalid IDs return an empty string without erroring. |
| `separator` | Separator for multi-valued properties. Defaults to `, `. Trimmed by MediaWiki, so trailing whitespace is not preserved. |

### Output by property type

| Type | Output |
|------|--------|
| `text`, `url`, `select` | The string value. Multiple values joined with `separator`. |
| `number` | The number as a string. PHP's default formatting (e.g. `19.99`); not locale-aware. |
| `boolean` | The literal string `true` or `false`; not localized. |
| `relation` | The target Subject's label. Multiple targets joined with `separator`. Falls back to the target Subject ID if the label cannot be looked up. Requires the graph database backend (Neo4j) to be available for label resolution. |

### Output is HTML-escaped and not parsed

The output is HTML-escaped before being inserted, and emitted with `noparse` and `isHTML` set.
Consequences:

- A property value of `<b>bold</b> & "quoted"` renders as the literal text `<b>bold</b> & "quoted"`,
  not as bold HTML.
- The output cannot contain wikitext (links, templates, transclusions are not expanded).
- When passing the result to another parser function as an argument, the receiving function
  receives HTML-encoded text. For example, a value of `Engineers & Designers` reaches the inner
  function as `Engineers &amp; Designers`.

### Empty results

Returns an empty string when:

- The `propertyName` is empty (after trimming whitespace)
- The page has no Main Subject, the named page does not exist, or the page name is invalid
- The Subject ID is invalid or not found
- The Subject has no statement for the property
- The value is "empty" — an empty collection of strings or relations. `number` and `boolean`
  values are never empty, so `false` always renders as `"false"` and `0` as `"0"`.

### Examples

```
Founded: {{#neowiki_value: Founded at}}
Status: {{#neowiki_value: Status | page=ACME Inc}}
Process owner: {{#neowiki_value: Process owner | subject=s1abc5def6ghi78}}
Tags: {{#neowiki_value: Tags | separator=;}}
```

A typical integration with another extension's parser function. Note that because the output is
HTML-escaped, the receiving function receives HTML-encoded text — fine for plain identifiers, but
something to be aware of for values that may contain `<`, `>`, `&`, or `"`:

```
{{#read-confirmation: audience={{#neowiki_value: Target audience}}}}
```

## `{{#cypher_raw}}`

Executes a read-only Cypher query against the Neo4j graph database and returns the raw results
as JSON. Primarily intended for development and debugging.

For end-user dashboards, formatted query result rendering and a Lua `nw.query()` function are
planned (see [#736](https://github.com/ProfessionalWiki/NeoWiki/issues/736)).

### Syntax

```
{{#cypher_raw: <cypherQuery>}}
```

The query is trimmed before validation, so surrounding whitespace (including newlines from
multi-line wikitext) is fine.

### Behavior

- Validates the query is read-only via two layers:
  1. A keyword filter rejects queries containing any of `CREATE`, `SET`, `DELETE`, `REMOVE`,
     `MERGE`, `DROP`, `CALL`, `LOAD`, `FOREACH`, `GRANT`, `DENY`, `REVOKE`, or `SHOW`. (Note that
     `CALL` is rejected even for read-only procedures.)
  2. An `EXPLAIN` of the query is run and rejected if the resulting plan contains any non-read
     operators.
- Executes via the Neo4j driver's `readTransaction()` on a dedicated read-only connection
  (separate from the connection used for writes).
- Returns formatted JSON wrapped in a `<pre><code class="json">` block. The JSON is HTML-escaped
  before insertion, so values containing `<`, `>`, `&`, etc. are safely displayed.
- Returns a styled error message in a `<div class="error">` for any failure. Possible causes
  include: an empty query, a query containing a rejected write keyword, a query whose `EXPLAIN`
  plan contains non-read operators, query execution failures (including Neo4j being unavailable
  during validation), and JSON encoding failures.

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
