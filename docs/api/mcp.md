---
title: MCP
order: 10
---

# MCP

AI agents connect to NeoWiki through the
[MediaWiki MCP Server](https://github.com/ProfessionalWiki/MediaWiki-MCP-Server). On a wiki with NeoWiki installed,
it offers tools to read Schemas, find and read Subjects, run read-only [Cypher queries](query-api.md), and validate,
create, change, and delete Subjects, besides its tools for wiki pages. Its README covers installation, sign-in, and
the full tool list.

The NeoWiki tools act through the [REST API](rest-api.md) as the wiki user the server is signed in as, with that
user's [permissions](rest-api.md#permissions). Their writes are
[validated](validation-codes.md#severity-blocking-and-enforcement) against the Subject's Schema like any other write.
