# Validation Severity Levels

Related to [Validation Architecture](Validation.md). This is a separate concern from where validation
happens (frontend, backend, or both).

## Context

[ADR 12: Backend Validation](../adr/012_Backend_Validation.md) established that invalid Subjects can be
persisted. This is necessary because Schemas can change after Subjects have been saved, making previously
valid Subjects invalid. The system already handles this case.

Severity levels formalize this by distinguishing between violations that should block persistence and
violations that should be surfaced but allowed.

## Proposal

Validation results are split into two severity levels:

* **Errors**: the Subject is rejected and not persisted. Represents hard constraints that must always
  be satisfied.
* **Warnings**: the Subject is flagged as invalid but still persisted. Represents soft constraints where
  the data does not conform to the Schema but is still acceptable for storage.

Severity is **user-defined at the Schema level**: each constraint in a Property Definition specifies whether
violation is an error or a warning. The default severity when unspecified is warning (permissive by default),
since the system already needs to handle invalid Subjects due to Schema changes.

This maps to two real categories:

1. **Errors**: hard constraints the schema author considers essential for data integrity
   (e.g., a required identifier field)
2. **Warnings**: conformance issues that should be surfaced but not block persistence
   (e.g., a number out of preferred range, a missing optional-but-recommended field)

Pros:
* Schema authors control which constraints are strict vs. lenient, matching their domain needs
* Addresses the ECHOLOT CH import scenario: messy data is accepted with warnings rather than rejected

Cons:
* Warning/error severity on constraints in the Schema model and UIs
* API responses that distinguish warnings from errors
* Adds a concept (severity) to the Schema model that schema authors need to understand
