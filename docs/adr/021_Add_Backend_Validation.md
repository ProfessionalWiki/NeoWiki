# Add Backend Validation

Date: 2026-05-06

Status: Accepted. Supersedes [ADR 12](012_Backend_Validation.md).

## Context

In [ADR 12: Backend Validation](../adr/012_Backend_Validation.md) we decided to validate in the frontend only.
The scope of the project changed, both due to ECHOLOT and AI progress, so we have to revisit this decision.

ECHOLOT stakeholders wish to be able to get a list of constraint violations from the API before attempting a save.
They also want to be able to have the API reject edits that would make Subjects invalid.

We want good support for agentic AI workflows. AI agents interact with the API and do not benefit from frontend-only
validation. It's important for them to get feedback and to enable optional guardrails that wiki admins can put in place.

We assume we will have these:
* REST APIs to read and write Subjects and Schemas identified by id. Both use NeoWiki-specific JSON formats
* REST API to get a Schema in JSON-schema format
* TypeScript library with a validation service that takes a Subject and a Schema that can be used outside NeoWiki/MediaWiki
* UIs that support display and editing of "invalid" Subjects. These are Subjects that do not meet all constraints in their linked Schema.
* Ability to write "invalid" Subjects to the backend. (Needed by the UI, and plausibly by various CH use cases)

For the broader discussion that produced this decision, see the
[Mattermost thread](https://chat.professional.wiki/pro-wiki/pl/k5koija5npdhijzjedhbxbiutr).

## Requirement: invalid Subjects support

We have to support invalid Subjects. Even if we were to always reject edits that create invalid data, Schemas can
change after Subjects have been persisted, making the already-persisted Subjects invalid. Thus, our UIs need to be
able to show invalid Subjects. Additionally, even if we end up disallowing the creation of new constraint violations
via the UIs and APIs, editing of Subjects that already have a constraint violation for one or more Statements should
be possible without resolving those pre-existing violations.

Some scenarios to consider:

* A new constraint is added to a Schema, making some existing Subjects invalid.
* A Property Definition is removed from a Schema, perhaps by accident, perhaps later reverted. What happens to invalid Subjects?
* Someone wants to import existing data which contains some invalid Subjects, perhaps to be cleaned up later.

Handling of invalid Subjects:

* We need to be able to display the invalid Subjects in the UI.
* Edits to valid parts of invalid Subjects should still be allowed.
* Users should be able to edit invalid parts of invalid Subjects to make them valid.
* Invalid parts should not disappear on save.
* The desired behavior for graph-based queries is unclear. Might depend on the usecase and perhaps call for storing but flagging invalid Subjects.

## Decision

We will add validation capability to the backend.

Validation remains optional, though this can be changed on a per-wiki level via configuration, and later perhaps also
on smaller scope(s) like Schema-level.

Even in cases where validation is optional, the edit APIs have the ability to return constraint violations, and a dedicated
validation API allows seeing said errors before attempting an actual edit.

## Consequences

Pros:
* API consumers gain access to validation results in API responses
* Wiki admins can enforce constraints at the API boundary, not just in the UI
* We gain a foundation for potential future Schema-scope-or-below severity-level work

Cons:
* We have to implement and maintain backend validation, keeping it in sync with frontend behavior
* Backend validation must distinguish newly-introduced constraint violations from pre-existing ones,
  so that enforcement applies only to the former
* We have to return constraint violations from the Subject edit endpoints, possibly changing their response shapes,
  and figure out if we always do this or optionally
* We have to add a new dedicated validation endpoint to allow for external validation without persistence attempts
* We have to add a per-wiki setting that controls whether new constraint violations block writes

## Alternatives Considered

### Keep validation frontend-only

Pros:
* No cost of implementation, we can build other things instead
* No cost of carry. Simpler system

Cons:
* API users have to validate their data before sending it to the API if they want to ensure correctness
* Wiki admins cannot enforce validation on edit

### Backend-only validation (frontend calls API)

Have validation live solely in PHP, exposed via an API endpoint that the frontend calls for every check.

Pros:
* Single canonical validator. No sync burden between PHP and TS.
* API consumers get the same validation as the UI by construction.

Cons:
* Network round-trip on every validation check (typing in a field, pre-submit checks). Loses instant feedback as a UX property.
* Frontend becomes dependent on the API for what is currently local logic; offline / library-reuse cases become harder.
