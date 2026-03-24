# Validation Architecture

Written 2026-01-15

## Current Status and Context

In [ADR 12: Backend Validation](../adr/012_Backend_Validation.md) we decided to validate in the frontend only.
With ECHOLOT, the scope of the project changed, and we have to revisit this decision.

All changes to Subjects and Schemas go through the REST API. This includes changes made
by the NeoWiki UIs. These NeoWiki UIs validate Subjects based on their associated Schemas.

Concrete example: when editing an organization, the NeoWiki UI provides a form with fields
based on the organization Schema. This Schema contains Property Definitions
(i.e. "Name", "Founded at") which contain types and can contain further constraints.
The UI form shows only fields for these Property Definitions.

The NeoWiki UI is written in TypeScript and Vue, with much of it decoupled from MediaWiki
so it can be reused elsewhere. In particular, the TypeScript library includes repositories
for interacting with the REST API, providing read and write access to Subjects and Schemas.
It also includes a data model for Subjects and Schemas, along with a validation service that
validates Subjects based on their Schema. This allows external applications to validate data
before submitting it to the REST API without writing their own validation logic. Finally,
the TypeScript library provides a UI component to display Subjects and Schemas.

Clients not using the TypeScript library can still access the Schemas via the REST API.
The Schemas are returned as JSON in a format optimized for our UIs and validation logic.
We decided against using JSON Schema for Schemas internally in [ADR 9: Move Away from JSON Schema](../adr/009_Move_Away_from_JSON_Schema.md)
because this creates friction and complexity, while we can still easily create a JSON Schema
from the internal representation for consumers that wish for such an API.

## Frontend-only Validation Motivations

It is appealing to avoid invalid Subjects in the persistence and UI layers as supporting
this adds complexity. However, because Schemas can change after Subjects have been persisted,
we cannot avoid having to support invalid Subjects. This neutralizes perhaps the most
appealing benefit of backend validation.

If editing and import happen via UIs in MediaWiki or UIs using our TypeScript library,
then validation in the backend does not seem to justify its own cost. The cost comes from
having two implementations of validation logic, one in TypeScript and one in PHP, which
need to be kept in sync. (Aside: we could have the validation just in PHP and have a validation
API endpoint, but this comes with usability downsides we wish to avoid.)

If there are substantial users of the REST APIs that do not use our TypeScript library,
then we may need to add backend validation. This seems plausible for ECHOLOT, both in the form
of external applications (for instance, for import) and for the end users of the software.

## Scenarios to Consider

* A new constraint is added to a Schema, making some existing Subjects invalid.
* A Property Definition is removed from a Schema, perhaps by accident, perhaps later reverted. What happens to invalid Subjects?
* Someone wants to import existing data which contains some invalid Subjects.

Handling of invalid Subjects:
* We need to be able to display the invalid Subjects in the UI.
* Edits to valid parts of invalid Subjects should still be allowed.
* Users should be able to edit invalid parts of invalid Subjects to make them valid.
* Invalid parts should not disappear on save.
* The desired behavior for graph-based queries is unclear. Might depend on the usecase and perhaps call for storing but flagging invalid Subjects.

**Conclusions:**

* We might need to add backend validation for ECHOLOT, depending on the use cases and degree to which
  external applications that do not use the TypeScript library are developed. Key question: what API-based
  validation do ECHOLOT end users (not project participants) need?
* Backend validation would still have to be optional, as per the above scenarios.

## Our Options

Currently, we assume we will have these:
* REST APIs to read and write Subjects and Schemas identified by id. Both use NeoWiki-specific JSON formats
* REST API to get a Schema in JSON-schema format
* TypeScript library with a validation service that takes a Subject and a Schema that can be used outside NeoWiki/MediaWiki
* UIs that support display and editing of "invalid" Subjects. These are Subjects that do not meet all constraints in their linked Schema.
* Ability to write "invalid" Subjects to the backend. (Needed by the UI, and plausibly by various CH use cases)

**Option 1: Keep validation frontend-only**

Pros:
* No cost of implementation, we can build other things instead
* No cost of carry. Simpler system
* Instant feedback when editing in the frontend

Cons:
* API users have to validate their data before sending it to the API if they want to ensure correctness

**Option 2: Add backend validation**

We implement a backend validation service similar to the existing TypeScript one. We do some things like
adding a dedicated validation endpoint and adding a strict mode to the subject writing API. Details TBD.

Pros:
* Constraints defined on a Schema are enforced for everyone, not only for users who edit via the NeoWiki
  frontend
* API users can edit Subjects without prior validation without risking creating "invalid" Subjects
* API users can potentially validate Subjects without editing via a new dedicated endpoint
* Schemas are increasingly likely to be managed through the API, whether from LLM clients, third-party tools,
  or import pipelines. Consumers do not need to implement their own validation.

Cons:
* Need to figure out those TBD details
* Cost of implementation and cost of carry
    * Validation system in PHP
    * Strict-mode or similar for Subject writing APIs with validation status responses
    * Potentially dedicated REST validation endpoint

**Option 3: Both frontend and backend validation**

The Schema is the **single source of truth** for validation rules. Both the TypeScript library and the PHP
backend have a generic constraint interpreter that reads the Schema and validates against it. This is the same
pattern as JSON Schema: one schema, multiple interpreters. The interpreters handle constraint types (required,
min/max, regex, etc.), not specific business rules. Adding a new constraint type means updating both
interpreters, but the constraint values themselves are defined once in the Schema.

A shared test suite runs the same inputs and expected outputs against both interpreters to keep them in sync.

New constraint types scale without per-rule maintenance. For example, a user-defined regex check is a
declarative rule in the Schema (`pattern: "^[A-Z]"`). Both TS and PHP execute it natively. Only the "regex"
constraint type needs to be added once to each interpreter.

The same declarative constraint metadata could also drive the Schema Editor UI, replacing per-Property-Type
editor components with a generic renderer.

Pros:
* Constraints defined on a Schema are enforced for everyone, not only users who edit via the NeoWiki frontend
* API consumers get validation without implementing their own
* API users can validate Subjects via a dedicated endpoint without persisting
* Instant feedback when editing in the frontend
* Single source of truth and generic interpreters keep the two implementations manageable
* Scales for new constraint types without growing per-rule maintenance

Cons:
* More complex to implement than either option alone
    * Validation system in PHP
    * Constraint interpreters in both TS and PHP, kept in sync via shared test suite
    * Strict-mode or similar for Subject writing APIs with validation status responses
    * Potentially dedicated REST validation endpoint

See also: [Validation Severity](Validation-Severity.md) for a related but separate discussion on
warning/error severity levels for validation results.
