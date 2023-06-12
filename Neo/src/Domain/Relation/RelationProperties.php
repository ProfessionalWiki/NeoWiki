<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

use InvalidArgumentException;

class RelationProperties {

	public function __construct(
		/**
		 * @var array<string, mixed>
		 */
		public readonly array $map,
	) {
		foreach ( $this->map as $key => $value ) {
			if ( is_array( $value ) ) {
				throw new InvalidArgumentException( "Relation property $key cannot be an array" );
			}
		}
	}

}
