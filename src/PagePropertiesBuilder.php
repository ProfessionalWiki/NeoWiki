<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use WikiPage;

class PagePropertiesBuilder {

	public function __construct(
		private readonly RevisionStore $revisionStore
	) {
	}

	public function getPagePropertiesFor( RevisionRecord $revision, ?WikiPage $wikiPage, ?UserIdentity $user ): PageProperties {
		return new PageProperties(
			title: $revision->getPageAsLinkTarget()->getText(),
			creationTime: $this->getCreationTime( $revision ),
			modificationTime: $this->getModificationTime( $revision ),
			categories: $this->getCategories( $wikiPage ),
			lastEditor: $user?->getName() ?? ''
		);
	}

	private function getCreationTime( RevisionRecord $revision ): string {
		$time = $this->revisionStore->getFirstRevision( $revision->getPage() )?->getTimestamp();

		if ( $time === null ) {
			throw new \RuntimeException( 'Got null for creation time' );
		}

		return $time;
	}

	private function getModificationTime( RevisionRecord $revision ): string {
		$time = $revision->getTimestamp();

		if ( $time === null ) {
			throw new \RuntimeException( 'Got null for modification time' );
		}

		return $time;
	}

	/**
	 * @return string[]
	 */
	private function getCategories( ?WikiPage $wikiPage ): array {
		$categories = [];

		// FIXME: this is getting the categories from the "previous" revision, not the revision that is being saved
		foreach ( $wikiPage?->getCategories() ?? [] as $category ) {
			$categories[] = $category->getText();
		}

		return $categories;
	}

}
