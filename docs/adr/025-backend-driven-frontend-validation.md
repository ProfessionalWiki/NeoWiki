# Backend-driven Frontend Validation

Date: 2026-06-30

Status: Accepted. Amends [ADR 21](021-add-backend-validation.md).

## Context

[ADR 21](021-add-backend-validation.md) added backend validation while keeping the existing frontend validation,
and explicitly rejected the "Backend-only validation (frontend calls API)" alternative on the grounds that a network
round-trip on every check loses instant feedback.

Since then:

* The duplicated validators drifted. Each Property Type had a validator in both PHP and TypeScript, and the two
  diverged in places: the PHP validators treat whitespace-only values as missing when checking `required`, while
  the TypeScript ones accept them. The duplication also produced user-facing bugs, such as the client-side number
  validator turning unset bounds into blocking errors ([#756](https://github.com/ProfessionalWiki/NeoWiki/issues/756)).
* The server-driven flow shipped ([#886](https://github.com/ProfessionalWiki/NeoWiki/pull/886)): a debounced
  dry-run endpoint (`POST .../subject/validate`) returns constraint violations as the user edits, and save-time
  enforcement returns them as a 422.
* The frontend extension API (`PropertyTypeRegistration`) also carried a client `validate` hook, duplicating the
  server-side validator a custom type already registers (for example RedHerb's `ColorType`).

## Decision

Remove the duplicated client-side validation logic. The frontend no longer computes violations locally; it still
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

* A single canonical validator in PHP. No PHP/TypeScript sync burden, and no drift bugs.
* Core and extension Property Types validate the same way, through the server.
* Less frontend code to carry.

Cons:

* Validation feedback depends on the server round-trip. The debounced dry-run makes this near-instant in practice,
  but it is no longer strictly local, and the standalone-TypeScript-library reuse case noted in ADR 21 is not served
  by the frontend.
* An extension author who wants a custom Property Type validated must register a server-side PHP validator; there is
  no client-only validation hook.
