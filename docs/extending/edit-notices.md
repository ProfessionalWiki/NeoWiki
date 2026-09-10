---
title: Edit notices
order: 6
---
# Edit notices

Show a message in the Subject editor, the Subject creator and Manage subjects.

```php
class ApprovalEditNoticeProvider implements SubjectEditNoticeProvider {

	public function __construct(
		private readonly MessageLocalizer $messageLocalizer
	) {
	}

	public function getNotices( SubjectEditNoticeContext $context ): array {
		return [ new SubjectEditNotice(
			key: 'myext-approval',
			html: $this->messageLocalizer->msg( 'myext-approval-notice' )->parse()
		) ];
	}

}
```

Register with `NeoWikiRegistrar::addSubjectEditNoticeProvider()`. Provider `html` is inserted as given: escape it
yourself. Notices render in registration order after the ones admins write as interface messages; providers are not
called for pages the requesting user may not read. `SubjectEditNoticeContext` exposes `$pageId`, `$pageDbKey`,
`$namespaceId`, and `$schemaName` when a Schema is known.

Namespace keys to your extension; the first provider to claim a key keeps it.
[Edit notices](../authoring/edit-notices.md) lists the keys admins use.
