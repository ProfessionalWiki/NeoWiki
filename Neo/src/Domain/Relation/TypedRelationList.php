<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

readonly class TypedRelationList {

	public function __construct(
		/**
		 * @var TypedRelation[]
		 */
		public array $relations
	) {
		foreach ( $relations as $relation ) {
			if ( !( $relation instanceof TypedRelation ) ) {
				throw new \InvalidArgumentException( 'All relations must be of type TypedRelation' );
			}
		}
	}

	/**
	 * @return string[]
	 */
	public function getIdsAsStringArray(): array {
		return array_map(
			fn( TypedRelation $relation ): string => $relation->id->asString(),
			$this->relations
		);
	}

}
