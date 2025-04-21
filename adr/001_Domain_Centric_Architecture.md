# Domain Centric Architecture

Date: March 2023

Status: Accepted

## Context

We are building a non-trivial application on top of MediaWiki. We need to decide how to structure the code.

We choose to create NeoWiki instead of enhancing Semantic MediaWiki in part because Semantic MediaWiki's internal
quality is poor. It is difficult to understand and modify. We wish to avoid the same fate for NeoWiki.

## Decision

We use domain centric architecture.

This includes a structure similar to The Clean Architecture with a domain layer, application layer, presentation layer,
and persistence layer. It also includes CQRS and a reasonable effort to adhere to the Domain Driven Design principles
of Ubiquitous Language and non-Anemic Domain Model.

## Consequences

Cons:

* Junior developers will need guidance to understand where to put code, since there are various restrictions.

Pros:

* Increased testability and maintainability
* Insulation from changes to the framework (MediaWiki)
* Increased ability to swap out presentation layer and persistence layer components such as the graph database
