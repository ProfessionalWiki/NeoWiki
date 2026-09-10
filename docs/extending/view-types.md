---
title: View Types
order: 3
---
# View Types

A View Type renders a Subject in a particular visual format; `infobox` is the only built-in one. View Types are
frontend-only: register a Vue component through the `neowiki.registration` hook from your
[frontend module](extending.md#frontend-module):

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

The registration object's shape is defined by [`ViewTypeRegistration.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/domain/ViewTypeRegistration.ts).
The component conforms to the `ViewProps` prop shape ([`ViewContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Views/ViewContract.ts)):
the `subjectId` to render, a `canEditSubject` flag saying whether to offer editing, and an optional `layoutName`.
The Subject, its Schema and the Layout are read from the stores (`nw.useSubjectStore()`, `useSchemaStore()`,
`useLayoutStore()`); a Layout's Settings are free-form JSON your component reads through `layout.getSettings()`,
as the card reads `fullWidthProperties`. Once registered, the `typeName` is selectable as a Layout's View Type, and
a `{{#view}}` or Main Subject placeholder that references it renders through your component instead of the
built-in infobox.

The `redherb-card` example renders each value through the [value display components](javascript.md#displaying-values).

## Editing from a View

The card opens the shared `nw.SubjectEditorDialog` when `canEditSubject` is true. Editing reads go through the
repositories (`nw.NeoWikiServices.getSubjectRepository()`, `getSchemaRepository()`), not the stores, and reach the
dialog as props. Seed the editor with `getSubjectForEditing()`: `getSubject()` answers with the revision the wiki
publishes, which a save would overwrite. A save through the dialog updates the stores itself. Relation fields in the
dialog offer creating
the target Subject in place only when the dialog is given an `onCreate` handler alongside `onSave`, which decides
the page the new Subject goes to; the handler types are in
[`SubjectEditorDialog.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/SubjectEditor/SubjectEditorDialog.vue).

Full example: [`resources/init.js`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/init.js) with [`RedHerbCard.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/RedHerbCard.vue).
