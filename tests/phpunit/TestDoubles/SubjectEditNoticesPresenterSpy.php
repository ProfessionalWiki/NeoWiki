<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices\GetSubjectEditNoticesPresenter;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;

class SubjectEditNoticesPresenterSpy implements GetSubjectEditNoticesPresenter {

	/**
	 * @var SubjectEditNotice[]
	 */
	private array $notices = [];

	public function presentNotices( array $notices ): void {
		$this->notices = $notices;
	}

	/**
	 * @return string[]
	 */
	public function presentedKeys(): array {
		return array_map( static fn ( SubjectEditNotice $notice ): string => $notice->key, $this->notices );
	}

}
