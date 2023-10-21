<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RenderedRevision;
use User;
use Content;
use CommentStoreComment;
use MWTimestamp;

class RevisionUpdater {

	public function __construct(
		private readonly User $performer,
		private readonly RenderedRevision $renderedRevision
	) {
	}

	/**
	 * @param array<string, Content> $contentBySlot Keys are slot names, values are Content objects
	 */
	public function addSubjectsToRevision( array $contentBySlot ): void {
		/** @var MutableRevisionRecord $revRecord */
		$revRecord = $this->renderedRevision->getRevision();

		foreach ( $contentBySlot as $slotName => $content ) {
			$revRecord->setContent( $slotName, $content );
		}

		// TODO: this causes problems (overrides the comment) and is probably not needed (there should already be a comment)
		$revRecord->setUser( $this->performer )
			->setComment( CommentStoreComment::newUnsavedComment( 'New subjects added' ) )
			->setTimestamp( MWTimestamp::now( (int)TS_MW ) );

		$this->renderedRevision->updateRevision( $revRecord );
	}
}
