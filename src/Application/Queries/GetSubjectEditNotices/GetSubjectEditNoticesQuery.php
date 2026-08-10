<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices;

use ProfessionalWiki\NeoWiki\Application\EditNotice\SubjectEditNoticeContextFactory;
use ProfessionalWiki\NeoWiki\Application\EditNotice\SubjectEditNoticeEnvironment;
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
		private SubjectEditNoticeEnvironment $environment,
	) {
	}

	public function execute( int $pageId, ?string $schemaName ): void {
		$context = $this->resolveContext( new PageId( $pageId ), $schemaName );

		if ( $context === null ) {
			$this->presenter->presentNotices( [] );
			return;
		}

		$this->environment->prepareFor( $context );

		$this->presenter->presentNotices( $this->collectNotices( $context ) );
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

		// Keyed while collecting, so a key claimed twice yields one notice rather than a duplicate a
		// client would render twice. The first provider to claim a key keeps it.
		foreach ( $this->registry->getProviders() as $provider ) {
			foreach ( $provider->getNotices( $context ) as $notice ) {
				$notices[$notice->key] ??= $notice;
			}
		}

		return array_values( $notices );
	}

}
