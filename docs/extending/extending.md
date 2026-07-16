---
title: Extending NeoWiki
order: 1
---
# Extending NeoWiki

NeoWiki exposes extension points so other MediaWiki extensions can add custom Property Types, contribute
page metadata to the graph, and reuse NeoWiki's UI. This page is the reference for those extension points and
the APIs extensions build on.

NeoWiki concepts referenced here — Subject, Schema, Property Type, Page Property — are defined in the
[Glossary](../glossary.md).

[RedHerb](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb) is a minimal, test-backed
example extension shipped in the NeoWiki repository. NeoWiki's own tests exercise it, so its examples stay
working. Each extension point below links to the RedHerb file that demonstrates it, so the fastest start is to
copy the relevant RedHerb file and adapt it.

## Stability

NeoWiki is pre-1.0. Every extension point on this page is alpha and may change without notice until 1.0.

## Getting started

An extension that builds on NeoWiki declares the dependency in its `extension.json`:

```json
"requires": {
	"extensions": {
		"NeoWiki": "*"
	}
}
```

Most backend extension points are registered through the `NeoWikiRegistration` hook, which hands you a
`NeoWikiRegistrar`:

```json
"Hooks": {
	"NeoWikiRegistration": "ProfessionalWiki\\MyExt\\MyExtHooks::onNeoWikiRegistration"
}
```

```php
public static function onNeoWikiRegistration( NeoWikiRegistrar $registrar ): void {
	$registrar->addPropertyType( new ColorType() );
	$registrar->addPagePropertyProvider( new StaticPagePropertyProvider() );
}
```

Full example: [`src/RedHerbHooks.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbHooks.php).

## Backend extension points (PHP)

### Property Types

A Property Type defines a kind of structured value — its Value Type, validation, and Display Attributes. Implement
the `PropertyType` interface, paired with a class extending `PropertyDefinition` that holds the type-specific
definition fields, and register it with `NeoWikiRegistrar::addPropertyType()` (see "Getting started" above). The
linked example shows the methods to implement.

Example: [`src/ColorType.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/ColorType.php)
(`implements PropertyType`) and
[`src/ColorProperty.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/ColorProperty.php)
(`extends PropertyDefinition`).

If your Property Type stores a value that isn't already a Neo4j scalar, also register a builder that converts it,
keyed by the Property Type name:

```php
$registrar->addNeo4jValueBuilder( ColorType::NAME, static fn ( $value ) => $value->toScalars() );
```

#### Contributing RDF value mappers

For the [RDF export](../rdf/rdf-export.md), register a mapper that turns your Property Type's value into RDF
literals, keyed by the Property Type name. It returns a list of `Literal`s (one per value part):

```php
$registrar->addRdfValueMapper(
	ColorType::NAME,
	static function ( NeoValue $value ): array {
		// Your mapper is called for whatever a Statement holds, so guard the value shape.
		$scalars = $value->toScalars();

		if ( !is_array( $scalars ) ) {
			return [];
		}

		$literals = [];

		foreach ( $scalars as $part ) {
			if ( is_scalar( $part ) ) {
				$literals[] = RdfLiteralFactory::typed( (string)$part, 'string' );
			}
		}

		return $literals;
	}
);
```

Without a mapper, a Property Type's Statements are omitted from the RDF export, just as they are from the
Neo4j projection.

### Page Property Providers

Page Property Providers contribute key/value metadata to the Page node in the graph (queryable via Cypher;
Neo4j is currently the only graph backend). Implement `PagePropertyProvider`:

```php
class StaticPagePropertyProvider implements PagePropertyProvider {

	public function getProperties( PagePropertyProviderContext $context ): array {
		return [ 'myext_reviewState' => 'approved' ];
	}

}
```

Register with `NeoWikiRegistrar::addPagePropertyProvider()`. The context exposes the page id, title,
creation and modification times, categories, and last editor. Example:
[`src/StaticPagePropertyProvider.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/StaticPagePropertyProvider.php).

### Refreshing a page's data without an edit

A page's graph data is written on edit and on full rebuild. When data your extension contributes through a
`PagePropertyProvider` changes *outside* an edit — for example an approval extension marking a revision approved —
the graph keeps the old value until the page is next saved. Trigger a refresh on demand:

```php
$outcome = NeoWikiExtension::getInstance()
	->newSubjectPageRebuilder()
	->rebuild( $title );
```

NeoWiki re-runs every registered `PagePropertyProvider` for the page (and re-reads its subject slot) and updates
the Page node. No new revision is created. `rebuild()` returns a `PageRefreshOutcome`:

- `Refreshed` — the Page node was updated.
- `SkippedMissingRevision` — the page has no current revision.
- `SkippedMissingSubjectSlot` — the page carries no NeoWiki subject slot, so there was nothing to write.

Genuine failures (such as the graph store being unreachable) throw rather than returning an outcome.

### Graph Database Backends

NeoWiki currently supports Neo4j only, but the graph projection is an extension point: implement
`GraphDatabasePlugin` and NeoWiki keeps your store in sync alongside Neo4j.

```php
class MyGraphDatabasePlugin implements GraphDatabasePlugin {

	public function savePage( Page $page ): void {
		// Project the page and its subjects into your store.
	}

	public function deletePage( PageId $pageId ): void {
		// Remove the page from your store.
	}

}
```

Register with `NeoWikiRegistrar::addGraphDatabasePlugin()`. Example:
[`src/RedHerbGraphDatabasePlugin.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbGraphDatabasePlugin.php).

`savePage` hands you the page with all of its Subjects and runs for every revision, so subject edits, undeletions
and page moves all reach you as a save. `deletePage` gets only the page id.

**Signal failure by throwing.** On an edit, delete or undelete, NeoWiki logs the failure and lets the user's
operation commit, so a backend being down never blocks the wiki or starves the other backends — your projection is
simply out of sync until someone runs `RebuildGraphDatabases`. During that rebuild, failures propagate instead, so
the script can report which pages did not reconcile.

Make `deletePage` idempotent: the rebuild re-issues a delete for every page MediaWiki no longer has, so it will ask
you to remove pages that are already gone from your store.

### Reading NeoWiki data and authorization

`NeoWikiExtension::getInstance()` exposes read-side services usable from any MediaWiki extension point
(hooks, special pages):

- `newSubjectPermissionHints( Authority )` — side-effect-free subject permission checks, for showing or
  hiding affordances. A positive answer is a hint, not authorization to write.
- `newPageSubjectsLookup()` — look up the subjects on a page.
- `newSubjectContentRepository()` — read Subject data by id.
- `newFrontendModuleLoader()` — mount NeoWiki's UI on any page.

Examples: [`src/RedHerbSidebarHook.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbSidebarHook.php)
and [`src/Specials/SpecialRedHerbSubjectFinder.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/Specials/SpecialRedHerbSubjectFinder.php).

### Running Cypher queries

To run a read-only Cypher query from PHP, use `NeoWikiExtension::getInstance()->newCypherQueryService()`.
It rejects write queries and enforces the timeout and row cap you pass as limits; resolve the values
configured in [`$wgNeoWikiQueryLimits`](../api/query-api.md) with `Neo4jQueryLimits::forUser()` (users
with `apihighlimits` get the higher tier):

```php
$result = NeoWikiExtension::getInstance()->newCypherQueryService()->execute( new Neo4jQueryRequest(
	cypher: 'MATCH (s:Subject:Person) WHERE s.`Birth year` > $minYear RETURN s.name AS name',
	parameters: [ 'minYear' => 2000 ],
	limits: Neo4jQueryLimits::forUser( $this->getUser() ),
) );
```

`execute()` returns a `Neo4jQueryResult` (columns, rows, truncation flag) and throws a `QueryException`
subclass on failure; `newCypherQueryService()` itself throws a `LogicException` on a wiki with no graph
backend configured.

The `User` in `forUser()` only sizes the limits: how heavy a single query may be, not whether the user may
query at all or how often. When running user-supplied queries, check the `neowiki-query` right and rate
limit yourself, as the [Query API](../api/query-api.md) endpoint does.

Example: [`src/Specials/SpecialRedHerbContentPageCount.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/Specials/SpecialRedHerbContentPageCount.php).

## Frontend extension points (JS/Vue)

NeoWiki's frontend is built with TypeScript and Vue. Extensions consume it as plain JavaScript and need no build step.
You can also author in TypeScript with types; see "Authoring in TypeScript" below.

### Loading your frontend

Getting your JavaScript onto NeoWiki pages takes two steps. First, declare a ResourceLoader module that depends
on `ext.neowiki`, which makes `require( 'ext.neowiki' )` available:

```json
"ResourceModules": {
	"ext.myext": {
		"class": "MediaWiki\\ResourceLoader\\CodexModule",
		"dependencies": [ "vue", "ext.neowiki" ],
		"packageFiles": [ "init.js" ]
	}
}
```

Then load that module alongside NeoWiki's UI by handling the `NeoWikiGetFrontendModules` hook:

```php
class MyExtFrontendModulesHook implements NeoWikiGetFrontendModulesHook {

	public function onNeoWikiGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		$modules[] = 'ext.myext';
	}

}
```

Example: [`src/RedHerbFrontendModulesHook.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbFrontendModulesHook.php).

### Registering a Property Type frontend

A backend Property Type needs a matching frontend: a display component, an input component, and an
attributes editor. Register them through the `neowiki.registration` JS hook:

```javascript
const nw = require( 'ext.neowiki' );

mw.hook( 'neowiki.registration' ).add( ( registrar ) => {
	registrar.registerPropertyType( {
		typeName: 'color',
		valueType: nw.ValueType.String,
		displayAttributeNames: [],
		createPropertyDefinitionFromJson: function ( base, json ) {
			return Object.assign( {}, base, {
				allowedColors: Array.isArray( json.allowedColors ) ? json.allowedColors : []
			} );
		},
		getExampleValue: function () {
			return nw.newStringValue( '#ff5733' );
		},
		displayComponent: ColorDisplay,
		inputComponent: ColorInput,
		attributesEditor: ColorAttributesEditor,
		label: 'myext-property-type-color',
		icon: icons.cdxIconHighlight
	} );
} );
```

The registration object's shape is defined by
[`PropertyTypeRegistration.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/domain/PropertyTypeRegistration.ts);
every field is required, including `attributesEditor` even for a type with no configurable attributes. The
`typeName` must equal the backend `PropertyType::getTypeName()`. The display, input, and attributes-editor
components conform to NeoWiki's component prop shapes — see
[`ValueDisplayContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Value/ValueDisplayContract.ts),
[`ValueInputContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Value/ValueInputContract.ts),
and [`AttributesEditorContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/SchemaEditor/Property/AttributesEditorContract.ts).

Full example: [`resources/init.js`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/init.js)
with [`ColorDisplay.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/ColorDisplay.vue),
[`ColorInput.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/ColorInput.vue),
and [`ColorAttributesEditor.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/ColorAttributesEditor.vue).

### Registering a View Type frontend

A View Type renders a Subject in a particular visual format; `infobox` is the only built-in one. Register a Vue
component for a new View Type through the same `neowiki.registration` hook, at parity with Property Types:

```javascript
const nw = require( 'ext.neowiki' );
const RedHerbCard = require( './RedHerbCard.vue' );

mw.hook( 'neowiki.registration' ).add( ( registrar ) => {
	registrar.registerViewType( {
		typeName: 'redherb-card',
		component: RedHerbCard
	} );
} );
```

The registration object's shape is defined by
[`ViewTypeRegistration.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/domain/ViewTypeRegistration.ts):
a `typeName` and the Vue `component` that renders it. The component conforms to the `ViewProps` prop shape
([`ViewContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Views/ViewContract.ts)):
the `subjectId` to render, a `canEditSubject` flag, and an optional `layoutName`. Resolve any Layout-specific
configuration (Display Rules and Settings) from the layout store using `layoutName`. Once registered, the
`typeName` becomes selectable as a Layout's View Type, and a `{{#view}}` (or Main Subject) placeholder that
references it renders through your component instead of the built-in infobox.

The `redherb-card` example reuses NeoWiki's own building blocks rather than rendering values by hand: the subject,
schema, and layout stores; `nw.resolveDisplayProperties` together with the value-display component registry to
render each value through its Property Type's component; and the shared `nw.SubjectEditorDialog` for editing when
`canEditSubject` is true.

Full example: [`resources/init.js`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/init.js)
with [`RedHerbCard.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/RedHerbCard.vue).

### Using NeoWiki's public JS API

`require( 'ext.neowiki' )` returns NeoWiki's public API barrel; its exports are listed in
[`public-api.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/public-api.ts).
The value model and factories (`newStringValue`, `newNumberValue`) live in
[`domain/Value.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/domain/Value.ts);
value shape varies by `valueType`.

### Mounting standalone Vue features

To build a Vue feature wired to NeoWiki's services, obtain NeoWiki's Pinia instance and register its
services on your app:

```javascript
const nw = require( 'ext.neowiki' );
const app = Vue.createMwApp( MyComponent );

app.use( nw.NeoWikiExtension.getInstance().getPinia() );
nw.NeoWikiServices.registerServices( app );
app.mount( '#my-mount-point' );
```

Examples: [`resources/createChild/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/createChild),
[`resources/editMainSubject/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/editMainSubject),
and [`resources/subjectFinder/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/subjectFinder).

### Authoring in TypeScript

Plain JavaScript is the simplest path and needs no build step. You can instead write your extension in TypeScript and
get types for NeoWiki's API. This is configuration on your side; NeoWiki ships nothing extra for it. See
[ADR 24](../adr/024-frontend-extension-mechanism.md) for the reasoning.

Point your `tsconfig.json` `paths` at NeoWiki's barrel source, which sits next to your extension in `extensions/`:

```json
"paths": {
	"ext.neowiki": [ "../NeoWiki/resources/ext.neowiki/src/public-api" ]
}
```

You then get types on the same specifier you load at runtime, for example
`import { ValueType, newStringValue } from 'ext.neowiki';` and
`import type { PropertyTypeRegistration } from 'ext.neowiki';`. Mark the modules NeoWiki already provides as external
in your bundler, so you do not ship a second copy and break the shared store: `ext.neowiki`, `vue`, `@wikimedia/codex`,
`@wikimedia/codex-icons` and `pinia`. At runtime your built JavaScript loads the same `ext.neowiki` module as the rest
of the page.

## Conventions

### i18n and validation codes

A Property Type is validated on the backend by `PropertyType::validate()` (returns `Violation[]`). The
frontend does not validate; it surfaces the violations the server returns. NeoWiki resolves each violation
`code` as the message key `neowiki-field-<code>`, so your extension must define those messages. For example,
a backend validator that returns the code `invalid-hex` requires a `neowiki-field-invalid-hex` message (see
RedHerb's [`i18n/en.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/i18n/en.json)).

### Icons

The frontend registration's `icon` is a Codex `Icon`. RedHerb uses stock Codex icons (browse the
[icon gallery](https://doc.wikimedia.org/codex/latest/icons/all-icons.html)) declared via
`CodexModule::getIcons` in its
[`extension.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/extension.json).
Custom SVG icons are also supported — pass an SVG string as the `icon`.

## Not yet extensible

These extension points are designed or partially present but not yet open to extensions:

- **Query surfaces for a graph database backend.** The projection side is extensible (see
  "Graph Database Backends"), but a backend cannot yet contribute its own parser function, REST route, or
  `mw.neowiki` Lua function; Neo4j's are wired in core.
- **A published TypeScript types package.** TypeScript authors get types today by pointing their `tsconfig` at
  NeoWiki's source (see "Authoring in TypeScript" and [ADR 24](../adr/024-frontend-extension-mechanism.md)). A
  published, versioned package is deferred until a consumer needs types without a NeoWiki checkout.

## Internal surfaces

Everything on this page is alpha, but the surfaces below are internal even by that standard: they are
implementation details that happen to be reachable, and they can change in any release without notice.

### NeoWiki's rendered HTML

The `.ext-neowiki-view` placeholder elements and `data-mw-neowiki-*` attributes are the private contract
between NeoWiki's backend and its frontend for mounting Views. They are not an integration surface: do not
select these elements, read Subject IDs out of them, restyle their internals, or remove and replace them
with your own rendering. Their structure can change in any release.

To control where and how a Subject renders:

- To place a Subject rendering in page content, use the
  [`{{#view}}` parser function](../authoring/parser-functions.md), optionally with a Layout to control which
  properties are shown.
- To render Subjects in your own visual format, register a custom View Type
  (see [Registering a View Type frontend](#registering-a-view-type-frontend)).
- For fully custom UI outside the View system, fetch the data through the [REST API](../api/rest-api.md) or
  the [public JS API](#using-neowikis-public-js-api) and render your own components, mounted as described in
  [Mounting standalone Vue features](#mounting-standalone-vue-features).

### The internal Neo4j client

`NeoWikiExtension::getInstance()->getNeo4jClient()` and `getReadOnlyNeo4jClient()` return the Laudis client
NeoWiki itself uses. Neo4j access is treated as an implementation detail of NeoWiki's persistence layer
([ADR 13](../adr/013-restrict-neo4j-access.md)). Nothing stops an extension from querying through the raw
client, but compare what the documented query interfaces
([`{{#cypher_raw}}`](../authoring/parser-functions.md), [`nw.query`](../authoring/lua-api.md), the
[Query API](../api/query-api.md), and the [PHP query service](#running-cypher-queries)) provide over it:

- **Read-only enforcement.** The query interfaces reject write queries (keyword check plus `EXPLAIN`);
  with the raw client, a bug in calling code can corrupt the graph projection, which is what ADR 13
  exists to prevent.
- **Resource limits.** The query interfaces apply a configured timeout and row cap; the raw client runs
  unbounded queries.
- **Result handling.** The query interfaces return normalized rows and columns; the raw client returns
  Laudis driver types that you convert yourself.
- **Churn exposure.** The query interfaces are documented contracts (alpha, like everything on this page);
  the client accessors are internal wiring that can change or disappear in any release.

Writing to the graph directly deserves particular caution: the graph is a projection of wiki content that
NeoWiki rewrites at will. Saving a page re-projects that page's nodes, and the `RebuildGraphDatabases`
maintenance script rebuilds the projection from scratch, so anything a third party writes into the graph
can be overwritten, orphaned, or deleted at any time. To contribute data to the graph durably, use
[Page Property Providers](#page-property-providers), which NeoWiki re-runs on every save and rebuild.
