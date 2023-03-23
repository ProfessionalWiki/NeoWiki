<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public readonly SubjectLabel $label,
		public readonly SubjectTypeIdList $types,
		// TODO: "same as" identifiers?
		public readonly RelationList $relations,
		public readonly SubjectProperties $properties,
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

}
