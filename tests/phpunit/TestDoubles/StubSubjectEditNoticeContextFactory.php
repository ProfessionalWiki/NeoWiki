<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\EditNotice\SubjectEditNoticeContextFactory;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class StubSubjectEditNoticeContextFactory implements SubjectEditNoticeContextFactory {

	public function __construct(
		private readonly bool $pageExists
	) {
	}

	public function newContext( PageId $pageId, ?string $schemaName ): ?SubjectEditNoticeContext {
		if ( !$this->pageExists ) {
			return null;
		}

		return new SubjectEditNoticeContext(
			pageId: $pageId,
			pageDbKey: 'Berlin',
			namespaceId: 0,
			schemaName: $schemaName
		);
	}

}
