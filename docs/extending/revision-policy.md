---
title: Revision policy
order: 5
---
# Revision policy

Choose which revision of a page NeoWiki publishes: to the graph stores, the RDF export, the Subject read
`GET /neowiki/v0/subject/{subjectId}`, and its own Schema, Layout, Mapping and configuration reads. Without a policy
every page publishes its latest revision.

```php
class ApprovalRevisionPolicy implements RevisionPolicy {

	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
		return $this->approvalLookup->lastApprovedRevisionOf( $revision->getPage() );
	}

	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
		return $this->approvalLookup->isApproved( $revision ) || $viewer->isAllowed( 'myext-see-drafts' );
	}

}
```

Register with `NeoWikiRegistrar::setRevisionPolicy()`; a second policy is refused with a warning on the `NeoWiki`
log channel.

- `publishedRevision()` receives the page's current revision and returns the one to publish, of the same page, or
  `null`, which deletes the page's Page node and Subjects from the graph stores and makes the RDF export and the
  Subject read answer not-found for it. A policy that throws counts as returning `null`, with the error logged. It
  runs on every page save and rebuild and on every read above, so keep it cheap.
- `revisionIsReadableBy()` is asked when a REST caller names a `revisionId` or asks for `latest`
  ([REST API](../api/rest-api.md)); a refusal answers exactly like a revision that does not exist.

Approval changes outside an edit reach NeoWiki only through
[`rebuild()`](page-properties.md#refreshing-a-pages-data-without-an-edit), and a newly registered policy reaches
the graph stores only through a [full rebuild](../operations/maintenance.md#rebuilding-the-graph).

Editing reads the latest revision. So do `?action=subjects`, `GET /neowiki/v0/page/{pageId}/subjects` and the
referenced Subjects on a `revisionId` read ([#1390](https://github.com/ProfessionalWiki/NeoWiki/issues/1390)), and
the parse-time accessors. Schema and Mapping reads are cached under the latest revision id, so an approval change
without an edit to that Schema or Mapping page takes effect on its next edit or when the entry expires
([#1392](https://github.com/ProfessionalWiki/NeoWiki/issues/1392)).
