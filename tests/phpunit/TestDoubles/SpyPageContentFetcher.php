<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Content\Content;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;

/**
 * A PageContentFetcher on a wiki without a revision policy: every existing page publishes its latest
 * revision, and a missing one publishes none. Reading a revision's content returns the given Contents
 * in turn, the last one from then on, and is counted.
 */
class SpyPageContentFetcher extends PageContentFetcher {

	public int $fetchCount = 0;

	/**
	 * @var array<?Content>
	 */
	private readonly array $contents;

	public function __construct( ?Content ...$contents ) {
		$this->contents = $contents;
	}

	public function getPublishedRevision( Title $title ): ?RevisionRecord {
		if ( !$title->exists() ) {
			return null;
		}

		$revision = new MutableRevisionRecord( PageIdentityValue::localIdentity( 1, NS_MAIN, 'Page' ) );
		$revision->setId( $title->getLatestRevID() );
		return $revision;
	}

	public function getRevisionContent( RevisionRecord $revision, Authority $authority, string $slotName ): ?Content {
		$content = $this->contents[min( $this->fetchCount, count( $this->contents ) - 1 )];
		$this->fetchCount++;
		return $content;
	}

}
