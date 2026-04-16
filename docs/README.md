## Technical Documentation

These docs are aimed at developers working on or interfacing with NeoWiki.

Key docs:
* [Glossary](Glossary.md) - Definitions of NeoWiki concepts. We use these as Ubiquitous Language (UI, code, docs, etc)
* [Parser Functions](ParserFunctions.md) - Reference for `{{#view}}`, `{{#neowiki_value}}`, and `{{#cypher_raw}}`
* [Lua API](LuaAPI.md) - Reference for the `mw.neowiki` Scribunto library
* [Schema Format](SchemaFormat.md) - JSON format for Schema definitions
* [Subject Format](SubjectFormat.md) - JSON format for Subject data
* [Graph Model](GraphModel.md) - Neo4j node and relationship structure
* [Architecture Decision Records](adr/)
* [Planning docs](planning/) - Work-in-progress exploration and discussion documents

REST API docs, including OpenAPI, will be created at some point. Till then, look for "RestRoutes" in [extension.json](../extension.json).
