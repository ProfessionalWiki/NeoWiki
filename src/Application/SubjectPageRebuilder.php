<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;

class SubjectPageRebuilder {

	public function __construct(
		private readonly OnRevisionCreatedHandler $handler,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
	}

	public function rebuild( Title $title ): bool {
		$revision = $this->wikiPageFactory->newFromTitle( $title )->getRevisionRecord();

		if ( $revision === null ) {
			return false;
		}

		$user = $revision->getUser();

		if ( $user === null ) {
			return false;
		}

		$this->handler->onRevisionCreated( $revision, $user );
		return true;
	}

}
