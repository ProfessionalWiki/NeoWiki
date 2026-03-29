<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;

readonly class PagePropertiesBuilder {

	public function __construct(
		private RevisionStore $revisionStore,
		private IContentHandlerFactory $contentHandlerFactory,
		private PagePropertyProviderRegistry $providerRegistry,
	) {
	}

	public function getPagePropertiesFor( RevisionRecord $revision, ?UserIdentity $user ): PageProperties {
		$context = $this->buildContext( $revision, $user );

		$properties = [];

		foreach ( $this->providerRegistry->getProviders() as $provider ) {
			$properties = array_merge( $properties, $provider->getProperties( $context ) );
		}

		return new PageProperties( $properties );
	}

	private function buildContext( RevisionRecord $revision, ?UserIdentity $user ): PagePropertyProviderContext {
		return new PagePropertyProviderContext(
			pageId: new PageId( $revision->getPageId() ),
			title: $revision->getPageAsLinkTarget()->getText(),
			creationTime: $this->getCreationTime( $revision ),
			modificationTime: $this->getModificationTime( $revision ),
			categories: $this->getCategories( $revision ),
			lastEditor: $user?->getName() ?? '',
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
	private function getCategories( RevisionRecord $revision ): array {
		$content = $revision->getContent( SlotRecord::MAIN );

		if ( $content === null ) {
			return [];
		}

		return $this->contentHandlerFactory->getContentHandler( $content->getModel() )
			->getParserOutput( $content, new ContentParseParams( $revision->getPage() ) )
			->getCategoryNames();
	}

}
