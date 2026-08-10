<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices;

use ProfessionalWiki\NeoWiki\Application\EditNotice\SubjectEditNoticeContextFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeProviderRegistry;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

readonly class GetSubjectEditNoticesQuery {

	public function __construct(
		private GetSubjectEditNoticesPresenter $presenter,
		private SubjectEditNoticeProviderRegistry $registry,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectEditNoticeContextFactory $contextFactory,
	) {
	}

	public function execute( int $pageId, ?string $schemaName ): void {
		$context = $this->resolveContext( new PageId( $pageId ), $schemaName );

		$this->presenter->presentNotices(
			$context === null ? [] : $this->collectNotices( $context )
		);
	}

	/**
	 * A denied page takes exactly the path a page without notices takes, so the response is
	 * byte-identical to absence and cannot be used to probe page readability (#1046).
	 */
	private function resolveContext( PageId $pageId, ?string $schemaName ): ?SubjectEditNoticeContext {
		if ( !$this->readAuthorizer->authorizeReadByPageId( $pageId ) ) {
			return null;
		}

		return $this->contextFactory->newContext( $pageId, $schemaName );
	}

	/**
	 * @return SubjectEditNotice[]
	 */
	private function collectNotices( SubjectEditNoticeContext $context ): array {
		$notices = [];

		foreach ( $this->registry->getProviders() as $provider ) {
			foreach ( $provider->getNotices( $context ) as $notice ) {
				$notices[] = $notice;
			}
		}

		return $notices;
	}

}
