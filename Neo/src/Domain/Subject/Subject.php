<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public readonly SubjectLabel $label,
		public readonly SubjectTypeIdList $types,
		// TODO: "same as" identifiers?
		public readonly RelationList $relations,
		private SubjectProperties $properties,
	) {
	}

	public static function newSubject( SubjectId $id, SubjectLabel $label ): self {
		return new self(
			id: $id,
			label: $label,
			types: new SubjectTypeIdList( [] ),
			relations: new RelationList( [] ),
			properties: new SubjectProperties( [] ),
		);
	}

	/**
	 * @return string[]
	 */
	public function getRelationsAsIdStringArray(): array {
		return array_map(
			fn( Relation $relation ): string => $relation->id->text,
			$this->relations->relations
		);
	}

	/**
	 * @param array<string, array> $patch Property name to list of new values
	 */
	public function applyPatch( array $patch ): void {
		$this->properties = $this->properties->applyPatch( $patch );
	}

	public function getProperties(): SubjectProperties {
		return $this->properties;
	}

	public function hasSameIdentity( self $subject ): bool {
		return $this->id->equals( $subject->id );
	}

}
