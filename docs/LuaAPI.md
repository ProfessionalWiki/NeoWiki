# Lua API

NeoWiki provides a Scribunto library at `mw.neowiki` for accessing structured data from Lua
modules. This is the programmatic complement to the [parser functions](ParserFunctions.md) — use
parser functions for simple inline values, use Lua for templates that need to render multiple
properties, iterate over collections, or build custom output.

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
| `options` | table | Optional. `{ page = '...' }` or `{ subject = '...' }`. |

#### Returns

The first value of the property, type-converted for Lua:

| Property type | Returned type |
|---------------|---------------|
| `text`, `url`, `select` | string |
| `number` | number |
| `boolean` | boolean |
| `relation` | string (target Subject's label, falls back to target ID if lookup fails) |

Returns `nil` when:

- The property name is empty
- The Subject does not exist (page has no Main Subject, page does not exist, Subject ID invalid
  or not found)
- The Subject has no statement for the property
- The value is empty

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
multi-valued and you need all values.

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
| `subjectId` | string | Required. A Subject ID (15 chars, starts with `s`). |

#### Returns

A Subject table (see [Subject table format](#subject-table-format)) or `nil` if the ID is invalid
or no Subject exists with that ID.

#### Example

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
Returns an empty table `{}` if the page does not exist or has no Child Subjects.

#### Example

```lua
local children = nw.getChildSubjects()

for _, child in ipairs(children) do
    mw.log(child.label)
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
        ['Founded at']  = { type = 'number',  values = { [1] = 2005 } },
        ['Status']      = { type = 'select',  values = { [1] = 'Active' } },
        ['Websites']    = { type = 'url',     values = { [1] = 'https://acme.com', [2] = 'https://acme.org' } },
        ['Active']      = { type = 'boolean', values = { [1] = true } },
        ['Products']    = {
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

- `statements` is keyed by property name (a regular Lua associative table, **not** 1-indexed).
- `values` within each statement is a 1-indexed Lua table.
- `type` is the property type at the time the Statement was last written
  ([writer's schema](adr/011_Include_Writers_Schema.md)).
- For relation values, `label` falls back to the target Subject ID if the label cannot be looked
  up (e.g. broken reference).

## Performance notes

The following calls are marked as expensive (count against the parser's expensive function limit):

- `getValue` and `getAll` when called with `page=` or `subject=` options
- `getMainSubject(pageName)` when `pageName` is non-nil
- `getSubject(subjectId)` (always)
- `getChildSubjects(pageName)` when `pageName` is non-nil

Calls that read from the current page are not expensive.

## Planned additions

The following are not yet implemented:

- `nw.query(cypher, params)` — Execute Cypher queries from Lua. Tracked in
  [#736](https://github.com/ProfessionalWiki/NeoWiki/issues/736).
- `nw.getSchema(name)` — Schema introspection for generic templates. Tracked in
  [#737](https://github.com/ProfessionalWiki/NeoWiki/issues/737).

## Related Documentation

- [Parser Functions](ParserFunctions.md) — Wikitext interface to the same data
- [Glossary](Glossary.md) — Definitions of Subject, Schema, Statement, etc.
- [SchemaFormat.md](SchemaFormat.md) — How Schemas and properties are defined
- [SubjectFormat.md](SubjectFormat.md) — How Subject data is stored
- [GraphModel.md](GraphModel.md) — Neo4j node and relationship structure
