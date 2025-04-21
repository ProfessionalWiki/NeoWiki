# One Schema per Subject

Date: May 2023

## Decision

* Subjects can only have one type/schema
* We do not allow adding values for properties that are not defined in the schema, at least not via the UI

## Consequences

* Simpler and more user friendly UIs
* Easier to develop said UIs
* Easier to detect issues with the data
* Not possible to have a Subject of multiple types
