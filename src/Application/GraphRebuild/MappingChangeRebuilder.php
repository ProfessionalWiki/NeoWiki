<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Rebuilds the graph stores a changed Mapping defines the contents of.
 *
 * A Mapping page describes what every page's graph should contain in that vocabulary, and editing it
 * reprojects nothing: the stores holding that projection keep serving the old vocabulary until something
 * walks the wiki again. This is that something, for wikis that have asked for it.
 *
 * A store already rebuilding automatically is restarted rather than left to finish, because a run that
 * began under the old Mapping has projected part of the wiki under rules that no longer apply. Restarting
 * converges; letting it finish would leave the store half in each vocabulary with nothing recording that
 * it had. A rebuild somebody started is left alone instead — see
 * {@see GraphRebuildCoordinator::restartBackground()}.
 */
class MappingChangeRebuilder {

	/**
	 * @param array<string, ?string> $projectionsByStore Keys are store names; the value is the RDF
	 *        vocabulary that store holds, normalised as a Mapping page name
	 */
	public function __construct(
		private readonly array $projectionsByStore,
		private readonly GraphRebuildCoordinator $coordinator,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * The stores whose projection this Mapping page defines. Each of them has to be started in a
	 * transaction round of its own: starting one takes a database lock that flushes the connection's
	 * snapshot, which a connection still holding the previous store's writes may not do.
	 *
	 * @param string $mappingName The name of the Mapping page that was saved or deleted, normalised.
	 *
	 * @return string[] Store names
	 */
	public function storesHoldingProjection( string $mappingName ): array {
		$stores = [];

		foreach ( $this->projectionsByStore as $storeName => $projection ) {
			if ( $projection === $mappingName ) {
				$stores[] = (string)$storeName;
			}
		}

		return $stores;
	}

	public function onMappingChanged( string $storeName ): void {
		$this->restartRebuildOf( $storeName );
	}

	/**
	 * Nothing is thrown out of here: this runs off a page having been saved or deleted, and that has
	 * already happened. A store that could not be rebuilt is reported rather than allowed to take the
	 * edit down with it, and Special:GraphStores still shows it as stale.
	 */
	private function restartRebuildOf( string $storeName ): void {
		try {
			$this->coordinator->restartBackground( $storeName, RebuildTrigger::Auto );
		} catch ( RebuildAlreadyRunningException ) {
			$this->reportRebuildLeftAlone( $storeName );
		} catch ( Throwable $e ) {
			$this->logger->error(
				'NeoWiki could not rebuild graph store "' . $storeName . '" after the Mapping defining its '
				. 'projection changed, so the store still holds the old vocabulary. Rebuild it from '
				. 'Special:GraphStores. Underlying error: '
				. BackendFailureMessage::withoutCredentials( $e->getMessage() ),
				[ 'exception' => $e, 'store' => $storeName ]
			);
		}
	}

	/**
	 * Not a failure: somebody is waiting on that rebuild, and taking it away to start their wait over is
	 * not something an edit to a Mapping page should be able to do.
	 */
	private function reportRebuildLeftAlone( string $storeName ): void {
		$this->logger->info(
			'NeoWiki left the rebuild graph store "' . $storeName . '" already had going to finish, rather '
			. 'than restarting it for the Mapping that changed. It was started by hand, so somebody is '
			. 'waiting on it. The store is reported as stale on Special:GraphStores once that run ends, and '
			. 'rebuilding it from there picks up the new Mapping.',
			[ 'store' => $storeName ]
		);
	}

}
