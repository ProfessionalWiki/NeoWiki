# Move Away from JSON Schema

Date: May 2023

Status: Accepted

## Context

In ADR 006, we decided to use JSON Schema for our schemas. However, we have since found that using
JSON schema internally introduces accidental complexity.

The accidental complexity comes from the expressivity of JSON schema that we do not need, and from a mismatch
between JSON schema and how our UIs represent and edit data.

## Decision

We will move away from JSON schema and instead use a custom schema format that is more closely aligned with our UIs.

Including:
* We will replace the array type with a "multiple" attribute
* We will implement required properties via a "required" attribute rather than the schema-level "required" list

## Consequences

* We can simplify our code dealing with schemas, especially code related to handling of multiple values.
* We can no longer use standard JSON-schema-based validators to validate Subjects. Then again, we anticipated those
  would not have sufficed anyway.
* We no longer expose schemas in a standard format. If such a requirement arises, we can still implement it via a
  web API that converts our custom schema format to JSON schema.

## Alternatives Considered

### Continued usage of JSON Schema

We would need to maintain a higher level of internal complexity. Users who need JSON Schema can still easily be
accommodated via a new API endpoint that does simple translation.

### RDF shape languages: SHACL and ShEx

[SHACL](https://www.w3.org/TR/shacl/) (a W3C standard) and [ShEx](https://shex.io/) (a grammar-based schema language,
used by Wikidata for its EntitySchemas) both describe and validate the shape of **RDF graphs**.

Not adopted as our internal schema format, for the same reasons we moved away from JSON Schema. Our editing UIs
cannot handle all their expressivity. Nearly all potential users are better served with a subset of their
expressivity, avoiding both unnecessary implementation and carry costs, and complexity in the UIs and elsewhere.

Additionally, our data model is not an RDF graph.

This does not rule out *emitting* SHACL or ShEx as a downstream artifact. We could generate shapes from our native
schemas, for instance, at RDF-export time.
