# Release notes

To move an install to a newer version, see [Upgrading](https://neowiki.ai/docs/operations/upgrading).

## Unreleased

* `MediaWiki:NeoWiki` refuses invalid settings on MediaWiki 1.47 too, and Action and REST API errors now name them

## NeoWiki 0.1.1

* On MediaWiki 1.47, pages that place their Main Subject with `{{#main_subject}}` no longer show it twice
* The Subject editor lists each Subject you are editing, with its Schema, and stays still as you switch between them
* The Data tab opens with the page's Main Subject expanded, and hides the empty "Other Subjects on this Page" heading
  from users who cannot edit
* Special:NeoWiki no longer says "No subjects yet." on a wiki that has Subjects
* Values of Property Types from other extensions no longer sometimes show as blank or as "not available on this wiki"
* The interface is available in German (by Klaus Feldmann)
* A SPARQL store that stops responding now delays a save by at most 10 seconds instead of 25
* In the Docker demo, if QLever cannot read its data and fails to start, `make upgrade` rebuilds the data from the wiki
* The Docker demo remembers the extras you last started, such as Neo4j and HTTPS for sharing, even after `make down`,
  until you pass different ones to `make up` or `make upgrade`; if you ran `make down` before this upgrade, see below

### If you ran `make down` before upgrading the Docker demo

A plain `make upgrade` then starts only the default services. If your demo ran more, add it once:

* Neo4j, for Cypher queries: `make upgrade services=qlever,neo`
* Sharing it with others over HTTPS: `COMPOSE_PROFILES=server make upgrade`
* Both: `COMPOSE_PROFILES=server make upgrade services=qlever,neo`

From then on, a plain `make upgrade` keeps them. If you already upgraded without them, run the upgrade again this way.

## NeoWiki 0.1.0

The first release, a beta. Highlights:

* Structured data on wiki pages: Subjects, edited in forms generated from their Schema and versioned with the page
* Schemas with nine Property Types, including relations between Subjects, dates and language-tagged text, and
  constraints that block a save or only warn
* Infoboxes, with Layouts that choose which properties they show and how
* Cypher queries on Neo4j, SPARQL queries on SPARQL 1.1 stores, and RDF export, with Subjects mapped to ontologies such
  as EDM and CIDOC-CRM
* Parser functions, a Lua library, a REST API, and extension points for new Property Types, View Types and graph stores
* A Docker stack with demo data, to try NeoWiki in one command

Try NeoWiki at [neowiki.dev](https://neowiki.dev), or start with
[Installation](https://neowiki.ai/docs/operations/installation). [Capabilities](https://neowiki.ai/capabilities) tours
NeoWiki in depth.

### Docker stacks from before 0.1.0

The Docker stack no longer runs Neo4j, which Cypher queries need, unless you name it in `services=` each time you start
the stack, as in `make upgrade services=qlever,neo`. QLever, its SPARQL store, runs as before.

* Unless you keep Neo4j, run `make down` before `make upgrade`, or its container keeps running unused.
* Existing demo pages query Neo4j. `make import-demo-data` replaces them with pages that query QLever. Pages
  rendered before the import keep their old errors until MediaWiki runs the jobs it queues;
  `php maintenance/run.php runJobs` in `make bash` runs them at once.
