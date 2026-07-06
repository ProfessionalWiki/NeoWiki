# Backend-driven Frontend Validation

Date: 2026-06-30

Status: Accepted. Amends [ADR 21](021-add-backend-validation.md).

## Context

[ADR 21](021-add-backend-validation.md) added backend validation while keeping the existing frontend validation,
and explicitly rejected the "Backend-only validation (frontend calls API)" alternative on the grounds that a network
round-trip on every check loses instant feedback.

Since then the server-driven flow shipped ([#886](https://github.com/ProfessionalWiki/NeoWiki/pull/886)): a debounced
dry-run endpoint (`POST .../subject/validate`) returns constraint violations as the user edits, and save-time
enforcement returns them as a 422.

It has also become clear that many edits will happen through alternative UIs, lowering the value of having an
additional TypeScript implementation of the validators, and raising the relative maintenance burden.

## Decision

Remove the client-side validation logic. The frontend no longer computes violations locally; it still
surfaces them inline, but the violations now come from the server — the dry-run while editing, and the 422 at save.

Concretely:

* Drop `validate()` from the Property Type classes and the `BasePropertyType` contract, and remove the
  `ValueValidationError` type.
* Drop the `validate` hook from the frontend extension API (`PropertyTypeRegistration` and `PropertyTypeAdapter`).
  Custom Property Types validate through their registered PHP validator, the same as core types.
* The value inputs surface server violations only.

This adopts the "Backend-only validation (frontend calls API)" approach that ADR 21 rejected.

## Consequences

Pros:

* A single canonical validator in PHP. No PHP/TypeScript sync burden.
* Core and extension Property Types validate the same way, through the server.
* Less frontend code to carry.

Cons:

* Validation feedback depends on the server round-trip, making the UIs less snappy.
* We no longer provide TS or JS validation code that can be used in other applications.
