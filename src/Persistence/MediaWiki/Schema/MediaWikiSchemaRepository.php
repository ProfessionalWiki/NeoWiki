<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Schema;

use MediaWiki\Revision\SlotRecord;
use ProfessionalWiki\NeoWiki\Application\SchemaRepository;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdater;
use MWException;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Factories\CommentFactory;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Factories\ContentFactory;
use Title;

class MediaWikiSchemaRepository implements SchemaRepository {

	public function __construct(
		private readonly PageUpdater $pageUpdater,
		private readonly ContentFactory $contentFactory,
		private readonly CommentFactory $commentFactory
	) {
	}

	/**
	 * @throws MWException
	 */
	public function saveSchema( Title $title, string $text, int $editFlag ): RevisionRecord|null {
		$content = $this->contentFactory->create( $text,  $title );
		$commentMessage = $editFlag === EDIT_NEW ? 'New schema is created' : 'The schema is updated';
		$comment = $this->commentFactory->create( $commentMessage );

		$this->pageUpdater->setContent( SlotRecord::MAIN, $content );
		return $this->pageUpdater->saveRevision( $comment, $editFlag );
	}
}
