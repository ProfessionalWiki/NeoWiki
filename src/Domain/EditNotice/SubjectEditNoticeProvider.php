<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\EditNotice;

interface SubjectEditNoticeProvider {

	/**
	 * The html of each notice is rendered as given, with no sanitization and no presentation added
	 * by NeoWiki, so escape it yourself and bring whatever styling the notice needs.
	 *
	 * @return SubjectEditNotice[]
	 */
	public function getNotices( SubjectEditNoticeContext $context ): array;

}
