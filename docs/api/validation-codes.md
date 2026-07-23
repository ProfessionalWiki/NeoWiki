---
title: Validation Codes
order: 4
---
# Validation Codes

Constraint violations returned by NeoWiki's validation API use stable `code` strings. This document is
the reference for those codes.

Violations are returned by:

- `POST /neowiki/v0/subject/validate` — dry-run validation of a proposed create-shape body.
- `POST /neowiki/v0/subject/{subjectId}/validate` — dry-run validation of a proposed update-shape
  body against an existing Subject's Schema.
- `POST /neowiki/v0/subject` and `PUT /neowiki/v0/subject/{subjectId}` — the write endpoints include
  the resulting `violations` array in their `201`/`200` success body. Whether a write with violations
  is rejected is covered under [Blocking and enforcement](#blocking-and-enforcement).

The `/validate` endpoints return `200 OK` with a `{violations: [...]}` body whenever the request is
well-formed; violations in the body do not change the HTTP status. `400` is reserved for malformed
input, `404` for a missing Subject (update dry-run). A missing Schema is reported differently per
endpoint — see [`schema-not-found`](#schema-not-found). `POST /subject` returns `409` when the page
already has the requested Subject (the create did not run, so the body carries no `violations`).

Each violation in the response has this shape:

```json
{
  "propertyName": "Website",
  "code": "invalid-url",
  "args": [],
  "severity": "error",
  "valuePartIndex": 0
}
```

- `propertyName` is the property name as a string, or `null` for Subject-level violations.
- `code` is one of the stable strings documented below.
- `args` is always present; `[]` when there is nothing to interpolate.
- `severity` is always present and is either `error` or `warning`. See
  [Severity, blocking, and enforcement](#severity-blocking-and-enforcement).
- `valuePartIndex` is the zero-based index of the offending part of a multi-part value. Only the
  codes that document it set it; the key is omitted from the JSON otherwise.

## Severity, blocking, and enforcement

Severity decides whether a write can be rejected: warnings never block, errors can. Each code in the
reference below documents its own severity.

Where a violation is backed by a Constraint the schema author writes — `required`, `minimum`,
`maximum`, `minLength`, `maxLength`, `uniqueItems`, `options` — that author sets its severity in the
Schema, per Constraint, and the default is `warning`. See
[Constraint severity](schema-format.md#constraint-severity) for the JSON. Because the default is
`warning`, an unannotated Schema blocks nothing at all: invalid Subjects are a normal, supported
state, and blocking is what an author opts into. Every other code reports a system condition rather
than a user-correctable Constraint, so its severity is fixed.

Blocking matters only when an admin enables enforcement (`$wgNeoWikiEnforceValidation`; off by
default, so every write persists). Under enforcement, a write that introduces *new* `error`
violations is rejected with `422 Unprocessable Entity` and an `{ status, message, violations }` body,
where `violations` carries the full proposed list, not just the newly-introduced ones. Violations
already present on the stored Subject never block, so an already-invalid Subject stays editable, and
raising a Constraint's severity does not make an existing violation count as newly introduced. See
[ADR 21](../adr/021-add-backend-validation.md) and
[ADR 26](../adr/026-validation-severity-levels.md).

## Code reference

### `required`

A property declared `required: true` has no usable value. Fires when the Subject body has no
Statement for the property, and when a Statement is present but its value is empty for its type:
only whitespace (`text`, `date`, `dateTime`), no parts (`url`, `select`), no targets (`relation`),
or no value at all (`number`, `boolean`).

`args`: `[]`. `severity`: set by the `required` Constraint (default `warning`).

### `label-required`

The Subject's label is empty or whitespace-only. Subject-level: `propertyName` is `null`.

`args`: `[]`. `severity`: `error` (fixed).

### `type-mismatch`

The type recorded on the Statement when it was written (ADR 11) no longer matches the type the
Schema currently declares for the property — for example, a Statement written while the property was
a `url`, after the Schema changed the property to `number`. When this fires, it is the only
violation reported for that property: per-type checks and `required` are suppressed.

`args`: `[writerType, currentType]`. `severity`: `error` (fixed).

### `invalid-url`

On `url` properties. A non-empty value does not match the allowed URL pattern. The pattern accepts
`http://` and `https://` schemes (or no scheme), domain-like hosts, IPv4 literals, `localhost`,
port, path, query, and fragment. It rejects other schemes (`ftp://`, `file://`), spaces, and
disallowed characters.

`args`: `[]`. `valuePartIndex`: the offending part. `severity`: `error` (fixed).

### `unique`

On `text` and `url` properties with `uniqueItems` enabled: the value contains duplicate parts.

`args`: `[]`. `severity`: set by the `uniqueItems` Constraint (default `warning`).

### `min-length` / `max-length`

On `text` properties. A part's trimmed length is below `minLength` or above `maxLength`. Empty
parts are not length-checked.

`args`: `[minLength]` / `[maxLength]`. `valuePartIndex`: the offending part. `severity`: set by the
`minLength` / `maxLength` Constraint (default `warning`).

### `min-value` / `max-value`

On `number`, `date`, and `dateTime` properties. The value is below the property's inclusive
`minimum` or above its inclusive `maximum`.

`args`: `[minimum]` / `[maximum]` — a number for `number` properties, the declared ISO 8601 string
for `date` and `dateTime`. `severity`: set by the `minimum` / `maximum` Constraint (default
`warning`).

### `invalid-option`

On `select` properties. A part is not in the property's `options` allow-list.

`args`: `[offendingPart]`. `valuePartIndex`: the offending part. `severity`: set by the `options`
Constraint (default `warning`).

### `single-value-only`

On single-valued (`multiple: false`) `select` and `relation` properties: more than one part
(`select`) or relation target (`relation`) was supplied.

`args`: `[]`. `severity`: `error` (fixed).

### `invalid-datetime`

On `dateTime` properties. The value is not a strict ISO 8601 / `xsd:dateTime` string with an
explicit timezone offset or `Z`. Includes calendar-overflow cases like `2025-02-30T00:00:00Z`,
partial dates (`2025`, `2025-06`, `2025-06-15`), and missing offsets.

`args`: `[]`. `severity`: `error` (fixed).

### `invalid-date`

On `date` properties. The value is not a strict ISO 8601 calendar date (`YYYY-MM-DD`). Time or
timezone components and calendar overflows like `2025-02-30` are rejected.

`args`: `[]`. `severity`: `error` (fixed).

### `unregistered-type`

The property's type has no registered PropertyType — typically the extension providing the type is
disabled. The value cannot be interpreted, so it is preserved verbatim and no other checks run for
the property. A required property of an unregistered type reports this code instead of `required`,
so the Subject stays saveable.

`args`: `[propertyType]`. `severity`: `warning` (fixed).

### `schema-not-found`

The Subject's Schema cannot be loaded — usually deleted or renamed since the Subject
was created, or the Subject was created or imported referencing a Schema that does not (yet) exist.
The write proceeds and reports the violation; creating or renaming the Schema page resolves it.
Subject-level: `propertyName` is `null`.

Returned by the update dry-run and both write endpoints. The create dry-run
(`POST /subject/validate`) instead returns `404`, because there the Schema is the addressed
resource.

`args`: `[schemaName]`. `severity`: `warning` (fixed).

### `relation-target-schema-mismatch`

On `relation` properties. The relation targets a Subject that exists but whose own Schema is not the
property's declared `targetSchema`. A target that cannot be resolved is reported as
[`relation-target-not-found`](#relation-target-not-found) instead.

`args`: `[expectedSchema, actualSchema]`. `valuePartIndex`: the offending target. `severity`: `error`
(fixed).

### `relation-target-not-found`

On `relation` properties. The relation targets a Subject ID that does not resolve to any existing
Subject. Deliberately a warning: pointing at a not-yet-created Subject is wiki-native red-link
behavior, and an import may legitimately mint the target later.

`args`: `[targetId]`. `valuePartIndex`: the offending target. `severity`: `warning` (fixed).

## Out-of-schema Statements

A Statement whose property is not declared on the current Schema is ignored — no violation. This is
schema-drift tolerance: a property may have been removed from the Schema while Subjects still carry
old Statements.

## Adding a new validation code

If you're a plugin author (e.g. registering a custom PropertyType) and want to surface a new
violation code:

1. Return a `Violation` from your `PropertyType::validate()` method. Leave `propertyName` as
   `null` — `SubjectValidator` attaches it.
2. Use a short kebab-case code that describes the violated rule, not the implementation
   (`invalid-postcode`, not `regex-failed-1`).
3. Set `args` to the values you'd want interpolated into a user-facing message — the same
   convention as the codes above.
4. If the violation points at a specific part of a multi-part Value, set `valuePartIndex` to
   that part's zero-based index.
5. Set the severity. A `Violation` defaults to `warning`, which never blocks a write. If your code
   is backed by a Constraint the schema author writes, take the configured value with
   `$definition->severityOf( 'yourConstraintKey' )` so authors can make it blocking; if it reports a
   fixed system condition, pass `Severity::Error` or `Severity::Warning` explicitly. Severity applies
   to Constraints only — a severity written on one of your Display Attributes is discarded.
6. Document your new code in your extension's documentation.

RedHerb's `ColorType` (`tests/RedHerb/src/ColorType.php`) is a worked example, including its own
`invalid-color` code.
