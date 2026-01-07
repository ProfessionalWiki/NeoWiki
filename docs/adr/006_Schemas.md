# Schemas

Date: April 2023

Status: Accepted

## Context

Users should be able to define properties and also specify which properties are expected for a given type of Subject.

Semantic MediaWiki and Wikibase both work with "global properties" and lack first-class schema support. In Semantic
MediaWiki schema support is somewhat emulated via templates and Page Forms, though the core software does not understand
or enforce those schemas.

## Decision

* We have Schemas that define the type of a Subject and the properties that are expected for that type.
* Schemas are defined in JSON
* Schemas are stored on dedicated pages in the Schema namespace
* Schemas follow the JSON Schema specification

## Consequences

* We can build forms to create and edit subjects based on their schema.
* We can validate subjects against their schema.
* We can build UIs for creating and editing schemas.
* Properties are namespaced within their schema instead of global. Thus, different types/schemas can have properties with
  the same name but a different definition.
