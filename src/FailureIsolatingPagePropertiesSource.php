<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use Exception;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Wraps the page-properties build so a page MediaWiki can no longer fully handle -- one whose content
 * model an uninstalled extension owned, or one a throwing Page Property Provider cannot answer for --
 * is logged rather than failing the projection that follows an edit, import or undeletion.
 *
 * Which throwables are let through matches FailureIsolatingGraphDatabasePlugin, for the same reasons:
 * \Error is not caught at all, and TimeoutException and DBError are re-thrown.
 *
 * Used on the projections that follow an operation on a page. The RebuildGraphDatabases maintenance path deliberately
 * runs the undecorated PagePropertiesBuilder, so a page it cannot reconcile is reported as a failure
 * naming its cause rather than as a skip the operator cannot act on. See NeoWikiExtension.
 */
class FailureIsolatingPagePropertiesSource implements PagePropertiesSource {

	public function __construct(
		private readonly PagePropertiesSource $source,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getPagePropertiesFor( RevisionRecord $revision, ?ParserOutput $parserOutput ): ?PageProperties {
		try {
			return $this->source->getPagePropertiesFor( $revision, $parserOutput );
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
