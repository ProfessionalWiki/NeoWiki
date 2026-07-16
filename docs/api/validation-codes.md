---
title: Validation Codes
order: 4
---
# Validation Codes

Constraint violations returned by NeoWiki's backend validation API use stable `code` strings.
This document is the authoritative reference for NeoWiki's PHP backend validation.

Violations are returned by:

- `POST /neowiki/v0/subject/validate` — dry-run validation of a proposed create-shape body.
- `POST /neowiki/v0/subject/{subjectId}/validate` — dry-run validation of a proposed update-shape
  body against an existing Subject's Schema.
- `POST /neowiki/v0/subject` and `PUT /neowiki/v0/subject/{subjectId}` — the write endpoints include
  the resulting `violations` array in their `201`/`200` success body. By default
  (`$wgNeoWikiEnforceValidation = false`) the write always persists. When an admin enables
  enforcement (`$wgNeoWikiEnforceValidation = true`), a write that introduces *new* blocking
  violations is rejected with `422 Unprocessable Entity` and an `{ status, message, violations }`
  body; `violations` carries the full proposed list, not just the newly-introduced subset.
  Pre-existing violations (already present on the stored Subject under the current Schema) and
  non-blocking codes such as [`schema-not-found`](#schema-not-found) never block, so editing an
  already-invalid Subject stays possible. See [ADR 21](../adr/021-add-backend-validation.md).

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
  "valuePartIndex": 0
}
```

- `propertyName` is the property name as a string, or `null` for Subject-level violations.
- `code` is one of the stable strings documented below.
- `args` is always present; `[]` when there is nothing to interpolate.
- `valuePartIndex` is the zero-based index into the offending Value's parts. The key is **omitted
  from the JSON** when not applicable (Subject-level violations or property-level violations that
  do not point at a single part).

## Code reference

### `required`

Emitted by `SubjectValidator` (when a required property has no Statement on the Subject) and
by every built-in PropertyType plus `ColorType` (RedHerb) (when a Statement is present but its
Value is content-empty).
`args`: `[]`.
`valuePartIndex`: never set.
`propertyName`: attached by `SubjectValidator` (either directly when the schema-iteration loop
emits, or via `withPropertyName()` from the per-type loop).

Fires in two cases for any property declared `required: true`:

1. **No Statement at all** — the Subject body did not include the property. `SubjectValidator`
   iterates the Schema's properties after the per-statement loop and emits `required` for any
   required property without a corresponding Statement. This also covers the case where the
   client sent the property with an empty Value (e.g. `value: []`), because
   `StatementListBuilder` drops empty-Value Statements before the validator runs, leaving the
   schema-iteration loop to surface it.
2. **Statement present but Value is content-empty** — emitted by the PropertyType validator.
   What counts as "content-empty" depends on the type:
   - `TextType` — no non-whitespace string in the `StringValue` parts.
   - `UrlType`, `SelectType`, `ColorType` — `StringValue` has zero parts after deserialization.
   - `NumberType` — the value is not a `NumberValue`.
   - `RelationType` — the value is not a `RelationValue`, or has zero relations.
   - `DateTimeType` — the value is not a `StringValue`, has zero parts, or its first part is
     empty/whitespace.

### `label-required`

Emitted by `SubjectValidator` only.
`args`: `[]`.
`valuePartIndex`: never set.
`propertyName`: always `null` (Subject-level).

The Subject's label is empty or whitespace-only.

### `type-mismatch`

Emitted by `SubjectValidator` only.
`args`: `[writerType, currentType]` — the type recorded on the Statement when it was written
and the type the Schema currently declares for the property.
`valuePartIndex`: never set.
`propertyName`: the affected property.

The Statement's writer's-schema type (the `propertyType` recorded when the Statement was
written, per ADR 11) no longer matches the Schema's current type for that property. For example,
a property was originally declared as `url` and a Statement was written carrying a `StringValue`;
the Schema was later edited to make that property a `number`. The Statement is now invalid under
the current Schema (ADR 12 names this case explicitly).

`SubjectValidator` skips per-type validation when this fires — the per-type's `instanceof`
PropertyDefinition guard would no-op against the wrong-typed definition anyway, and the more
specific `type-mismatch` code is what a consumer needs to react.

If a property is `required` AND its Statement has a type mismatch, only `type-mismatch` fires
(the Statement is present, just under the wrong type). `required` would be redundant noise.

### `invalid-url`

Emitted by `UrlType`.
`args`: `[]`.
`valuePartIndex`: index of the offending URL within the multi-part `StringValue`.
`propertyName`: attached by `SubjectValidator`.

A non-empty trimmed string in the `StringValue` does not match the allowed URL pattern. The
pattern accepts `http://` and `https://` schemes (or no scheme), domain-like hosts, IPv4
literals, `localhost`, port, path, query, and fragment. It rejects other schemes (`ftp://`,
`file://`), spaces, and disallowed characters.

### `unique`

Emitted by `TextType` and `UrlType`.
`args`: `[]`.
`valuePartIndex`: never set.
`propertyName`: attached by `SubjectValidator`.

The Property's `uniqueItems` constraint is enabled and the `StringValue` contains duplicate parts.

### `min-value`

Emitted by `NumberType` and `DateTimeType`.
`args`: `[minimum]`. For `NumberType` this is a `number`; for `DateTimeType` this is the ISO 8601
string declared on the Property.
`valuePartIndex`: never set.

The value is strictly below the Property's inclusive minimum.

### `max-value`

Emitted by `NumberType` and `DateTimeType`.
`args`: `[maximum]`. Same shape as `min-value`.
`valuePartIndex`: never set.

The value is strictly above the Property's inclusive maximum.

### `invalid-option`

Emitted by `SelectType` and RedHerb's `ColorType` (when `allowedColors` is non-empty).
`args`: `[offendingPart]`.
`valuePartIndex`: index of the offending part within the `StringValue`.

A part is not in the Property's allow-list (`options` for SelectType, `allowedColors` for
ColorType).

### `single-value-only`

Emitted by `SelectType`.
`args`: `[]`.
`valuePartIndex`: never set (Subject-property-level, not part-level).

The Property's `multiple` flag is false and more than one part was supplied.

### `invalid-datetime`

Emitted by `DateTimeType`.
`args`: `[]`.
`valuePartIndex`: never set (DateTime is single-valued).

The non-empty first part of the `StringValue` is not a strict ISO 8601 / xsd:dateTime string
with an explicit timezone offset or `Z`. Includes calendar-overflow cases like
`2025-02-30T00:00:00Z`, partial dates (`2025`, `2025-06`, `2025-06-15`), and missing offsets.

### `invalid-color`

Emitted by RedHerb's `ColorType`.
`args`: `[offendingPart]`.
`valuePartIndex`: index of the offending part within the `StringValue`.

A part does not match the 6-digit hex-color pattern (`/^#[0-9a-fA-F]{6}$/`).

### `schema-not-found`

Emitted by `ValidateSubjectUpdateApi` and by the Subject write endpoints
(`POST /subject`, `PUT /subject/{id}`) via `ProposedSubjectValidator`.
`args`: `[schemaName]`.
`valuePartIndex`: never set.
`propertyName`: always `null` (Subject-level).

The Subject's Schema cannot be loaded — usually because the Schema page was deleted or renamed
since the Subject was created, or because a Subject is created/imported referencing a Schema that
does not (yet) exist. On the write endpoints it is non-blocking: the write proceeds and the
(unvalidatable) Subject stays editable per ADR 21, but the response reports `schema-not-found`
rather than an empty (and misleading "valid") violation list. The caller can resolve it by
creating or renaming the Schema page.

Note the create dry-run endpoint (`POST /subject/validate`) instead returns `404`
(`SchemaNotFoundException`) for a missing Schema, because there it is the addressed resource;
the update dry-run (`POST /subject/{id}/validate`) and both write endpoints surface the violation.
Reconciling that asymmetry is left to the enforcement tier (ADR 21).

## Known limitations (Foundation round)

The PHP `SubjectValidator` performs two Subject-level checks:

- **`required`** — PHP iterates the Schema's properties to catch absent-required cases.
- **`type-mismatch`** — PHP compares the Statement's writer's-schema type against the Schema's
  current type and surfaces drift per ADR 11 / ADR 12.

### Deliberate behavior, not a gap

- **Out-of-schema Statements are silently skipped.** A Statement whose property is not declared
  on the current Schema is ignored — no violation. This is schema-drift tolerance: a property
  may have been removed from the Schema while Subjects still carry old Statements. ADR 8 says
  one Schema per Subject, not that every Statement on that Subject must reference an extant
  Property. If surfacing these as violations becomes useful (e.g. for migration UIs), it can be
  added as a separate code without changing the current behavior.

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
5. Document your new code in this file (or your extension's documentation if you want it to be
   discoverable independently).
