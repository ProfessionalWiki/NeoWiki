# Move Away from JSON Schema

Date: May 2023

Status: Accepted

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
* We can no longer use standard JSON-schema-based validators to validate Subjects. Then again, we anticipated those
  would not have sufficed anyway.
* We no longer expose schemas in a standard format. If such a requirement arises we can still implement it via a
  web API that converts our custom schema format to JSON schema.

## Alternatives Considered

*Added 2026-06-28. NeoWiki's move toward RDF and Linked Open Data (see
[ADR 19](019-graph-database-architecture.md)) makes RDF-native shape languages an obvious question. This section
records why they are not used as our internal schema format. The decision against JSON Schema itself is the subject
of this ADR, above.*

### RDF shape languages: SHACL and ShEx

[SHACL](https://www.w3.org/TR/shacl/) (a W3C standard) and [ShEx](https://shex.io/) (a grammar-based schema language,
used by Wikidata for its EntitySchemas) both describe and validate the shape of **RDF graphs**.

Not adopted as our internal schema format, for the same reasons we moved away from JSON Schema, which apply more
strongly here:

* **They target RDF graphs, which is not our internal model.** Our data are Subjects and Statements stored as JSON
  ([ADR 2](002-store-data-as-json.md)); RDF is one of several *projection/export* targets, each owning its own
  mapping ([ADR 19](019-graph-database-architecture.md)). Defining schemas in an RDF-native language would couple the
  core to RDF and undercut that backend-agnostic, map-at-export approach.
* **They are validation languages, not editing schemas.** The driving reason in this ADR — alignment with how our UIs
  represent and edit data — has no counterpart in SHACL or ShEx, which say nothing about form-based creation and
  editing of data.
* **They carry expressivity we do not need.** Open-world graph semantics, logical shape algebra, and (for SHACL)
  SPARQL-based constraints are exactly the kind of accidental complexity this ADR moves away from.

This does not rule out *emitting* SHACL or ShEx as a downstream artifact: generating shapes from our native schemas
at RDF-export time — for RDF-side validation, documentation, or interoperability — is a separate and potentially
valuable concern (see [planning/RdfMapping.md](../planning/RdfMapping.md)). That is a mapping output, not the schema
format itself.
