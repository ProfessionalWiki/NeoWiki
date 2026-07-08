# Validation Severity Levels

Date: 2026-07-07

Status: Draft

## Context

With backend-driven validation complete ([ADR 21](021-add-backend-validation.md) added backend validation and
[ADR 25](025-backend-driven-frontend-validation.md) made it the single validator), where validation happens is
settled. What is not settled is how strict it should be. A Schema needs to express two kinds of Constraint at once:

1. Soft constraints: data that violates them is persisted and flagged rather than rejected. Examples: a value outside
   a preferred range, or messy imported data (the ECHOLOT cultural-heritage scenario).
2. Hard constraints: edits that violate them are rejected. Example: an identifier that must always be present.

The current mechanism cannot serve both. Enforcement is a per-wiki boolean (`$wgNeoWikiEnforceValidation`): when it
is on, a write is rejected with a 422 only if it introduces violations that were not present on the prior revision,
so pre-existing invalidity never locks a Subject. Every violation blocks except `schema-not-found`, which is
hardcoded as non-blocking. Validation is thus already two-state in behavior, but which state a violation falls into
is decided by code and the wiki-level switch alone; Schemas play no part in it. We want that decision in the hands of
schema authors, per Constraint, and ADR 21 anticipated exactly this kind of "Schema-scope-or-below severity-level
work".

ADR 21 also carries forward a requirement first recorded in [ADR 12](012-backend-validation.md): invalid Subjects are
a normal, supported state. Schema changes can invalidate persisted Subjects at any time, so the system keeps invalid
values and shows that they are invalid instead of rejecting or dropping them.

For the discussion that first shaped this proposal, see [PR #631](https://github.com/ProfessionalWiki/NeoWiki/pull/631).

## Decision

We add two severity levels for validation results: `error` and `warning`. Schema authors define severity per
Constraint in the Property Definition: a Constraint marked `error` is hard, one marked `warning` is soft.

Blocking semantics:

* `$wgNeoWikiEnforceValidation` remains the master switch. Severity never blocks a write on its own.
* With enforcement on, a write is rejected only when it introduces new violations of severity `error`. Warnings never
  block. The newly-introduced-violations check from ADR 21 is unchanged, and severity is not part of violation
  identity in that check: changing a Constraint's severity does not make an existing violation count as new.
* With enforcement off, no violation blocks, as today.
* Validate and write responses always include severity, so UIs and API consumers can distinguish the tiers regardless
  of the enforcement setting.

The default severity is `warning`. The architecture treats invalid Subjects as a normal state (the ADR 21 requirement
above), so surfacing a violation without blocking is the consistent default, and blocking is the exception a schema
author opts into. Wikidata's constraint model is precedent from the same domain: constraints are advisory unless
individually marked as mandatory.

### Schema JSON

A Constraint accepts either the bare scalar shorthand, which keeps the default `warning` severity, or an object form
that carries the severity:

```json
{
	"type": "number",
	"required": { "severity": "error" },
	"minimum": 0,
	"maximum": { "value": 100, "severity": "error" }
}
```

For boolean Constraints (`required`, `uniqueItems`) the object form implies `true`, so it has no `value` key. For
`options` the `value` key carries the options array. Canonical serialization emits the shorthand when the severity is
the default, so existing Schemas round-trip unchanged. Custom Property Types follow the same pattern for their own
Constraint fields.

### Authorable and fixed severities

Severity is configurable exactly where an authorable Constraint field backs the violation. These codes default to
`warning`:

| Violation code | Backing Constraint |
|---|---|
| `required` | `required` |
| `min-value` / `max-value` | `minimum` / `maximum` |
| `min-length` / `max-length` | `minLength` / `maxLength` |
| `unique` | `uniqueItems` |
| `invalid-option` | `options` |

All other codes are system conditions with a fixed severity, generalizing today's hardcoded `schema-not-found`
carve-out:

| Violation code | Condition | Severity | Why fixed |
|---|---|---|---|
| `label-required` | the Subject has no label | `error` | a label is Subject identity, not a property Constraint; a labelless Subject cannot be displayed or found |
| `type-mismatch` | the Statement's Property Type no longer matches the Schema | `error` | shape condition; shape-violating data breaks views and the graph projection |
| `invalid-url` / `invalid-date` / `invalid-datetime` | the value cannot be interpreted as its type | `error` | type integrity, comparable to a parse error in a linter, which is fatal regardless of rule configuration |
| `single-value-only` | multiple values on a single-value property | `error` | `multiple` declares the value's shape, like `type` itself, so it is a shape condition rather than a Constraint |
| `schema-not-found` | the Subject's Schema page is missing | `warning` | severity configuration lives in the Schema and there is none to read from; a warning keeps the Subject editable |

The fixed `error` for uninterpretable values (`invalid-url`, `invalid-date`, `invalid-datetime`) is the most
debatable of these classifications: messy cultural-heritage imports contain exactly such values. It stays workable
because enforcement is off by default and an admin can lift it for the duration of an import.

### Wire format and domain model

Every serialized violation gains an always-present `"severity": "error" | "warning"` field, in the dry-run validate
endpoints' 200 body and in the enforcement 422 body alike. In the domain model, every violation carries its severity,
stamped at validation time from the configuration of the Constraint it violates.

## Consequences

Pros:

* Schema authors control which constraints are strict and which are advisory, per Constraint. Messy imports can be
  persisted with warnings while essential fields still block.
* API responses distinguish warnings from errors, and the frontend can style them accordingly (Codex `warning` vs
  `error` status).
* The wire format change is additive, and existing Schema JSON stays valid via the scalar shorthand.
* The existing non-blocking carve-out is generalized instead of growing a parallel mechanism.
* The two tiers map losslessly onto SHACL severities, so a future projection of Constraints to shapes (see
  [ShapeLanguages](../planning/ShapeLanguages.md)) can carry severity through instead of inventing tiers at export
  time.

Cons:

* Enforcement only bites once schema authors mark Constraints as `error`: flipping `$wgNeoWikiEnforceValidation` on
  has no blocking effect while Schemas are unannotated.
* The Schema editor UI must expose a severity control per Constraint.
* The PHP and TypeScript JSON parsers must handle both the scalar and the object Constraint form.
* Severity is one more concept schema authors need to understand.

## Alternatives Considered

### Error as the default severity

An `error` default would preserve the current behavior where every violation blocks under enforcement. We rejected it
because it contradicts the invalid-Subjects requirement carried into ADR 21 from ADR 12.

### Removing the wiki-level enforcement switch

With per-Constraint severity, the `error` marking could itself be the enforcement opt-in, making
`$wgNeoWikiEnforceValidation` redundant: errors always block and warnings never do. We keep the switch for two
reasons. Schemas are editable wiki pages, so without a wiki-level switch anyone with schema-edit rights could start
blocking other users' writes; whether writes can be rejected at all should stay an operator decision. And the switch
is an operational override: during a large import, an admin can lift blocking wiki-wide without demoting severities
in every Schema and restoring them afterwards. Linters and compilers make the same split: severity classification
lives in the shared configuration, while failing the build on errors is a pipeline decision.

### Coarser severity attachment

Severity could attach per Property Definition, per Schema, or stay fixed per violation code. Each is cheaper than
per-Constraint severity, but violations correspond to Constraints, and the coarser scopes cannot express the common
case of one property whose presence is essential while its range is advisory. Fixed per-code severity gives schema
authors no control at all.

### Sibling severity fields in the Schema JSON

A flat `"maximumSeverity": "error"` next to `"maximum": 100` is the smallest change to the current JSON shape, but it
pairs keywords by naming convention. JSON Schema draft-04 tried this with the boolean `exclusiveMinimum` modifier and
draft-06 abandoned it. The systems that have per-constraint severity (ESLint, Stylelint, SHACL, Wikidata constraints)
all co-locate severity inside the constraint's own configuration.

### Grouping Constraints under a `constraints` object

Restructuring the property JSON to separate Constraints from Display Attributes matches the conceptual split in the
[glossary](../concepts/glossary.md) and would be the cleanest home for severity. We rejected it for now because it
restructures every serializer, fixture, and editor surface for the same decision, and the core `required` field has
no natural place in it. The object form chosen here does not preclude this restructuring later.

### Additional tiers and SHACL vocabulary

SHACL has an `sh:Info` tier and calls its most severe level `sh:Violation`. We add no `info` tier because nothing in
the system needs one and the enum can grow later. We do not use `violation` as a severity name because in NeoWiki
"Violation" already names the finding itself, whatever its severity. If NeoWiki later emits SHACL-style validation
reports on the RDF side, the mapping is `error` to `sh:Violation` and `warning` to `sh:Warning`.
