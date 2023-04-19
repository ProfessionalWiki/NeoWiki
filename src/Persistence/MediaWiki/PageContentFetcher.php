<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Content;
use MalformedTitleException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use TitleParser;

class PageContentFetcher {

	private TitleParser $titleParser;
	private RevisionLookup $revisionLookup;

	public function __construct( TitleParser $titleParser, RevisionLookup $revisionLookup ) {
		$this->titleParser = $titleParser;
		$this->revisionLookup = $revisionLookup;
	}

	public function getPageContent( string $pageTitle ): ?Content {
		try {
			$title = $this->titleParser->parseTitle( $pageTitle );
		}
		catch ( MalformedTitleException ) {
			return null;
		}

		$revision = $this->revisionLookup->getRevisionByTitle( $title );

		return $revision?->getContent( SlotRecord::MAIN );
	}

}
