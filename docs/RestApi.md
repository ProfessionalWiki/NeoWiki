# REST API

NeoWiki's REST API lives under `/neowiki/v0/*`. There is no hand-written reference — MediaWiki core's
`ModuleSpecHandler` emits an OpenAPI 3.0 document from each handler's declared parameters and body schema.

## Browsing the spec

The spec endpoints are not registered by default. Add this to `LocalSettings.php` to expose them:

```php
$wgRestAPIAdditionalRouteFiles[] = 'includes/Rest/specs.v0.json';
```

Then:

- **Full spec:** `/rest.php/specs/v0/module/-`
- **Discovery (list of modules):** `/rest.php/specs/v0/discovery`

On the local dev wiki: `http://localhost:8484/rest.php/specs/v0/module/-`.

Paste the emitted JSON into [editor.swagger.io](https://editor.swagger.io) or a similar viewer for a
visual browse.

## How the spec is built

`ModuleSpecHandler` combines two sources at request time:

- `extension.json` — the `RestRoutes` array (paths, HTTP methods).
- REST handler classes under `src/EntryPoints/REST/` — `getParamSettings()` and `getBodyParamSettings()`
  (param names, types, required flags, descriptions).

To document a new endpoint, register its route in `extension.json` and set `PARAM_DESCRIPTION` on
every parameter and body field on the handler. The rest is picked up automatically.

## Stability

Pre-1.0. Endpoints, payloads, and the emitted spec may change without notice until 1.0. Do not treat
`/neowiki/v0/*` as stable for third-party integrations yet.

## Drift check

`tests/phpunit/EntryPoints/REST/ModuleSpecHandlerNeoWikiTest` runs on CI and asserts that:

- Every route registered in `extension.json` appears in the emitted spec with the expected methods.
- Every path or query parameter declared in `getParamSettings()` is rendered into the operation's `parameters`.
- Every body field declared in `getBodyParamSettings()` is rendered into the operation's `requestBody`.
- Every path or query parameter in the emitted spec carries a non-empty `description`.

What this catches: the framework silently stops emitting something a handler declared (e.g., a route
becomes invisible to `ModuleSpecHandler`). What it does not catch: intentional removal of a declaration
— that is covered by the per-handler tests that exercise the affected behaviour. The test builds the
spec through the framework directly, so it passes regardless of whether `specs.v0.json` is enabled on
the wiki.
