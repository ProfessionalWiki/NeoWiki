---
title: Extending NeoWiki
order: 7
---
# Extending NeoWiki

NeoWiki exposes extension points so other MediaWiki extensions can add custom Property Types, contribute
page metadata to the graph, and reuse NeoWiki's UI. This page is the reference for those extension points and
the APIs extensions build on.

NeoWiki concepts referenced here — Subject, Schema, Property Type, Page Property — are defined in the
[Glossary](../concepts/glossary.md).

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

### Reading NeoWiki data and authorization

`NeoWikiExtension::getInstance()` exposes read-side services usable from any MediaWiki extension point
(hooks, special pages):

- `newSubjectAuthorizer( Authority )` — subject permission checks.
- `newPageSubjectsLookup()` — look up the subjects on a page.
- `newSubjectContentRepository()` — read Subject data by id.
- `newFrontendModuleLoader()` — mount NeoWiki's UI on any page.

Examples: [`src/RedHerbSidebarHook.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbSidebarHook.php)
and [`src/Specials/SpecialRedHerbSubjectFinder.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/Specials/SpecialRedHerbSubjectFinder.php).

## Frontend extension points (JS/Vue)

NeoWiki's frontend is built with TypeScript and Vue, but extensions consume it as plain JavaScript — no
TypeScript build step is required.

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
var nw = require( 'ext.neowiki' );

mw.hook( 'neowiki.registration' ).add( function ( registrar ) {
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
		validate: validate,
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
var nw = require( 'ext.neowiki' );
var app = Vue.createMwApp( MyComponent );

app.use( nw.NeoWikiExtension.getInstance().getPinia() );
nw.NeoWikiServices.registerServices( app );
app.mount( '#my-mount-point' );
```

Examples: [`resources/createChild/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/createChild),
[`resources/editMainSubject/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/editMainSubject),
and [`resources/subjectFinder/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/subjectFinder).

## Conventions

### i18n and validation codes

A Property Type validates in two places: the backend `PropertyType::validate()` (authoritative — returns
`Violation[]`) and the frontend `validate` (immediate UX feedback — returns an array of `{ code }` objects,
empty meaning valid). Implement both. NeoWiki resolves each frontend `code` as the message key
`neowiki-field-<code>`; your extension must define those messages. For example, returning
`{ code: 'invalid-hex' }` requires a `neowiki-field-invalid-hex` message (see RedHerb's
[`i18n/en.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/i18n/en.json)).

### Icons

The frontend registration's `icon` is a Codex `Icon`. RedHerb uses stock Codex icons (browse the
[icon gallery](https://doc.wikimedia.org/codex/latest/icons/all-icons.html)) declared via
`CodexModule::getIcons` in its
[`extension.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/extension.json).
Custom SVG icons are also supported — pass an SVG string as the `icon`.

## Not yet extensible

These extension points are designed or partially present but not yet open to extensions:

- **View Types.** The View Type plug-in system is built ([ADR 18](../adr/018-views.md)) and `ViewTypeRegistry`
  is part of the public API, but `infobox` is the only built-in type and there is no wired-up registration path
  for third-party extensions yet: unlike Property Types, the `neowiki.registration` hook exposes no View Type
  registration.
- **Graph database backends.** A `GraphDatabasePlugin` interface exists, but Neo4j is the only backend and
  is currently hardcoded.
- **TypeScript types.** The plain-JavaScript path above is the supported way to extend the frontend; NeoWiki
  publishes no `.d.ts` type definitions, so there is no typed path yet.
