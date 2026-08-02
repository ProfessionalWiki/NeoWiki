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

		return $this->revisionLookup->getRevisionByTitle( $title )?->getTimestamp()
			?? $this->getDeletionTime( $title );
	}

	/**
	 * The most recent time the page was deleted. Most recent, because a page can be deleted, restored and
	 * deleted again, and only a page that is gone now gets this far.
	 */
	private function getDeletionTime( Title $title ): ?string {
		$timestamp = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( 'MAX(log_timestamp)' )
			->from( 'logging' )
			->where( [
				'log_type' => 'delete',
				'log_action' => 'delete',
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
