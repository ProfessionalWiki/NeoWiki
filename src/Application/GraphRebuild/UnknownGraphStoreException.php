<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use RuntimeException;

/**
 * A rebuild was asked for a store this wiki has not configured.
 */
class UnknownGraphStoreException extends RuntimeException {

	/**
	 * @param string[] $knownStores
	 */
	public function __construct( public readonly string $store, public readonly array $knownStores ) {
		parent::__construct(
			'Unknown graph store "' . $store . '". Configured stores: '
			. ( $knownStores === [] ? '(none)' : implode( ', ', $knownStores ) ) . '.'
		);
	}

}
