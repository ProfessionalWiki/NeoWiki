<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Content;
use MalformedTitleException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use TitleParser;

class PageContentFetcher {

	private TitleParser $titleParser;
	private RevisionLookup $revisionLookup;
	private Authority $authority;

	public function __construct( TitleParser $titleParser, RevisionLookup $revisionLookup, Authority $authority ) {
		$this->titleParser = $titleParser;
		$this->revisionLookup = $revisionLookup;
		$this->authority = $authority;
	}

	public function getPageContent( string $pageTitle, ?Authority $authority, int $defaultNamespace = NS_MAIN ): ?Content {
		try {
			$title = $this->titleParser->parseTitle( $pageTitle, $defaultNamespace );
		} catch ( MalformedTitleException ) {
			return null;
		}

		$revision = $this->revisionLookup->getRevisionByTitle( $title );

		$authority = $authority ?? $this->authority;

		return $revision?->getContent( SlotRecord::MAIN, RevisionRecord::FOR_THIS_USER, $authority );
	}
}
