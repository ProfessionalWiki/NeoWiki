<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

class RelationList {

	public function __construct(
		/**
		 * @var Relation[]
		 */
		public readonly array $relations
	) {
	}

}
