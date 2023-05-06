<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Content;
use MalformedTitleException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use TitleParser;

class PageContentFetcher {

	private TitleParser $titleParser;
	private RevisionLookup $revisionLookup;

	public function __construct( TitleParser $titleParser, RevisionLookup $revisionLookup ) {
		$this->titleParser = $titleParser;
		$this->revisionLookup = $revisionLookup;
	}

	public function getPageContent( string $pageTitle, int $defaultNamespace = NS_MAIN ): ?Content {
		try {
			$title = $this->titleParser->parseTitle( $pageTitle, $defaultNamespace );
		}
		catch ( MalformedTitleException ) {
			return null;
		}

		$revision = $this->revisionLookup->getRevisionByTitle( $title );

		return $revision?->getContent( SlotRecord::MAIN ); // TODO: RevisionRecord::FOR_THIS_USER, $this->authority
	}

}
