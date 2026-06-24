# RedHerb

RedHerb is a minimal MediaWiki extension shipped inside the NeoWiki repository as a
live, test-backed reference for how to extend NeoWiki. NeoWiki's own tests exercise it,
so the examples here are kept working. RedHerb does not cover every extension point and
grows over time as new examples are added. If you are building a NeoWiki extension, use
this directory as a working example alongside the index below.

> **Stability:** NeoWiki is pre-1.0. Every extension point below is alpha and may change
> without notice.

## Backend extension points (PHP)

- **Declare a NeoWiki dependency** — list NeoWiki under `requires.extensions` in
  [`extension.json`](extension.json).
- **Declare JS modules that depend on NeoWiki** — add `ResourceModules` entries with
  `dependencies: [ "vue", "ext.neowiki" ]` in [`extension.json`](extension.json); this
  is what makes `require('ext.neowiki')` available on the frontend.
- **Register property types and graph data** — the `NeoWikiRegistration` hook delivers a
  `NeoWikiRegistrar`: `addPropertyType()`, `addNeo4jValueBuilder()` (value → Neo4j
  scalars), and `addPagePropertyProvider()`. A property type implements the
  `PropertyType` interface, paired with a class extending `PropertyDefinition`. See
  [`src/RedHerbHooks.php`](src/RedHerbHooks.php),
  [`src/ColorType.php`](src/ColorType.php) (`implements PropertyType`),
  [`src/ColorProperty.php`](src/ColorProperty.php) (`extends PropertyDefinition`), and
  [`src/StaticPagePropertyProvider.php`](src/StaticPagePropertyProvider.php).
- **Load frontend modules alongside NeoWiki's UI** — the `NeoWikiGetFrontendModules`
  hook. See [`src/RedHerbFrontendModulesHook.php`](src/RedHerbFrontendModulesHook.php).
- **Query NeoWiki data and authorization from PHP** — `NeoWikiExtension::getInstance()`
  exposes `newSubjectAuthorizer()` (e.g. `canCreateChildSubject()`, `canEditSubject()`),
  `newPageSubjectsLookup()` (e.g. `pageHasMainSubject()`), and `newFrontendModuleLoader()`
  (mount NeoWiki's UI on any page). See [`src/RedHerbSidebarHook.php`](src/RedHerbSidebarHook.php)
  and [`src/Specials/SpecialRedHerbSubjectFinder.php`](src/Specials/SpecialRedHerbSubjectFinder.php).
- **Integrate via standard MediaWiki extension points backed by NeoWiki data** — a
  `SidebarBeforeOutput` hook and a special page. See
  [`src/RedHerbSidebarHook.php`](src/RedHerbSidebarHook.php) and
  [`src/Specials/SpecialRedHerbSubjectFinder.php`](src/Specials/SpecialRedHerbSubjectFinder.php).

## Frontend extension points (JS/Vue)

- **Register a property type frontend** — the `neowiki.registration` JS hook
  (`mw.hook('neowiki.registration')`) delivers a registrar; call
  `registrar.registerPropertyType(...)`. This code only runs if your module is loaded
  alongside NeoWiki's UI (the `NeoWikiGetFrontendModules` hook above). The object you
  pass matches
  [`PropertyTypeRegistration.ts`](../../resources/ext.neowiki/src/domain/PropertyTypeRegistration.ts);
  every field is required, including `attributesEditor` even for a type with no
  configurable attributes. The registration's `typeName` must equal the backend
  `PropertyType::getTypeName()`. The display, input, and attributesEditor
  components conform to NeoWiki's component prop shapes — see
  [`ValueDisplayContract.ts`](../../resources/ext.neowiki/src/components/Value/ValueDisplayContract.ts),
  [`ValueInputContract.ts`](../../resources/ext.neowiki/src/components/Value/ValueInputContract.ts),
  and [`AttributesEditorContract.ts`](../../resources/ext.neowiki/src/components/SchemaEditor/Property/AttributesEditorContract.ts).
  RedHerb: [`resources/init.js`](resources/init.js),
  [`resources/ColorDisplay.vue`](resources/ColorDisplay.vue),
  [`resources/ColorInput.vue`](resources/ColorInput.vue),
  [`resources/ColorAttributesEditor.vue`](resources/ColorAttributesEditor.vue).
- **Register a view type frontend** — the same `neowiki.registration` registrar offers
  `registrar.registerViewType(...)`, at parity with property types. The object you pass
  matches
  [`ViewTypeRegistration.ts`](../../resources/ext.neowiki/src/domain/ViewTypeRegistration.ts):
  a `typeName` plus the Vue `component` that renders it. The component conforms to the
  [`ViewTypeContract.ts`](../../resources/ext.neowiki/src/components/Views/ViewTypeContract.ts)
  prop shape (`subjectId`, `canEditSubject`, `layoutName`). The `redherb-card` example
  assembles NeoWiki's own building blocks: the subject / schema / layout stores
  (`nw.useSubjectStore()` etc.), `nw.resolveDisplayProperties` plus the value-display
  component registry (`nw.NeoWikiServices.getComponentRegistry()`) to render each value with
  its property type's component, and the shared `nw.SubjectEditorDialog` for the edit
  affordance (rendered only when `canEditSubject` is true). NeoWiki populates the stores
  before mounting the view. A registered `typeName` becomes selectable as a Layout's View
  Type; a `{{#view}}` (or Main Subject) placeholder referencing it then renders through your
  component instead of the built-in infobox. RedHerb: [`resources/init.js`](resources/init.js),
  [`resources/RedHerbCard.vue`](resources/RedHerbCard.vue).
- **Use NeoWiki's public JS API** — `require('ext.neowiki')`; exports are listed in
  [`public-api.ts`](../../resources/ext.neowiki/src/public-api.ts) (a re-export barrel).
  The value model and factories (`newStringValue`, `newNumberValue`) live in
  [`domain/Value.ts`](../../resources/ext.neowiki/src/domain/Value.ts); value shape
  varies by `valueType`.
- **Mount standalone Vue features wired to NeoWiki services** — obtain NeoWiki's Pinia
  via `NeoWikiExtension.getInstance().getPinia()` and call
  `NeoWikiServices.registerServices(app)`. See [`resources/createChild/`](resources/createChild),
  [`resources/editMainSubject/`](resources/editMainSubject), and
  [`resources/subjectFinder/`](resources/subjectFinder).

## Conventions

- **i18n / validation codes** — standard MediaWiki i18n. A property type's `validate`
  returns `{ code }` objects (see [`resources/init.js`](resources/init.js)) which NeoWiki
  resolves as `neowiki-field-<code>` message keys; your extension must define those
  messages (see [`i18n/en.json`](i18n/en.json), e.g. `neowiki-field-invalid-hex`).
- **Icons** — the property-type registration's `icon` is a Codex `Icon`. RedHerb uses a
  stock Codex icon (browse the gallery at
  <https://doc.wikimedia.org/codex/latest/icons/all-icons.html>) declared via
  `CodexModule::getIcons` in [`extension.json`](extension.json). Custom SVG icons are also supported — pass an SVG
  string as the `icon`; NeoWiki ships SVG-string constants of this form (see
  [`CustomIcons.ts`](../../resources/ext.neowiki/src/assets/CustomIcons.ts)). RedHerb does
  not yet demonstrate a custom SVG icon, and no built-in NeoWiki type currently uses one.
