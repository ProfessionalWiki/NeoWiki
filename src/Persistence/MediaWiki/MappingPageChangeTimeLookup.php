<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\MWTimestamp;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\ProjectionChangeTimeLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Reads a projection's last change off the Mapping page that defines it.
 *
 * Deleting that page is as much a change as editing it: every page projected under it still carries the
 * vocabulary it described, and nothing reprojects them. The page is gone by then, so when there is no
 * live revision the deletion log says when.
 *
 * A projection whose Mapping page never existed reads as never changed rather than as changed just now:
 * a store configured for a projection nothing has ever defined is a misconfiguration, and reporting it
 * as perpetually out of date would say nothing an operator could act on.
 */
class MappingPageChangeTimeLookup implements ProjectionChangeTimeLookup {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly RevisionLookup $revisionLookup,
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getLastChangeTime( string $projection ): ?string {
		$title = $this->titleFactory->newFromText( $projection, NeoWikiExtension::NS_MAPPING );

		if ( $title === null ) {
			return null;
		}

		$revisionTime = $this->getLastContentChangeTime( $title );
		$logTime = $this->getLastDeletionOrRestorationTime( $title );

		if ( $revisionTime === null || $logTime === null ) {
			return $revisionTime ?? $logTime;
		}

		// Both are TS_MW, which sorts as it reads.
		return max( $revisionTime, $logTime );
	}

	/**
	 * When the page's content last changed. Protecting or unprotecting a page inserts a revision
	 * carrying the content of the one before it, with a fresh timestamp — read as a change, that
	 * reports every store holding the projection as stale over an action that changed nothing. Walked
	 * past by content hash; in practice at most a few protection entries deep.
	 */
	private function getLastContentChangeTime( Title $title ): ?string {
		$revision = $this->revisionLookup->getRevisionByTitle( $title );

		while ( $revision !== null ) {
			$parentId = $revision->getParentId();
			$parent = ( $parentId === null || $parentId === 0 )
				? null
				: $this->revisionLookup->getRevisionById( $parentId );

			if ( $parent === null || $parent->getSha1() !== $revision->getSha1() ) {
				return $revision->getTimestamp();
			}

			$revision = $parent;
		}

		return null;
	}

	/**
	 * The most recent time the page was deleted or put back. Most recent, because a page can be deleted,
	 * restored and deleted again.
	 *
	 * Restoring counts because a restored revision keeps its original timestamp: read off the page alone,
	 * a projection put back after its stores were rebuilt without it looks untouched since before the
	 * deletion, and the stores that no longer describe it report as in sync.
	 */
	private function getLastDeletionOrRestorationTime( Title $title ): ?string {
		$timestamp = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( 'MAX(log_timestamp)' )
			->from( 'logging' )
			->where( [
				'log_type' => 'delete',
				'log_action' => [ 'delete', 'restore' ],
				'log_namespace' => $title->getNamespace(),
				'log_title' => $title->getDBkey(),
			] )
			->caller( __METHOD__ )
			->fetchField();

		if ( !is_string( $timestamp ) ) {
			return null;
		}

		$converted = MWTimestamp::convert( TS_MW, $timestamp );

		return $converted === false ? null : $converted;
	}

}
