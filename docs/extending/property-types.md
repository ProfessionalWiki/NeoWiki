---
title: Property Types
order: 2
---
# Property Types

A Property Type is a kind of structured value ([Glossary](../glossary.md#property-type)): a PHP class, a
projection per store, and frontend components.

## Backend class

Implement `PropertyType`, paired with a class extending `PropertyDefinition` that holds the type-specific definition
fields, and register it with `NeoWikiRegistrar::addPropertyType()`. Example:
[`src/ColorType.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/ColorType.php) and [`src/ColorProperty.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/ColorProperty.php).

`PropertyType::validate()` returns `Violation[]`. NeoWiki runs no validation in the browser; the input component
shows the violations the server returns, each `code` resolved to the message key `neowiki-field-<code>`, which your
extension defines (RedHerb's [`i18n/en.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/i18n/en.json)).

## Projection

Statements of a Property Type without a Neo4j value builder are omitted from the Neo4j projection; without an RDF
value mapper, from the RDF export.

For Neo4j, register a builder that converts the Value to Neo4j scalars under the Property Type name:

```php
$registrar->addNeo4jValueBuilder( ColorType::NAME, static fn ( $value ) => $value->toScalars() );
```

For the [RDF export](../api/rdf-export.md), register a mapper under the Property Type name with
`NeoWikiRegistrar::addRdfValueMapper()`. It receives the Statement's `NeoValue` and returns a list of RDF terms —
`Literal`s, or `Iri`s for values that denote a resource, as the built-in `url` mapper does — one per value part.
Guard the value shape: the mapper receives whatever a Statement holds. RedHerb's
[`RedHerbHooks.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbHooks.php) registers a guarded mapper for its color type.

## Frontend components

Register the type's components through the `neowiki.registration` JS hook from your
[frontend module](extending.md#frontend-module):

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
[`PropertyTypeRegistration.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/domain/PropertyTypeRegistration.ts); every field is required, including
`attributesEditor` even for a type with no configurable attributes. `typeName` must equal the backend
`PropertyType::getTypeName()`; `createPropertyDefinitionFromJson( base, json )` extends the common definition `base`
with the type-specific fields your `PropertyDefinition` class serializes; `label` is a message key your module
registers. The components conform to
[`ValueDisplayContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Value/ValueDisplayContract.ts),
[`ValueInputContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Value/ValueInputContract.ts), and
[`AttributesEditorContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/SchemaEditor/Property/AttributesEditorContract.ts).

`icon` is a Codex `Icon`: a stock icon from the
[gallery](https://doc.wikimedia.org/codex/latest/icons/all-icons.html), declared via `CodexModule::getIcons` as
RedHerb's [`extension.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/extension.json) does, or an SVG string.

Full example: [`resources/init.js`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/init.js) with [`ColorDisplay.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/ColorDisplay.vue),
[`ColorInput.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/ColorInput.vue), and
[`ColorAttributesEditor.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/ColorAttributesEditor.vue).
