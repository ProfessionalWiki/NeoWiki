# Architecture Overview

These diagrams follow the [C4 model](https://c4model.com/) and render natively on GitHub via Mermaid.

## System Context

NeoWiki and its users and external systems.

```mermaid
C4Context
    title NeoWiki - System Context

    Person(wikiUser, "Wiki User", "Views wiki pages, structured data, and graph query results")
    Person(contentEditor, "Content Editor", "Creates and edits Subjects, Schemas, and Views")
    Person(apiConsumer, "API Consumer", "Third-party system or developer integrating via the REST API")

    System(neowiki, "NeoWiki Wiki", "Provides structured data management, graph-based querying, and knowledge graph capabilities on top of MediaWiki")

    System_Ext(sparqlClients, "External SPARQL Clients", "LOD and semantic web clients querying the public SPARQL endpoint [planned]")

    Rel(wikiUser, neowiki, "Views pages and query results", "HTTPS")
    Rel(contentEditor, neowiki, "Creates and edits structured data", "HTTPS")
    Rel(apiConsumer, neowiki, "CRUD operations, queries", "REST API")
    Rel(sparqlClients, neowiki, "Queries RDF data", "SPARQL Protocol")

    UpdateRelStyle(wikiUser, neowiki, $offsetY="-40")
    UpdateRelStyle(contentEditor, neowiki, $offsetY="-40")
    UpdateRelStyle(apiConsumer, neowiki, $offsetY="-40")
    UpdateRelStyle(sparqlClients, neowiki, $offsetY="-40")
```

## Containers

The major technical building blocks inside the NeoWiki Wiki system.

```mermaid
C4Container
    title NeoWiki - Container Diagram

    Person(wikiUser, "Wiki User", "Views wiki pages and structured data")
    Person(contentEditor, "Content Editor", "Creates and edits Subjects and Schemas")
    Person(apiConsumer, "API Consumer", "Third-party integrator")

    System_Ext(sparqlClients, "External SPARQL Clients", "[planned]")

    Container_Boundary(system, "NeoWiki Wiki") {
        Container(frontend, "NeoWiki Frontend", "Vue.js, TypeScript, Codex", "Interactive UIs for viewing and editing Subjects, Schemas, and Views. Loaded within wiki pages via ResourceLoader.")
        Container(backend, "MediaWiki with NeoWiki Extension", "PHP 8.3, MediaWiki 1.43+", "Serves wiki pages, handles REST API requests, executes parser functions (#cypher), domain logic, and data synchronization")
        ContainerDb(mariadb, "MediaWiki Database", "MariaDB", "Pages, revisions incl. NeoWiki JSON in dedicated revision slots, user accounts, wiki configuration")
        ContainerDb(neo4j, "Neo4j", "Neo4j Community", "Query-optimized property graph projection of structured data, queried via Cypher")
        ContainerDb(tripleStore, "Triple Store", "SPARQL 1.1 conformant [planned]", "RDF projection for SPARQL queries and LOD interoperability")
    }

    Rel(wikiUser, frontend, "Views structured data and query results")
    Rel(contentEditor, frontend, "Creates/edits Subjects, Schemas, Views")
    Rel(apiConsumer, backend, "CRUD, queries", "REST API")
    Rel(sparqlClients, tripleStore, "Queries RDF data", "SPARQL Protocol")

    Rel(frontend, backend, "Subject/Schema operations, label search", "REST API")
    Rel(backend, mariadb, "Reads/writes pages, revisions, JSON data", "SQL")
    Rel(backend, neo4j, "Syncs graph projection, executes Cypher queries", "Bolt")
    Rel(backend, tripleStore, "Syncs RDF triples, proxies SPARQL queries", "HTTP")
```
