<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\EditNotice;

interface SubjectEditNoticeProvider {

	/**
	 * @return SubjectEditNotice[]
	 */
	public function getNotices( SubjectEditNoticeContext $context ): array;

}
