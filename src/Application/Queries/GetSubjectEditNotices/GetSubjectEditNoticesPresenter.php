<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices;

use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;

interface GetSubjectEditNoticesPresenter {

	/**
	 * @param SubjectEditNotice[] $notices
	 */
	public function presentNotices( array $notices ): void;

}
