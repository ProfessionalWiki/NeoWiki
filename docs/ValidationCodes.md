# Validation Codes

Constraint violations returned by NeoWiki's backend validation API use stable `code` strings.
This document is the authoritative reference, shared between the PHP backend implementation and
(in a future round) the TS frontend implementation.

The API endpoints are:

- `POST /neowiki/v0/subject/validate` — validates a proposed create-shape body.
- `POST /neowiki/v0/subject/{subjectId}/validate` — validates a proposed update-shape body
  against an existing Subject's Schema.

Both endpoints return `200 OK` with a `{violations: [...]}` body whenever the request is
well-formed. Violations present in the body do not change the HTTP status. `400` is reserved for
malformed input; `404` for a missing Schema or Subject.

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

The Subject's label is empty or whitespace-only. Reachable through the validate endpoints
because they construct the Subject via `SubjectLabel::createForValidation()` which permits empty
labels; the regular `SubjectLabel::__construct` still rejects empty labels for write paths.

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

Emitted by `ValidateSubjectUpdateApi`.
`args`: `[schemaName]`.
`valuePartIndex`: never set.
`propertyName`: always `null` (Subject-level).

The existing Subject's Schema cannot be loaded — usually because the Schema page was deleted or
renamed since the Subject was created. Surfaced as a violation rather than a 404 because the
Subject itself does exist; the caller can fix this by re-creating or renaming the Schema page.

## Known limitations (Foundation round)

The PHP `SubjectValidator` is intentionally more rigorous than the current TS `SubjectValidator`
on the `required` check — PHP iterates the Schema's properties to catch absent-required cases.
The TS top-level validator does not yet do this; alignment will happen in the tier-two TS rework
and is the intended direction (PHP first, TS catches up).

Remaining gaps:

- **TextType min-length / max-length.** TS `TextType` validates min/max-length per part with
  `min-length` / `max-length` codes. PHP's `TextProperty` does not yet expose `minLength` /
  `maxLength` fields, so PHP `TextType::validate` does not emit these codes. Adding the fields
  to PHP `TextProperty` and the corresponding codes here is a future enhancement.
- **RelationType invalid-subject-id.** TS emits `invalid-subject-id` per relation whose target
  fails ID validation. PHP rejects invalid `SubjectId` strings at constructor time, so this code
  is unreachable on the PHP side — a `RelationValue` cannot hold an invalid target. The TS code
  exists for client-side parity but PHP currently does not emit it.

## Synchronization contract

PHP and TS implementations share the codes above. When changing or adding a code:

1. Add the case to the affected PropertyType's test file on **both** sides (TS spec.ts + PHP
   ValidateTest.php).
2. Implement on both sides.
3. Update this document.

Reviewers should reject one-sided code changes. If a TS implementation grows a new code, this
document and the PHP implementation should grow with it in the same PR (or an immediate
follow-up). Drift between the two implementations is the primary risk this discipline guards
against.

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
