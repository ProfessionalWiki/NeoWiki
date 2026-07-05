# Claude Code guidance for NeoWiki

NeoWiki is a MediaWiki extension: a PHP backend (`src/`, domain-centric — see
[ADR 1](docs/adr/001-domain-centric-architecture.md)) and a Vue/TypeScript frontend (`resources/ext.neowiki/`).
The documentation map is [docs/README.md](docs/README.md). The
[glossary](docs/concepts/glossary.md) defines the Ubiquitous Language: use its terms in code, tests, and docs.

## Commands

* `make ci` — PHPUnit + PHPCS + PHPStan. Narrow with `make phpunit filter=Foo`.
* `make ts-ci` — vitest + eslint/stylelint + `vue-tsc` build. Narrow with `make ts-test filter=Foo`.
* PHPUnit runs through MediaWiki core (`php ../../tests/phpunit/phpunit.php`), not `vendor/bin/phpunit` — always
  use the Makefile targets. Integration tests need the dev stack running (see "Development" in
  [README.md](README.md)).

## Conventions

* Tests mirror `src/` 1:1. Pure unit tests extend `PHPUnit\Framework\TestCase`; anything needing MediaWiki or
  Neo4j extends `tests/phpunit/NeoWikiIntegrationTestCase` and carries an `IntegrationTest` suffix. Pure-domain
  TS specs use a `.unit.spec.ts` suffix.
* PHP files `declare( strict_types = 1 )`; value objects are immutable (`readonly`).
* When a wire format changes (revision slot JSON, REST API, graph model), update the matching
  `docs/reference/` page in the same PR.
* Architectural decisions are recorded as ADRs in `docs/adr/`; significant design work ends with a new or
  amended ADR.
