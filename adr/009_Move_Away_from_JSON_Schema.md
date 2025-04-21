# Move Away from JSON Schema

Date: May 2023

## Context

In ADR 006 we decided to use JSON Schema for our schemas. However, we have since found that using
JSON schema internally introduces accidental complexity.

The accidental complexity comes from expressivity of JSON schema that we do not need and from a mismatch between
JSON schema and how our UIs represent and edit data.

## Decision

We will move away from JSON schema and instead use a custom schema format that is more closely aligned with our UIs.

Including:
* We will replace the array type with a "multiple" attribute
* We will implement required properties via a "required" attribute rather than the schema-level "required" list

## Consequences

* We can simplify our code dealing with schemas, especially code related to handling of multiple values.
* We can no longer use standard JSON-schema-bases validators to validate Subjects. Then again, we anticipated those
  would not have sufficed anyway.
* We no longer expose schemas in a standard format. If such a requirement arises we can still implement it via a
  web API that converts our custom schema format to JSON schema.
