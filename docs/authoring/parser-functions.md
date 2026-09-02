---
title: Parser Functions
order: 1
---
# Parser Functions

NeoWiki provides these parser functions for use in wikitext.

| If you want to... | Use |
|-------------------|-----|
| Render a Subject visually on the page | [`{{#view}}`](#view) |
| Insert one property's value inline as text | [`{{#neowiki_value}}`](#neowiki_value) |
| Run a custom Cypher query and see the raw results | [`{{#cypher_raw}}`](#cypher_raw) |
| Run a custom SPARQL query and see the raw results | [`{{#sparql_raw}}`](#sparql_raw) |

For programmatic access from Lua modules, see the [Lua API](lua-api.md).

For definitions of terms like Subject, Schema, and Layout, see the [Glossary](../glossary.md).

## Permissions

Every parser function reads as the user the page is parsed for. Subjects that user cannot read are
treated as absent. `{{#cypher_raw}}` and `{{#sparql_raw}}` need the `neowiki-query` right.
`{{#view}}` only places a marker at parse time; the Subject it shows is fetched per viewer over the
REST API, under that viewer's permissions.

## `{{#view}}`

Renders a Subject as HTML on the page using a [View Type](../glossary.md#view-type) (currently
`infobox`). Optionally uses a [Layout](../glossary.md#layout) to control which properties are shown
and how.

### Syntax

```
{{#view: }}                                           renders the current page's Main Subject
{{#view: <subjectId>}}                                renders the specified Subject
{{#view: subject=<subjectId>}}                        same, with the Subject specified as a named argument
{{#view: layout=<layoutName>}}                        renders the current page's Main Subject with the named Layout
{{#view: <subjectId> | layout=<layoutName>}}          renders the specified Subject with the named Layout
{{#view: subject=<subjectId> | layout=<layoutName>}}  same, with the Subject specified as a named argument
```

### Parameters

| Parameter | Description |
|-----------|-------------|
| `<subjectId>` (positional) | Subject ID to render. Defaults to the current page's Main Subject. |
| `subject=<subjectId>` | Named alternative to the positional form. Cannot be combined with the positional form. |
| `layout=<layoutName>` | Layout to apply. Without one, all properties are shown in schema order. |

### Notes

- Renders nothing when the Subject does not exist, or when no Subject is given and the page has no
  Main Subject.
- Rendering happens client-side, so the Subject view appears once the page's JavaScript has loaded.

### Examples

```
{{#view: }}
{{#view: s1abc5def6ghi78}}
{{#view: layout=CompanyOverview}}
{{#view: s1abc5def6ghi78 | layout=CompanyOverview}}
{{#view: subject=s1abc5def6ghi78 | layout=CompanyOverview}}
```

Unknown named arguments, more than one positional argument, or specifying the
Subject both positionally and as `subject=` produce a visible parser error.

## `{{#neowiki_value}}`

Returns the value of a single property from a Subject, formatted as a string.

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
| `text`, `url`, `select`, `date`, `dateTime` | The string value. Multiple values joined with `separator`. |
| `number` | The number, e.g. `42` or `19.99`. |
| `boolean` | `true` or `false`. |
| `relation` | The target Subject's display name. Multiple targets joined with `separator`. Falls back to the target Subject ID when the target cannot be looked up or its page is not readable. |

Boolean and number values are always rendered, even for `false` and `0` — these are not treated
as "empty".

### Output is plain text

The output is HTML-escaped and not interpreted as wikitext. Links, templates, and HTML inside
property values render as literal characters.

When you pass the result to another parser function as an argument, that function also receives
HTML-encoded text — a value of `Engineers & Designers` arrives as `Engineers &amp; Designers`.

### Returns empty when

- The Subject does not exist on the page (or named page), or the Subject ID was not found.
- The Subject's page is not readable (see [Permissions](#permissions)).
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

Executes a read-only Cypher query and returns the raw results as JSON in a code block, for
development and debugging. Available only when a Neo4j graph backend is configured; on a wiki
without one, `{{#cypher_raw: …}}` is not registered and renders as ordinary wikitext. The
[Graph Model](../api/graph-model.md) describes the node and relationship structure to query.

For formatted result rendering, build it in a template with Lua
[`nw.query()`](lua-api.md#nwquerycypher-params).

### Syntax

```
{{#cypher_raw: <cypherQuery>}}
```

### Notes

- Only read queries are allowed. Anything that creates, modifies, or deletes data is rejected,
  including `CALL` (even for read-only procedures).
- Requires the `neowiki-query` right (see [Permissions](#permissions)).
- Results are capped at the `maxRows` and stopped at the `timeoutSeconds` of the `default` tier of
  `$wgNeoWikiQueryLimits` (see [Limits and tiers](../api/query-api.md#limits-and-tiers)); rows beyond
  the cap are dropped silently.
- Errors (rejected queries, syntax errors, the database being unavailable, etc.) render as a
  styled error message in place of the result.
- Output is HTML-escaped, so query results containing `<`, `>`, `&`, etc. display safely.
- The output is wrapped in `<div class="mw-neowiki-cypher-result"><pre>` and the error message in
  `<div class="error">`, so you can target either with CSS.

### Examples

```
{{#cypher_raw: MATCH (s:Subject) RETURN s.name LIMIT 10}}

{{#cypher_raw: MATCH (s:Subject) WHERE 'Company' IN labels(s) RETURN s.name, s.`Founded at`}}
```

## `{{#sparql_raw}}`

Executes a read-only SPARQL query against the first configured [SPARQL store](../operations/installation.md#optional-sparql-graph-stores)
and returns the raw results as JSON in a code block. The SPARQL counterpart of `{{#cypher_raw}}`, for
development and debugging. Available only when a SPARQL store is configured; on a wiki without one,
`{{#sparql_raw: …}}` is not registered and renders as ordinary wikitext.

The JSON is the W3C [`application/sparql-results+json`](https://www.w3.org/TR/sparql11-results-json/)
document — the standard `head` / `results` structure (or `boolean` for an `ASK` query).

### Syntax

```
{{#sparql_raw: <sparqlQuery>}}
```

### Notes

- Read-only: the query cannot modify the store.
- Requires the `neowiki-query` right (see [Permissions](#permissions)).
- No row cap is applied — the full results document is returned. Queries stop at the `timeoutSeconds`
  of the `default` tier of `$wgNeoWikiQueryLimits` (see
  [Limits and tiers](../api/query-api.md#limits-and-tiers)).
- Errors (a query the store rejects, the store being unavailable, etc.) render as a styled error message
  in place of the result.
- Output is HTML-escaped, so results containing `<`, `>`, `&`, etc. display safely.
- The output is wrapped in `<div class="mw-neowiki-sparql-result"><pre>` and the error message in
  `<div class="error">`, so you can target either with CSS.
- Pages are projected into named graphs, so which of them an unscoped query reaches depends on the store — see
  [RDF Export](../rdf/rdf-export.md#iri-scheme).

### Examples

```
{{#sparql_raw: SELECT ?label WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#label> ?label } LIMIT 10}}
```
