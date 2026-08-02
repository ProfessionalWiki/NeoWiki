<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

/**
 * How far a graph store's contents are from what the wiki now says they should be.
 *
 * Derived from the run records and the wiki, never stored: anything stored would be one more thing to
 * keep true, and would be wrong exactly when it mattered.
 */
enum StoreSyncState: string {

	/**
	 * No rebuild of this store has ever reconciled the whole wiki, so what it holds is whatever the
	 * per-edit projection has put there since it was configured — nothing at all, for a store added to a
	 * wiki that already had pages.
	 */
	case NeverBuilt = 'never-built';

	/**
	 * The Mapping that defines the store's projection has been edited since the last rebuild finished, so
	 * every page projected before that edit is described by the old vocabulary.
	 */
	case Stale = 'stale';

	case InSync = 'in-sync';

}
