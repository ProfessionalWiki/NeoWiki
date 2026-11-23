<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use InvalidArgumentException;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use TypeError;

/**
 * The Subjects a revision gives its page to publish, for the writes that publish them. Unlike a read, which shows the
 * Subjects it can, such a write leaves the page as it is when the slot cannot be read: writing the page as holding no
 * Subjects would drop the ones it holds.
 */
class SubjectsToPublish {

	/**
	 * None when the revision has no Subject slot.
	 *
	 * @throws UnreadableSubjectsException When the slot holds content that is not Subject data, or Subject data that
	 *   does not deserialize.
	 */
	public static function of( RevisionRecord $revision ): PageSubjects {
		if ( !$revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
			return PageSubjects::newEmpty();
		}

		$content = $revision->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME );

		if ( !$content instanceof SubjectContent ) {
			throw new UnreadableSubjectsException( 'Its subject slot holds content that is not Subject data.' );
		}

		try {
			return $content->getPageSubjects();
		} catch ( InvalidArgumentException | TypeError $exception ) {
			throw new UnreadableSubjectsException(
				'Its Subject data does not deserialize: ' . $exception->getMessage(),
				previous: $exception
			);
		}
	}

}
