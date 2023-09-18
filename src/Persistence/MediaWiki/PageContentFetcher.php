<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Content;
use MalformedTitleException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use TitleParser;

class PageContentFetcher {

	public function __construct(
		private readonly TitleParser $titleParser,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	public function getPageContent( string $pageTitle, Authority $authority, int $defaultNamespace = NS_MAIN ): ?Content {
		try {
			$title = $this->titleParser->parseTitle( $pageTitle, $defaultNamespace );
		} catch ( MalformedTitleException ) {
			return null;
		}

		$revision = $this->revisionLookup->getRevisionByTitle( $title );

		return $revision?->getContent( SlotRecord::MAIN, RevisionRecord::FOR_THIS_USER, $authority );
	}
	
}
