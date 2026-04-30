# RedHerb

RedHerb is a minimal MediaWiki extension that demonstrates how to extend NeoWiki. It is shipped inside the NeoWiki
repository as a live reference and is used by NeoWiki's own tests to verify that the extension points it covers work
end-to-end. RedHerb does not exercise every extension point NeoWiki exposes — it grows over time as new examples are
needed.

If you are building a NeoWiki extension, use this directory as a working example alongside the high-level guide below.

## Extending NeoWiki

> **Stability:** NeoWiki is pre-1.0; everything below is alpha and may change without notice.

### 1. `extension.json`

A NeoWiki extension is a normal MediaWiki extension. The only NeoWiki-specific requirement is a hard dependency on
NeoWiki under `requires.extensions`. See `extension.json` for a complete example, including how to register the hook
handlers and ResourceLoader modules described below.

### 2. Backend registration: `NeoWikiRegistration` hook

NeoWiki fires the `NeoWikiRegistration` hook once during initialization, passing a `NeoWikiRegistrar`. This is the
single entry point for all backend registrations. Through it, an extension can:

- **Add a Property Type** — `addPropertyType( PropertyType $type )`. Implement the `PropertyType` interface and pair
  it with a class extending `PropertyDefinition` (see `src/Domain/Schema/Property/TextProperty.php` for the simplest
  built-in example).
- **Add a Neo4j value builder** — `addNeo4jValueBuilder( string $propertyTypeName, callable $builder )`. Tells
  NeoWiki how to convert a `NeoValue` of the given property type into the scalar(s) stored on graph nodes. Required
  for any new property type whose values should be queryable in Neo4j.
- **Add a Page Property Provider** — `addPagePropertyProvider( PagePropertyProvider $provider )`. Contributes
  additional key-value pairs onto `Page` nodes in the graph database. Useful for extension-specific page-level
  metadata that should be queryable via Cypher.

See `src/RedHerbHooks.php` for an example registering all three, and `src/ColorType.php`, `src/ColorProperty.php`,
and `src/StaticPagePropertyProvider.php` for the implementations they register.

### 3. Frontend module loading: `NeoWikiGetFrontendModules` hook

NeoWiki only loads its own ResourceLoader module (`ext.neowiki`) when its UI is rendered. To have your own RL modules
loaded alongside it (and only then), implement `NeoWikiGetFrontendModulesHook` and append your module names to
`$modules`. The `OutputPage` and `Skin` arguments are available for conditional loading.

See `src/RedHerbFrontendHook.php`. Multiple implementations may coexist — `extension.json` registers two in RedHerb.

### 4. Frontend registration: `mw.hook('neowiki.registration')`

Each NeoWiki frontend mount fires the `neowiki.registration` JS hook with a `FrontendRegistrar`. Subscribe in your
module's entry point to register a Property Type frontend via `registrar.registerPropertyType( registration )`,
where `registration` matches the `PropertyTypeRegistration` TypeScript interface.

The backend `PropertyType::getTypeName()` and the frontend `registration.typeName` must match.

#### Vue component contracts

The `displayComponent`, `inputComponent`, and `attributesEditor` you supply must conform to these contracts (in
NeoWiki's `resources/ext.neowiki/src/`):

- **`displayComponent`** — `components/Value/ValueDisplayContract.ts`.
- **`inputComponent`** — `components/Value/ValueInputContract.ts`.
- **`attributesEditor`** — `components/SchemaEditor/Property/AttributesEditorContract.ts`.

See `resources/init.js`, `ColorDisplay.vue`, `ColorInput.vue`, and `ColorAttributesEditor.vue` for working examples.

#### Icon

The `icon` field on the registration is a Codex icon. The default path — used by RedHerb and by every built-in
NeoWiki property type — is to pick a stock icon from <https://doc.wikimedia.org/codex/latest/icons/all-icons.html>
and declare it in your ResourceLoader module's `MediaWiki\ResourceLoader\CodexModule::getIcons` callback (see
RedHerb's `extension.json`). If no stock icon fits, you can supply a custom SVG by exporting it from a TypeScript
file as a string; see `resources/ext.neowiki/src/assets/CustomIcons.ts` for the pattern.

### 5. Public frontend API: `require('ext.neowiki')`

Your RL module should declare `ext.neowiki` as a dependency and use `require('ext.neowiki')` to access NeoWiki's
public TS API. See `resources/ext.neowiki/src/public-api.ts` for the full list of exports.

### 6. i18n

Standard MediaWiki i18n (`i18n/en.json`, `i18n/qqq.json`). One NeoWiki-specific convention: validation error codes
returned from a Property Type's `validate` function are resolved as `neowiki-field-<code>` message keys, so your
extension must provide those messages for any custom codes it returns. RedHerb's `i18n/en.json` contains
`neowiki-field-invalid-hex` and `neowiki-field-not-in-palette` for this reason.
