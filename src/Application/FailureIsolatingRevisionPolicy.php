<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Holds a registered policy to what NeoWiki can accept from one, and fails closed when it cannot.
 *
 * A policy that throws is treated as publishing nothing and hiding everything: the edit hook it runs
 * on sits inside PageUpdater's atomic section, so a throw there would roll the contributor's save
 * back, and a throw on a read would take every Schema, Layout and Mapping read down with it. The other
 * extension-contributed plugins are wrapped the same way; see FailureIsolatingGraphDatabasePlugin.
 *
 * Two answers are refused as well as caught. A revision of another page: every caller keys its write
 * or its export on the returned revision's page id, so accepting one would publish page B under page
 * A's name, behind A's read gate. And a revision whose text is suppressed: core refuses to suppress a
 * page's current revision, so only a policy naming an older one could publish suppressed Subjects, and
 * this refuses to.
 */
class FailureIsolatingRevisionPolicy implements RevisionPolicy {

	public function __construct(
		private readonly RevisionPolicy $policy,
		private readonly LoggerInterface $logger,
	) {
	}

	public function publishesRevision( RevisionRecord $revision ): bool {
		if ( $revision->isDeleted( RevisionRecord::DELETED_TEXT ) ) {
			return false;
		}

		try {
			return $this->policy->publishesRevision( $revision );
		} catch ( Throwable $exception ) {
			$this->logFailure( 'publishesRevision', $revision, $exception );
			return false;
		}
	}

	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
		try {
			$published = $this->policy->publishedRevision( $revision );
		} catch ( Throwable $exception ) {
			$this->logFailure( 'publishedRevision', $revision, $exception );
			return null;
		}

		if ( $published === null ) {
			return null;
		}

		if ( $published->getPageId() !== $revision->getPageId() ) {
			$this->logger->error(
				'The revision policy {class} named revision {published} of page {publishedPage} for page {page}; '
				. 'a policy may only name a revision of the page it was asked about. Publishing nothing for it.',
				[
					'class' => $this->policy::class,
					'published' => $published->getId(),
					'publishedPage' => $published->getPageId(),
					'page' => $revision->getPageId(),
				]
			);
			return null;
		}

		if ( $published->isDeleted( RevisionRecord::DELETED_TEXT ) ) {
			return null;
		}

		return $published;
	}

	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
		try {
			return $this->policy->revisionIsReadableBy( $revision, $viewer );
		} catch ( Throwable $exception ) {
			$this->logFailure( 'revisionIsReadableBy', $revision, $exception );
			return false;
		}
	}

	private function logFailure( string $method, RevisionRecord $revision, Throwable $exception ): void {
		$this->logger->error(
			'The revision policy {class} threw from {method} for revision {revision} of page {page}; '
			. 'treating the page as publishing nothing. {message}',
			[
				'class' => $this->policy::class,
				'method' => $method,
				'revision' => $revision->getId(),
				'page' => $revision->getPageId(),
				'message' => $exception->getMessage(),
				'exception' => $exception,
			]
		);
	}

}
