# Release notes

To move an install to a newer version, see [Upgrading](https://neowiki.ai/docs/operations/upgrading).

## NeoWiki 0.1.0

Not released yet.

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

The Docker stack's graph store is now Oxigraph, a SPARQL store. Neo4j, which Cypher queries need, and QLever run only
if you name them in `services=` each time you start the stack, as in `make upgrade services=oxigraph,neo`.

* Run `make down` before `make upgrade`, or the Neo4j and QLever containers keep running unused.
* `make upgrade` fills Oxigraph from the wiki.
* Existing demo pages query Neo4j. `make import-demo-data` replaces them with pages that query Oxigraph. Pages
  rendered before the import keep their old errors until MediaWiki runs the jobs it queues;
  `php maintenance/run.php runJobs` in `make bash` runs them at once.
