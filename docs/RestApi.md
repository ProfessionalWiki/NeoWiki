# REST API

NeoWiki exposes a REST API under `/neowiki/v0/*`. The API surface is auto-documented as an OpenAPI 3.0 spec
generated from each handler's declared parameters and request body.

## Browsing the spec

- **Full per-module spec:** `/rest.php/specs/v0/module/-`
- **Discovery (list of available modules):** `/rest.php/specs/v0/discovery`

On the local dev wiki the full URL is `http://localhost:8484/rest.php/specs/v0/module/-`.

You can paste the JSON emitted by the module endpoint into a Swagger UI or Redoc viewer
(e.g. [editor.swagger.io](https://editor.swagger.io)) to browse it visually.

## Where the spec comes from

The spec is not hand-maintained. It is built at request time by MediaWiki core's `ModuleSpecHandler` from two sources:

- The `RestRoutes` array in `extension.json`, which registers each path and its HTTP method.
- The `getParamSettings()` and `getBodyParamSettings()` methods on each REST handler class under
  `src/EntryPoints/REST/`. These declare parameter names, types, required flags, and descriptions.

To document a new endpoint, add its route to `extension.json` and make sure the handler declares `PARAM_DESCRIPTION`
on every parameter in `getParamSettings()` (and on body fields in `getBodyParamSettings()` if the endpoint has a
body). The emitted spec picks the rest up automatically.

## Stability

The REST API is pre-1.0. Endpoints, request/response payloads, and the emitted spec itself may change without notice
until the project hits 1.0. Do not treat `/neowiki/v0/*` as stable for third-party integrations yet.

## Drift check

A PHPUnit integration test (`tests/phpunit/EntryPoints/REST/ModuleSpecHandlerNeoWikiTest.php`) runs on CI and
verifies that every route registered in `extension.json` appears in the emitted OpenAPI spec, with its declared
params and body schemas. If a handler's `getParamSettings()` declaration silently drops a field, the test will fail.
