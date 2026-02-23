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

Cons:
* API users have to validate their data before sending it to the API if they want to ensure correctness

**Option 2: Add backend validation**

We implement a backend validation service similar to the existing TypeScript one. We do some things like
adding a dedicated validation endpoint and adding a strict mode to the subject writing API. Details TBD.

For instant feedback, the Schema serves as the **single source of truth** for validation rules. Both the
TypeScript library and the PHP backend contain a generic constraint interpreter that reads the Schema and
executes validation — similar to how JSON Schema validation works, where the schema is defined once and
interpreters exist in multiple languages. The interpreters are generic: they handle constraint types
(required, min/max, regex, etc.), not specific business rules. Adding a new constraint type (e.g., regex)
means updating both interpreters, but the actual constraint values are defined once in the Schema.

This approach scales well for future custom validations. For instance, a user-defined regex check is a
declarative rule stored in the Schema (`pattern: "^[A-Z]"`), executable natively by both TS and PHP without
new interpreter logic — only the "regex" constraint type needs to be added once to each interpreter.

A shared test suite with the same inputs and expected outputs run against both interpreters keeps them in sync.

The same declarative constraint metadata could also drive the Schema Editor UI, replacing per-Property-Type
editor components with a generic renderer.

Pros:
* API users can edit Subjects without prior validation without risking creating "invalid" Subjects
* API users can potentially validate Subjects without editing via a new dedicated endpoint
* The Schema remains the single source of truth for rules. Interpreters are generic and stable.
* Scales for future custom validations (regex, enums, etc.) without growing maintenance burden per-rule
* Instant feedback preserved via the TypeScript interpreter; TS library stays usable standalone

Cons:
* Need to figure out those TBD details
* Cost of implementation and cost of carry
    * Validation system in PHP
    * Constraint interpreters in both TS and PHP, kept in sync via shared test suite
    * Strict-mode or similar for Subject writing APIs with validation status responses
    * Potentially dedicated REST validation endpoint

**Option 3: Warning/error severity levels**

This option can be combined with Option 2. Validation results are split into two severity levels:

* **Errors**: the Subject is rejected and not persisted. Represents hard constraints that must always be satisfied.
* **Warnings**: the Subject is flagged as invalid but still persisted. Represents soft constraints where the data
  does not conform to the Schema but is still acceptable for storage.

Severity is **user-defined at the Schema level**: each constraint in a Property Definition specifies whether
violation is an error or a warning. The default severity when unspecified is warning (permissive by default),
since the system already needs to handle invalid Subjects due to Schema changes.

This maps to two real categories:

1. **Errors** — hard constraints the schema author considers essential for data integrity
   (e.g., a required identifier field)
2. **Warnings** — conformance issues that should be surfaced but not block persistence
   (e.g., a number out of preferred range, a missing optional-but-recommended field)

Pros:
* Schema authors control which constraints are strict vs. lenient, matching their domain needs
* Addresses the ECHOLOT CH import scenario: messy data is accepted with warnings rather than rejected

Cons:
* Warning/error severity on constraints in the Schema model and UIs
* API responses that distinguish warnings from errors
* Adds a concept (severity) to the Schema model that schema authors need to understand
