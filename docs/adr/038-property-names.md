# Property Names

Date: 2026-10-24

Status: Accepted

## Context

A Property name is both the label people see and the identifier everything else uses to refer to the Property
Definition ([ADR 17](017-names-as-identifiers.md)). Statements store their values under it, and Layouts, Mappings,
`{{#neowiki_value}}`, Lua and API clients refer to the Property by it. Projections turn it into Neo4j property keys and
relationship types, and into RDF predicate IRIs.

Take a Person Schema with a Property Definition "Birth date". What happens when someone adds "Birth_date" next to it,
writes `{{#neowiki_value:birth date}}`, or builds a query language that puts a separator between Schema and Property
names? That depends on when two names are the same, which characters a name may hold, and which names NeoWiki uses
itself.

Schema names are page titles, so MediaWiki's title rules settle these questions for them; Property names need rules of
their own.

## Decision

1. Two Property names are the same name only when they are identical. "Birth date", "birth date" and "Birth_date" are
   three names, and every reference spells the name as its Schema does.
2. No two Property names in one Schema may be equal once letters are lowercased, underscores are read as spaces,
   compatibility variants such as full-width letters are read as their plain form, and invisible characters such as
   joiners and variation selectors are ignored. The Person Schema cannot hold both "Birth date" and "birth date".
3. Names that differ only invisibly are written the same: a name is trimmed, in Unicode NFC, has single spaces, and
   holds no invisible characters, such as zero-width spaces, bidirectional controls and fillers. Zero-width joiners,
   non-joiners and variation selectors stay, since scripts and emoji need them.
4. A name holds no control characters, noncharacters or U+FFFD, and none of `# < > [ ] | { }`, the characters MediaWiki
   forbids in page titles.
5. A name is not empty, is not an integer, such as `2024` or `-1`, is not `.` or `..`, and is at most 255 bytes long in
   UTF-8.
6. `id`, `name`, `wiki_id` and `__proto__` are reserved; "Name" and "ID" stay available.
7. NeoWiki's APIs and editors accept a Statement only under a name that follows rules 3 to 6.
8. These rules are the same on every wiki. Configuration does not change them.

A Schema whose Property names break rules 2 to 6 cannot be saved. The
[Schema format](../api/schema-format.md#property-names) lists the exact characters and steps.

## Consequences

- `{{#neowiki_value:birth date}}` shows nothing when the Property is "Birth date". Lua, Layouts and Mappings behave
  the same way.
- Names keep the case and style people type: "datePublished", "first_name" and "inception" stay as written, and so do
  their Neo4j keys and RDF IRIs.
- The Schema editor cleans up stray spaces and invisible characters in names.
- Wikitext can address a Property without escaping, since no name holds `|`, `{` or `}`.
- Each Property of a Schema has its own RDF predicate.
- Other syntaxes, such as a query language, can use `# < > [ ] | { }` as separators or quotes, since neither Property
  nor Schema names hold them.
- Names taken from spreadsheets sometimes need changing, such as "Phone #", "Height [cm]" or "2024", and so do pairs
  that differ only in case, such as "CO" and "Co".
- Renaming a Property still breaks references to it ([ADR 17](017-names-as-identifiers.md)), even when only case or
  underscores change.
- Because rule 2 keeps names unique ignoring case and underscores, a later version can match references that way
  without ambiguity.

## Alternatives Considered

### Page-title rules

Treat underscores as spaces and capitalize the first letter, as MediaWiki does for titles and NeoWiki for Schema names:
"has_author" would be stored as "Has author".

Not chosen because Properties are not pages, and this rewrites names people chose on purpose: more than half of the
names user testers typed start lowercase, such as "inception" and "datePublished". Their Neo4j keys and RDF IRIs would
change with them.

### Matching normalized names

Keep "birth date" as typed, but let it match "Birth date" everywhere.

Not chosen because Neo4j, RDF, JSON and Lua match exactly, so everything that leaves NeoWiki would still need one
spelling per Property, and every comparison in PHP, TypeScript and other consumers would need the same normalization.
References written in other spellings would also make normalized matching impossible to drop later.

### Exact names alone

Allow "Birth date" and "birth date" in one Schema by dropping rule 2.

Not chosen because two Properties that look alike invite values split between them, and RDF would merge "Has author" and
"Has_author" into one predicate while Neo4j keeps them apart. Allowing such pairs is also hard to undo: once wikis hold
them, forbidding them would mean renaming Properties.

### Cleaning names on the server

When a name with a stray space or an invisible character arrives through the API or a page edit, fix it instead of
refusing the save.

Not chosen because the same names also reach the wiki in Mappings, Layouts and Subject data, often from the same file or
script. Fixing only the Schema's copy would leave those with the old spelling, and they would stop matching without any
error. And once clients rely on the wiki fixing names, it could not go back to refusing them. The Schema editor is
different: a name typed there is not yet used anywhere else.

### Characters chosen per wiki

Let each wiki restrict names further, with a configuration setting or a save hook.

Not chosen because Schemas move between wikis through Sources and page imports, and rule 4 already gives other syntaxes
their separators.

### Fewer forbidden characters

Forbid only `< > | { }`, which wikitext and HTML need.

Not chosen because `#`, `[` and `]` are just as rare in real names, and forbidding them too gives Property names the
same character rule as Schema names.

### Dots as separators

Forbid `.` too, so a query language can write `Schema.property`, as Semantic MediaWiki's property chains do.

Not chosen because catalogue names often contain dots, such as "Inv.-Nr." or "No.", and the characters already forbidden
give such syntaxes separators.

### Moving NeoWiki's graph keys

Let Properties be named `id`, `name` and `wiki_id` by renaming the keys NeoWiki writes on Neo4j nodes, for example to
`_name`.

Not chosen because every Cypher query that reads a Subject's label as `s.name`, as the graph model documents, would
break, and only the lowercase spellings are reserved: "Name" and "ID" are allowed.
