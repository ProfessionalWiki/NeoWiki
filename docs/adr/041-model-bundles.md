# Model Bundles

Date: 2026-11-15

Status: Draft

## Context

Modellers build a Data Model in one wiki: Schemas, the Layouts and Mappings that go with them, and controlled
vocabularies kept as small Schemas whose Subjects are the terms. Other wikis need that model and each later version of
it. Until now that took scripts against the REST API or `edit.php`, and taking a new version replaced whatever a wiki
had changed in the model since.

## Decision

A **Model Bundle** is one JSON file holding Schemas, Layouts and Mappings by name and Subjects by ID, plus a name and
an optional version and description. A file without a name is a plain export of the same items. Items are written in a
canonical form, so that two exports of the same Data Model are the same file and modellers can keep bundles in git.

Special:DataModel installs, updates and exports bundles in the browser. Installing writes each item through the REST
and Action API endpoints the editors use, Subjects keeping their IDs, and then writes an **install record**: a JSON
page per bundle, `MediaWiki:NeoWiki/Model bundles/<name>.json`. It holds the bundle's version and source, the Schemas,
Layouts and Mappings the bundle installed, in canonical form, and a hash of each of its Subjects, whole Subjects being
too large for a page. An item belongs to one bundle at most.

An update compares each item three ways: the recorded version, the wiki's version and the file's. What only the bundle
changed is replaced by default; what the wiki changed too is shown in both versions and replaced only if the admin
ticks it; what the bundle no longer has is offered for removal.

Catalogs, JSON files at URLs that a wiki's settings name, list bundles by the URLs of their files, each as a model or a
destination with the bundles it requires; the browser reads them, and installs a bundle with those it requires.

For page weight, Model Bundles is an extension of its own in NeoWiki's repository, NeoWikiModelBundles, which a wiki
enables besides NeoWiki. Its frontend is a ResourceLoader module that only Special:DataModel loads, and that reaches
NeoWiki's frontend through `require( 'ext.neowiki' )`. NeoWiki's Special:Schemas imports and exports Schemas only.

## Consequences

- Each item is written as its own edit, with the wiki's own permission checks, validation and page history. A write
  that fails is reported and the others go on.
- Writing the record takes the `editsitejson` and `editinterface` rights, so by default only sysops and interface
  administrators install bundles. The record's history shows who installed which version when, and deleting it
  detaches the bundle, leaving its items.
- A change made on the wiki shows as such for as long as the bundle is installed.
- Subject IDs are kept, so relations resolve across the wikis that install a bundle. An ID a wiki already uses for
  something else is a conflict the preview shows.
- An install interrupted midway leaves its items written but no record. Installing again finds them unchanged and
  records them.
- Recorded Schemas, Layouts and Mappings are brought into the current canonical form before they are compared, so a
  change to how they serialize changes nothing. The Subject hashes depend on the canonical form of Subjects: a change
  to it shows installed Subjects as changed here, unless it comes with a migration of the records. Neither the file
  nor the record carries a format version.
- Every install reads each item from the wiki, and so does Special:DataModel for each bundle it shows expanded, so a
  bundle of a few hundred Subjects costs a few hundred requests.
- Bundles are told apart by name alone, versions are compared only for equality, and a bundle cannot require another. A
  catalog of bundles will have to settle all three.
- A command line would have to reproduce the canonical forms, the hashing and the comparison exactly, or run the same
  TypeScript.
- Pages that load NeoWiki's frontend carry none of Model Bundles' code or messages. Without the extension,
  Special:Schemas imports only the Schemas of a file that holds more; with it, Special:Schemas refers such a file to
  Special:DataModel.
- Model Bundles depends on NeoWiki's public JavaScript API, which is not yet stable. Both are released together.

## Alternatives Considered

### Part of NeoWiki's frontend

Built into NeoWiki, Model Bundles added 68 KB of script (16 KB gzipped, some 12%) and 97 messages to the module that
every page with NeoWiki's interface loads.

### MediaWiki XML import

Special:Export and Special:Import move whole pages with their history. A Subject would travel as the page that holds
it, wikitext included, and an import cannot tell a change made upstream from one made locally.

### Page Exchange

The Page Exchange extension installs and updates sets of pages from published package files. It also works with whole
pages, so it has the same problem with Subjects, and would make every NeoWiki wiki depend on another extension.

### A database table for install records

A table could be queried directly, but needs a schema change, has no page history, and is not visible on the wiki.

### A server-side install

A maintenance script or API module could install a bundle on the server, through the Application actions that the REST
handlers adapt, so its writes would behave as the editors' do. It would let an install run in the job queue and from a
command line. The cost is more PHP and a second client: the canonical forms, the hashing and the comparison written
again in PHP, to be kept the same as the browser's.
