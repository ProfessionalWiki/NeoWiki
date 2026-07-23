---
title: Lua API
order: 2
---
# Lua API

NeoWiki provides a Scribunto library at `mw.neowiki` for accessing structured data from Lua
modules. Use Lua when you need to render multiple properties, iterate over collections, or build
custom output. For simple inline values, the [parser functions](parser-functions.md) are usually
enough.

| If you want to... | Use |
|-------------------|-----|
| Read one value from a property | [`nw.getValue`](#nwgetvaluepropertyname-options) |
| Read every value from a multi-valued property | [`nw.getAll`](#nwgetallpropertyname-options) |
| Get a page's Main Subject (label, schema, all properties) | [`nw.getMainSubject`](#nwgetmainsubjectpagename) |
| Get a Subject by its ID, regardless of which page it's on | [`nw.getSubject`](#nwgetsubjectsubjectid) |
| Run a read-only Cypher query | [`nw.query`](#nwquerycypher-params) |
| Run a read-only SPARQL query | [`nw.sparqlQuery`](#nwsparqlquerysparql) |
| List all Child Subjects on a page | [`nw.getChildSubjects`](#nwgetchildsubjectspagename) |
| Inspect a Schema | [`nw.getSchema`](#nwgetschemaname) |

For definitions of terms like Subject, Schema, and Statement, see the [Glossary](../glossary.md).

## Loading the library

```lua
local nw = require('mw.neowiki')
```

## Functions

### `nw.getValue(propertyName, options)`

Returns a single scalar value for a property. For multi-valued properties, returns the **first**
value. Use [`nw.getAll()`](#nwgetallpropertyname-options) when you need every value.

| Parameter | Type | Description |
|-----------|------|-------------|
| `propertyName` | string | Required. The name of the property. |
| `options` | table | Optional. `{ page = '...' }` or `{ subject = '...' }`. If both are passed, `subject` takes precedence. |

The current-page (no options) and `{ page = '...' }` forms read the page's **Main Subject**; a property
that lives only on a Child Subject is not found. Use `{ subject = '...' }` to address a specific Subject
(including a Child) by ID.

#### Returns

The first value of the property, type-converted for Lua:

| Property type | Returned type |
|---------------|---------------|
| `text`, `url`, `select`, `date`, `dateTime` | string |
| `number` | number |
| `boolean` | boolean |
| `relation` | string (target Subject's label, falls back to target ID if lookup fails) |

Returns `nil` when the Subject does not exist, has no value for the property, or the value is
empty.

#### Examples

```lua
nw.getValue('Founded at')                              --> 2005
nw.getValue('Status')                                  --> "Active"
nw.getValue('Process owner')                           --> "Sarah Naumann"
nw.getValue('Status', { page = 'ACME Inc' })           --> "Active"
nw.getValue('City', { subject = 's1abc5def6ghi78' })   --> "Berlin"
```

### `nw.getAll(propertyName, options)`

Returns every value for a property as a 1-indexed Lua table. Even single-valued properties are
wrapped in a 1-element table.

Same parameters and resolution rules as [`nw.getValue()`](#nwgetvaluepropertyname-options).

#### Returns

A 1-indexed Lua table of values, in the order they are stored on the Subject, type-converted as in
`getValue`. For relations, each entry is the target Subject's label.

Returns `nil` under the same conditions as `getValue`.

#### Examples

```lua
nw.getAll('Websites')
--> { [1] = "https://acme.com", [2] = "https://acme.org" }

nw.getAll('Products')
--> { [1] = "Foo", [2] = "Bar", [3] = "Baz" }

local websites = nw.getAll('Websites')
if websites then
    for _, url in ipairs(websites) do
        mw.log(url)
    end
end
```

### `nw.getMainSubject(pageName)`

Returns the full data of a page's Main Subject as a Lua table.

| Parameter | Type | Description |
|-----------|------|-------------|
| `pageName` | string | Optional. Defaults to the current page. |

#### Returns

A Subject table (see [Subject table format](#subject-table-format)) or `nil` if the page does not
exist or has no Main Subject.

#### Examples

```lua
local subject = nw.getMainSubject()
if subject then
    mw.log(subject.label)         --> "ACME Inc."
    mw.log(subject.schema)        --> "Company"
end

local other = nw.getMainSubject('Berlin')
```

### `nw.getSubject(subjectId)`

Returns the full data of any Subject by its ID, regardless of which page it lives on.

| Parameter | Type | Description |
|-----------|------|-------------|
| `subjectId` | string | Required. A Subject ID. |

#### Returns

A Subject table (see [Subject table format](#subject-table-format)) or `nil` if no Subject exists
with that ID (or the ID is malformed).

#### Examples

```lua
local subject = nw.getSubject('s1abc5def6ghi78')
```

### `nw.getChildSubjects(pageName)`

Returns every Child Subject on a page as a 1-indexed Lua table.

| Parameter | Type | Description |
|-----------|------|-------------|
| `pageName` | string | Optional. Defaults to the current page. |

#### Returns

A 1-indexed Lua table of Subject tables (see [Subject table format](#subject-table-format)).
Returns an empty table `{}` (not `nil`) if the page has no Child Subjects, so it's safe to
iterate the result directly with `ipairs`.

#### Examples

```lua
local children = nw.getChildSubjects()

for _, child in ipairs(children) do
    mw.log(child.label)
end
```

### `nw.query(cypher, params)`

Runs a read-only Cypher query against the graph database and returns each row as a Lua table. It
is available only when a Neo4j graph backend is configured; on a wiki without one,
`mw.neowiki.query` is nil.

| Parameter | Type | Description |
|-----------|------|-------------|
| `cypher` | string | Required. A Cypher query. Must be read-only (no `CREATE`, `SET`, `DELETE`, etc.). |
| `params` | table | Optional. Parameter name → value. Use `$name` in the query to reference them. |

#### Returns

A 1-indexed Lua table of rows. Each row is a string-keyed table where the keys are the Cypher
`RETURN` aliases. An empty result is returned as `{}`, so it is safe to iterate with `ipairs`
without a `nil` check.

Scalar values come back as strings, numbers, booleans, or `nil`. Nested Cypher lists become
1-indexed tables; Cypher maps become string-keyed tables. Graph types convert as follows:

| Cypher type | Lua shape |
|-------------|-----------|
| Node | `{ id, labels, properties }` |
| Relationship | `{ id, type, startNodeId, endNodeId, properties }` |
| Path | `{ nodes, relationships }` |
| `date` | ISO 8601 date string, e.g. `"2023-10-01"` |
| `datetime` / zoned `time` | ISO 8601 string with offset, e.g. `"2023-09-13T14:22:23+00:00"` / `"14:22:23+02:00"` |
| `localdatetime` / `localtime` | ISO 8601 string without offset, e.g. `"2023-09-13T14:22:23"` / `"09:30:00"` |
| `duration` | `{ months, days, seconds, nanoseconds }` |
| `point` | `{ x, y, crs, srid }` (plus `z` for 3D points) |

#### Errors

Always throws on failure; wrap in `pcall` if you need graceful degradation.

- Empty or whitespace-only `cypher`.
- Write or non-read-only queries.
- Cypher syntax errors, missing parameters, or database errors.

#### Expensive

Every call counts as an expensive parser function.

#### Examples

```lua
local rows = nw.query( 'MATCH (s:Subject) RETURN s.name LIMIT 5' )

for _, row in ipairs( rows ) do
    mw.log( row['s.name'] )
end
```

```lua
-- Parameterised — always prefer this over concatenating values into the query.
local rows = nw.query(
    'MATCH (s:Subject {schema: $schema}) WHERE s.`Valid` = $valid RETURN s.name, s.`Expiry date`',
    { schema = 'ISMS Document', valid = 'Yes' }
)
```

Integer comparisons need an explicit cast in the query — e.g. `WHERE s.year = toInteger($year)`.

### `nw.sparqlQuery(sparql)`

Runs a read-only SPARQL query against the first configured
[SPARQL store](../operations/installation.md#optional-sparql-graph-stores) and returns the results as a Lua table.
The SPARQL counterpart of `nw.query`. It is available only when a SPARQL store is configured; on a wiki
without one, `mw.neowiki.sparqlQuery` is nil.

| Parameter | Type | Description |
|-----------|------|-------------|
| `sparql` | string | Required. A SPARQL `SELECT` or `ASK` query. Read-only by protocol. `CONSTRUCT` and `DESCRIBE` are not supported. |

#### Returns

The W3C [`application/sparql-results+json`](https://www.w3.org/TR/sparql11-results-json/) document as a
Lua table, preserving its standard structure: `head.vars` and `results.bindings` for a `SELECT`, or
`boolean` for an `ASK`. Every JSON array (`head.vars`, `results.bindings`) is a 1-indexed Lua sequence;
each binding is a string-keyed table of RDF terms (`{ type, value, datatype?, ['xml:lang']? }`).

#### Errors

Always throws on failure; wrap in `pcall` if you need graceful degradation.

- Empty or whitespace-only `sparql`.
- A query the store rejects (e.g. a SPARQL syntax error), or the store being unavailable.

#### Expensive

Every call counts as an expensive parser function.

#### Examples

```lua
local results = nw.sparqlQuery(
    'SELECT ?label WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#label> ?label } LIMIT 5'
)

for _, binding in ipairs( results.results.bindings ) do
    mw.log( binding.label.value )
end
```

### `nw.getSchema(name)`

Returns a Schema as a Lua table so a module can inspect it at runtime.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | string | Required. The Schema name (e.g. `'Company'`). |

#### Returns

A Schema table, or `nil` if no Schema with that name exists. An empty or whitespace-only `name`
and the reserved names `page` and `subject` also return `nil`. Guard with `if schema then`.

Top-level fields:

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | The Schema name. |
| `description` | string | The Schema description. Omitted when empty. |
| `properties` | table | 1-indexed list of properties, in Schema-defined order. |

Every property entry has `name`, `type`, and `required`, plus `description` and `default` when
those are set. Beyond that core, the fields depend on `type`:

| Property type | Always present | Present only when set |
|---------------|----------------|------------------------|
| `text` | `multiple`, `uniqueItems` | `minLength`, `maxLength` |
| `url` | `multiple`, `uniqueItems` | — |
| `number` | — | `precision`, `minimum`, `maximum` |
| `date`, `dateTime` | — | `minimum`, `maximum` |
| `select` | `multiple`, `options` (1-indexed list of `{ id, label }` entries) | — |
| `relation` | `multiple`, `relation`, `targetSchema` | — |
| `boolean` | — | — |

Optional fields are **omitted entirely** when unset, so check with `if prop.description then …`.
`required` is always present, and `multiple`/`uniqueItems` are present for the types the table
marks under **Always present**; where present, these boolean flags are emitted even when `false`,
so read them directly rather than testing truthiness. For a type where the table does not list
`multiple`/`uniqueItems`, they are absent (`nil`), not `false`.

#### Errors

Raises a Lua error only when `name` is missing or not a string. Wrap a computed or possibly-`nil`
name in `pcall`.

#### Expensive

Every call counts as an expensive parser function.

#### Examples

```lua
-- Fetch a Schema and read its first property.
local schema = nw.getSchema( 'Company' )
if schema then
    mw.log( schema.name )                --> "Company"
    mw.log( schema.properties[1].name )  --> first property's name
end
```

```lua
-- Render a property overview for the current page's Main Subject.
local subject = nw.getMainSubject()
local schema = subject and nw.getSchema( subject.schema )

if schema then
    for _, prop in ipairs( schema.properties ) do
        local tag = prop.required and ' (required)' or ''
        mw.log( prop.name .. ' — ' .. prop.type .. tag )
    end
end
```

```lua
-- Read optional fields only after checking they're set.
for _, prop in ipairs( schema.properties ) do
    if prop.type == 'number' and prop.minimum then
        mw.log( prop.name .. ' min: ' .. prop.minimum )
    end
end
```

## Subject table format

Subject tables returned by `getMainSubject`, `getSubject`, and `getChildSubjects` have this
structure:

```lua
{
    id = 's1abc5def6ghi78',
    label = 'ACME Inc.',
    schema = 'Company',
    statements = {
        ['Headquarters'] = { type = 'text',     values = { [1] = 'Berlin' } },
        ['Founded at']   = { type = 'number',   values = { [1] = 2005 } },
        ['Status']       = { type = 'select',   values = { [1] = 'Active' } },
        ['Websites']     = { type = 'url',      values = { [1] = 'https://acme.com', [2] = 'https://acme.org' } },
        ['Active']       = { type = 'boolean',  values = { [1] = true } },
        ['Products']     = {
            type = 'relation',
            values = {
                [1] = { id = 'r1...', target = 's1...', label = 'Foo' },
                [2] = { id = 'r1...', target = 's1...', label = 'Bar' },
            },
        },
    },
}
```

Notes:

- `statements` is keyed by property name. `values` within each statement is 1-indexed.
- `type` is the property type at the time the Subject was last edited. If the Schema has changed
  since (e.g. a property was changed from `text` to `select`), older Subjects keep their original
  type until they are re-saved.
- A relation's `label` falls back to the target Subject ID if the label cannot be looked up
  (e.g. a broken reference).
- Per-relation `properties` (qualifiers) are not currently exposed via Lua. Use the REST API if
  you need them.

## Performance

Each of these counts as an expensive parser function (against the page's expensive function limit):
`nw.query`, `nw.sparqlQuery`, `nw.getSchema`, and `nw.getSubject` on every call; `nw.getValue`,
`nw.getAll`, `nw.getMainSubject`, and `nw.getChildSubjects` only when passed a `page`/`subject`
option or page name. Reads of the current page do not count.

## Related Documentation

- [Parser Functions](parser-functions.md) — Wikitext interface to the same data
- [Glossary](../glossary.md) — Definitions of Subject, Schema, Statement, etc.
- [Schema Format](../api/schema-format.md) — How Schemas and properties are defined
- [Subject Format](../api/subject-format.md) — How Subject data is stored
- [Graph Model](../api/graph-model.md) — Neo4j node and relationship structure
