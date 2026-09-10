<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;

/**
 * The content of a revision's Subject slot, for reads. Null when the revision has no such slot and
 * when the slot holds content of another model: a read shows the Subjects it can and leaves the rest
 * out. A write path has to refuse such a slot instead, since it would save over it.
 */
class SubjectSlotReader {

	public static function read( RevisionRecord $revision ): ?SubjectContent {
		try {
			$content = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME );
		} catch ( RevisionAccessException ) {
			return null;
		}

		return $content instanceof SubjectContent ? $content : null;
	}

}
