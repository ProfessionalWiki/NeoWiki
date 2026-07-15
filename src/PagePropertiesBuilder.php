<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use MediaWiki\Content\Content;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;

readonly class PagePropertiesBuilder {

	public function __construct(
		private RevisionStore $revisionStore,
		private IContentHandlerFactory $contentHandlerFactory,
		private TitleFormatter $titleFormatter,
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
		$linkTarget = $revision->getPageAsLinkTarget();
		$content = $revision->getContent( SlotRecord::MAIN );
		$parserOutput = $content === null ? null : $this->parse( $content, $revision );

		return new PagePropertyProviderContext(
			pageId: new PageId( $revision->getPageId() ),
			pageTitle: $this->titleFormatter->getPrefixedText( $linkTarget ),
			namespaceId: $linkTarget->getNamespace(),
			creationTime: $this->getCreationTime( $revision ),
			modificationTime: $this->getModificationTime( $revision ),
			categories: $parserOutput === null ? [] : $parserOutput->getCategoryNames(),
			lastEditor: $user?->getName() ?? '',
			content: $content === null ? '' : $content->serialize(),
			contentModel: $content === null ? '' : $content->getModel(),
			parserProperties: $parserOutput === null ? [] : $parserOutput->getPageProperties(),
		);
	}

	private function parse( Content $content, RevisionRecord $revision ): ParserOutput {
		return $this->contentHandlerFactory->getContentHandler( $content->getModel() )
			->getParserOutput( $content, new ContentParseParams( $revision->getPage(), $revision->getId() ) );
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

}
