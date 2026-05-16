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
  `registrar.registerPropertyType(...)`. The registration's `typeName` must equal the
  backend `PropertyType::getTypeName()`. The display, input, and attributesEditor
  components conform to NeoWiki's component prop shapes — see
  [`ValueDisplayContract.ts`](../../resources/ext.neowiki/src/components/Value/ValueDisplayContract.ts),
  [`ValueInputContract.ts`](../../resources/ext.neowiki/src/components/Value/ValueInputContract.ts),
  and [`AttributesEditorContract.ts`](../../resources/ext.neowiki/src/components/SchemaEditor/Property/AttributesEditorContract.ts).
  RedHerb: [`resources/init.js`](resources/init.js),
  [`resources/ColorDisplay.vue`](resources/ColorDisplay.vue),
  [`resources/ColorInput.vue`](resources/ColorInput.vue),
  [`resources/ColorAttributesEditor.vue`](resources/ColorAttributesEditor.vue).
- **Use NeoWiki's public JS API** — `require('ext.neowiki')`; the full surface is
  [`public-api.ts`](../../resources/ext.neowiki/src/public-api.ts).
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
