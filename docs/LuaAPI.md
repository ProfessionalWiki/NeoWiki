# Lua API

NeoWiki provides a Scribunto library at `mw.neowiki` for accessing structured data from Lua
modules. Use Lua when you need to render multiple properties, iterate over collections, or build
custom output. For simple inline values, the [parser functions](ParserFunctions.md) are usually
enough.

| If you want to... | Use |
|-------------------|-----|
| Read one value from a property | [`nw.getValue`](#nwgetvaluepropertyname-options) |
| Read every value from a multi-valued property | [`nw.getAll`](#nwgetallpropertyname-options) |
| Get a page's Main Subject (label, schema, all properties) | [`nw.getMainSubject`](#nwgetmainsubjectpagename) |
| Get a Subject by its ID, regardless of which page it's on | [`nw.getSubject`](#nwgetsubjectsubjectid) |
| Run a read-only Cypher query | [`nw.query`](#nwquerycypher-params) |
| List all Child Subjects on a page | [`nw.getChildSubjects`](#nwgetchildsubjectspagename) |

For definitions of terms like Subject, Schema, and Statement, see the [Glossary](Glossary.md).

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

#### Returns

The first value of the property, type-converted for Lua:

| Property type | Returned type |
|---------------|---------------|
| `text`, `url`, `select` | string |
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

Returns every value for a property as a 1-indexed Lua table. Use this when a property is
multi-valued and you need all values. Even single-valued properties are wrapped in a 1-element
table.

Same parameters and resolution rules as [`nw.getValue()`](#nwgetvaluepropertyname-options).

#### Returns

A 1-indexed Lua table of values, type-converted as in `getValue`. For relations, each entry is
the target Subject's label.

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

Runs a read-only Cypher query against the graph database and returns each row as a Lua table. Use
this when a single property lookup is not enough — for example, to join multiple Subjects, filter
or sort in the query, or build a custom table.

| Parameter | Type | Description |
|-----------|------|-------------|
| `cypher` | string | Required. A Cypher query. Must be read-only (no `CREATE`, `SET`, `DELETE`, etc.). |
| `params` | table | Optional. Parameter name → value. Use `$name` in the query to reference them. |

#### Returns

A 1-indexed Lua table of rows. Each row is a string-keyed table where the keys are the Cypher
`RETURN` aliases. An empty result is returned as `{}`, so it is safe to iterate with `ipairs`
without a `nil` check.

Scalar values come back as strings, numbers, booleans, or `nil`. Nested Cypher lists become
1-indexed tables; Cypher maps become string-keyed tables. Neo4j-specific types convert as follows:

| Cypher type | Lua shape |
|-------------|-----------|
| Node | `{ id, labels, properties }` |
| Relationship | `{ id, type, startNodeId, endNodeId, properties }` |
| Path | `{ nodes, relationships }` |
| Temporal types | Structured table; fields vary by type (`days`, `seconds`, `nanoseconds`, `tzOffsetSeconds`, `tzId`) |
| Duration | `{ months, days, seconds, nanoseconds }` |
| Point | `{ srid, x, y }` (plus `z` for 3D points) |

#### Errors

Always throws on failure; wrap in `pcall` if you need graceful degradation.

- Empty or whitespace-only `cypher`.
- Write or non-read-only queries (rejected by the validator).
- Cypher syntax errors, missing parameters, or database errors (surfaced with the underlying
  message).

#### Expensive

Every call counts as an expensive parser function. Keep an eye on your page's expensive function
limit if a template calls `nw.query` in a loop.

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

Lua numbers are all floats in PHP. If a typed comparison fails, cast in the query — for example
`WHERE s.year = toInteger($year)`.

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

Calls that look up another page or a specific Subject ID count as expensive parser functions
(against the page's expensive function limit). Calls that read from the current page do not.

## Planned additions

The following are not yet implemented:

- `nw.getSchema(name)` — Schema introspection for generic templates. Tracked in
  [#737](https://github.com/ProfessionalWiki/NeoWiki/issues/737).

## Related Documentation

- [Parser Functions](ParserFunctions.md) — Wikitext interface to the same data
- [Glossary](Glossary.md) — Definitions of Subject, Schema, Statement, etc.
- [SchemaFormat.md](SchemaFormat.md) — How Schemas and properties are defined
- [SubjectFormat.md](SubjectFormat.md) — How Subject data is stored
- [GraphModel.md](GraphModel.md) — Neo4j node and relationship structure
