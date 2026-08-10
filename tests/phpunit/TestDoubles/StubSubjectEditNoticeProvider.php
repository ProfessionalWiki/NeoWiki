<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeProvider;

class StubSubjectEditNoticeProvider implements SubjectEditNoticeProvider {

	/**
	 * @param SubjectEditNotice[] $notices
	 */
	public function __construct(
		private readonly array $notices
	) {
	}

	public function getNotices( SubjectEditNoticeContext $context ): array {
		return $this->notices;
	}

}
