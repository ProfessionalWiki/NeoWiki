<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use Exception;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Wraps the page-properties build so a page MediaWiki can no longer fully handle -- one whose content
 * model an uninstalled extension owned, or one a throwing Page Property Provider cannot answer for --
 * does not abort the edit, import or undeletion that triggered the projection.
 *
 * Which throwables are let through matches FailureIsolatingGraphDatabasePlugin, for the same reasons:
 * \Error is not caught at all, and TimeoutException and DBError are re-thrown.
 *
 * Used only on the hook-facing write path. The RebuildGraphDatabases maintenance path deliberately
 * runs the undecorated PagePropertiesBuilder, so a page it cannot reconcile is reported as a failure
 * naming its cause rather than as a skip the operator cannot act on. See NeoWikiExtension.
 */
class FailureIsolatingPagePropertiesSource implements PagePropertiesSource {

	public function __construct(
		private readonly PagePropertiesSource $source,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getPagePropertiesFor( RevisionRecord $revision, ?UserIdentity $user ): ?PageProperties {
		try {
			return $this->source->getPagePropertiesFor( $revision, $user );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logger->error(
				'NeoWiki did not project page {pageId} because its page properties could not be built: '
				. '{message}. The graph is out of sync for that page until the cause is resolved.',
				[
					'pageId' => $revision->getPageId(),
					'message' => BackendFailureMessage::withoutCredentials( $e->getMessage() ),
					'exception' => $e,
				]
			);

			return null;
		}
	}

}
