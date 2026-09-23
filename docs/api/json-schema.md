---
title: JSON Schema
order: 8
---
# JSON Schema

`GET /neowiki/v0/schema/{schemaName}/json-schema` serves a Schema as a self-contained
[JSON Schema](https://json-schema.org/) draft 2020-12 document (`application/schema+json`). It answers
`404` when the Schema does not exist or you may not [read](rest-api.md#permissions) it.

The document describes one Subject of that Schema in the JSON the Subject endpoints accept and return
(see [Subject format](subject-format.md)): `schema`, `label`, and `statements` keyed by property name.
Top-level keys beyond those are allowed, so the `id` a read adds and the `comment` a write takes do not
make a Subject invalid. Every Constraint is described whatever its
[severity](schema-format.md#constraint-severity), so failing the document does not mean the wiki would
reject the Subject.

A Subject that validates against the document draws no [Violations](validation-codes.md) for its
values, except for what the document cannot express:

- whether a relation target exists and which Schema it follows; a relation's `target` and `id` are
  described as any string
- `minimum` and `maximum` on `date` and `dateTime` properties
- a calendar-impossible date such as `2025-02-30`, unless your validator asserts `format`
- for a Property Type an extension adds, whatever its own description of its values leaves out
- whitespace: the wiki trims each part of a value and drops empty parts before checking it
- letter case in a `monolingualText` language tag, which the wiki ignores when it checks `uniqueItems`

[`POST /neowiki/v0/subject/validate`](validation-codes.md) checks a proposed Subject against the wiki
itself.

Where the document is stricter than the write endpoints:

- a Statement for a property the Schema does not declare is rejected; the wiki stores it
- a `select` value must be an option id; the write endpoints also take an option label or an
  `{ "id", "label" }` object
- a Statement given as `null` or without `propertyType` is rejected; the wiki drops it
- a value with more parts than its property allows is rejected; the wiki checks `multiple` only for
  [`select`, `relation` and `monolingualText`](validation-codes.md#single-value-only)
