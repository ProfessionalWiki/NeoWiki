# Global Properties

Written 2026-01-31 by Jeroen De Dauw with some help from Claude Opus 4.5

## Context

Currently, Property Definitions are local to their Schema ([ADR 6](../adr/006_Schemas.md)). "Name" in a Person
Schema and "Name" in a Company Schema are independent definitions that can have different types and constraints.

Both Semantic MediaWiki and Wikibase use global properties: a property is defined once and reused across types.
ECHOLOT consortium partners familiar with RDF and Linked Open Data have suggested NeoWiki should do the same,
since in RDF, properties like `dcterms:title` or `foaf:name` are globally defined.

This document explores whether that change is warranted.

## Pros of Global Properties

**Direct ontology alignment.** Each global property can map 1:1 to an ontology term. With local properties,
the same ontology term (e.g., `foaf:name`) must be mapped separately for every Schema that has a "Name" property.

**Enforced consistency.** Same-named properties cannot accidentally have different types across Schemas.

**Cross-schema query semantics.** A property name on a Subject node in the [graph](../GraphModel.md) is
guaranteed to mean the same thing regardless of the Subject's Schema.

**Reduced redundancy.** Properties like "Name" or "Website" are defined once instead of duplicated across
every Schema that uses them.

**Familiar to SMW/Wikibase users.** These existing systems use global properties.

## Cons of Global Properties

**Thin abstraction.** Currently a [Property Definition](../Glossary.md#property-definition) carries its type,
constraints (like `minimum`/`maximum`), `required`, `default`, `multiple`, and display hints (like `precision`).
Whether a property is required, what its default is, and what constraints apply are per-Schema decisions. Display
settings are per-[View](../adr/018_Views.md). Once you move all of that out, a global property is just a name
and a type. It is unclear if that justifies the architectural complexity.

**Schema creation UX suffers.** Instead of defining properties inline while creating a Schema, users must
find-or-create global properties and then reference them. More steps, more indirection. Notion databases, Coda
tables, and class definitions in programming languages all use locally defined fields. Users of these tools
expect to define fields on the type, not reference a shared registry.

**Non-obvious side effects.** Editing a global property (e.g., changing its type or description) affects every
Schema that uses it. This is hard for novice users to anticipate and goes against our goal of high UX quality.

**"Same name" does not mean "same concept."** A person's "Name" and a city's "Name" are semantically different.
Global properties either conflate them or force distinct names like "Person Name" and "City Name", which is
local properties with extra indirection.

**Needs additional storage and UI.** Global Property Definitions require their own namespace or registry,
a creation/editing UI, and search/browse functionality — all separate from the Schema UI.

**Interaction with Views is unclear.** Display attributes like `precision` are currently Property Definition
Attributes but are also listed as View Attributes in [ADR 18](../adr/018_Views.md). With global properties,
this overlap becomes a design problem: a property has no display defaults outside of a View context unless we
add yet another layer (e.g., display defaults on the Schema's property reference that Views can override).

**Opportunity cost of implementation.** Time we spent on changing from local to global properties could be spent
on something else with higher expected value.

#### Limitations To The Upsides

**Cross-schema queries without type constraints are rare in practice.** Realistic queries almost always include
a Schema label: `MATCH (n:City) WHERE n.Name = "Berlin"`. That already works with local properties. Querying
across all types without constraints returns a mixed bag of unrelated results.

**RDF mapping does not require global properties.** A mapping layer can translate local properties to ontology
terms during import/export. ECHOLOT T2.3 talks about "model-model mappings" between systems, not requiring every
system to adopt the same model. A mapping configuration like "Person.Name maps to `foaf:name`" and "City.Name
maps to `dcterms:title`" achieves interoperability without changing the data model.

## Summary

The main argument for global properties is direct alignment with RDF ontologies. The main argument against is
that the same alignment can be achieved via a mapping layer, without the UX and architectural costs.

With local properties and a mapping layer:
- Schema creation stays simple: define properties inline
- No side effects across Schemas when editing a property
- Ontology mapping is a separate, explicit configuration
- The mapping layer is needed regardless, so not a downside of this approach
- Property Definitions keep their current role, carrying type, constraints, and defaults together

With global properties:
- Ontology alignment is implicit in the property itself
- UX suffers notably
- The boundary between what lives on the property, the Schema, and the View needs to be resolved
- Additional refactoring work
