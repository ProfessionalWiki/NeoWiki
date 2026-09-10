---
title: Using NeoWiki from JavaScript
order: 10
---
# Using NeoWiki from JavaScript

`require( 'ext.neowiki' )` from your [frontend module](extending.md#frontend-module) returns NeoWiki's public API
barrel; its exports are listed in [`public-api.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/public-api.ts). `nw.NeoWikiServices` is the service
locator: the repositories (`getSubjectRepository()`, `getSchemaRepository()`, `getLayoutRepository()`), the
component registry, and `registerServices( app )` for a Vue app of your own. The value model and factories
(`newStringValue`, `newNumberValue`) live in [`domain/Value.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/domain/Value.ts).

## Displaying values

Render each value through its Property Type's display component; rendering by hand loses Display Attributes and
custom types. `resolveDisplayProperties()` picks the properties a Layout shows, or every non-empty one without a
Layout:

```javascript
const nw = require( 'ext.neowiki' );
const registry = nw.NeoWikiServices.getComponentRegistry();

// [ { propertyDefinition, value }, ... ]
const resolvedProperties = nw.resolveDisplayProperties( schema, subject, layout );
```

```html
<component
	v-for="resolved in resolvedProperties"
	:key="resolved.propertyDefinition.name.toString()"
	:is="registry.getValueDisplayComponent( resolved.propertyDefinition.type )"
	:value="resolved.value"
	:property="resolved.propertyDefinition"
></component>
```

Inside a View the stores hold the Subject, Schema and Layout (`nw.useSubjectStore()`, `useSchemaStore()`,
`useLayoutStore()`); elsewhere, fetch them through the repositories. The props are typed in
[`ValueDisplayContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Value/ValueDisplayContract.ts); `subjectDisplayName( subject )` names a
Subject the way NeoWiki does. Example: [`RedHerbCard.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/RedHerbCard.vue).

## Mounting standalone Vue features

Give a Vue app of your own NeoWiki's Pinia instance and its services. NeoWiki's components inject the services and
read the shared stores, so both calls are required:

```javascript
const nw = require( 'ext.neowiki' );
const app = Vue.createMwApp( MyComponent );

app.use( nw.NeoWikiExtension.getInstance().getPinia() );
nw.NeoWikiServices.registerServices( app );
app.mount( '#my-mount-point' );
```

Examples: [`resources/createChild/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/createChild),
[`resources/editMainSubject/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/editMainSubject), and
[`resources/subjectFinder/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/subjectFinder).

If you mount `nw.SubjectEditor` yourself, call `unparseableInput()` before `getSubjectData()` and hold the save while
it is non-null. It returns the first field showing text the widget cannot turn into a Value — the property name and
the message the field displays — and `getSubjectData()` returns that statement without a value, so the text would
be lost on save. `nw.SubjectEditorDialog` does this for you.

## Authoring in TypeScript

NeoWiki ships no types package ([ADR 24](../adr/024-frontend-extension-mechanism.md)); a published one is deferred
until a consumer needs types without a NeoWiki checkout. Point your `tsconfig.json` `paths` at the barrel source,
which assumes NeoWiki is installed beside your extension as `extensions/NeoWiki`:

```json
"paths": {
	"ext.neowiki": [ "../NeoWiki/resources/ext.neowiki/src/public-api" ]
}
```

The runtime specifier then carries types too: `import { ValueType, type PropertyTypeRegistration } from 'ext.neowiki';`.
Mark the modules NeoWiki already provides as external in your bundler, so you do not ship a second copy and break
the shared store: `ext.neowiki`, `vue`, `@wikimedia/codex`, `@wikimedia/codex-icons` and `pinia`.
