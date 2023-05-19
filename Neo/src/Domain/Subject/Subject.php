<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public readonly SubjectLabel $label,
		private readonly SchemaId $schemaId,
		private SubjectProperties $properties,
		private readonly RelationList $relations, // TODO: "same as" identifiers?
	) {
	}

	public static function createNew(
		GuidGenerator $guidGenerator,
		SubjectLabel $label,
		SchemaId $schemaId,
		?SubjectProperties $properties = null,
		?RelationList $relations = null,
	): self {
		return new self(
			id: SubjectId::createNew( $guidGenerator ),
			label: $label,
			schemaId: $schemaId,
			properties: $properties ?? new SubjectProperties( [] ),
			relations: $relations ?? new RelationList( [] ),
		);
	}

	public static function newSubject( SubjectId $id, SubjectLabel $label, SchemaId $schemaId ): self {
		return new self(
			id: $id,
			label: $label,
			schemaId: $schemaId,
			properties: new SubjectProperties( [] ),
			relations: new RelationList( [] ),
		);
	}

	/**
	 * @return string[]
	 */
	public function getRelationsAsIdStringArray(): array {
		return array_map(
			fn( Relation $relation ): string => $relation->targetId->text,
			$this->relations->relations
		);
	}

	/**
	 * @param array<string, array> $patch Property name to list of new values
	 */
	public function applyPatch( array $patch ): void {
		$this->properties = $this->properties->applyPatch( $patch );
	}

	public function hasSameIdentity( self $subject ): bool {
		return $this->id->equals( $subject->id );
	}

	public function getId(): SubjectId {
		return $this->id;
	}

	public function getLabel(): SubjectLabel {
		return $this->label;
	}

	public function getSchemaId(): SchemaId {
		return $this->schemaId;
	}

	public function getProperties(): SubjectProperties {
		return $this->properties;
	}

	public function getRelations(): RelationList {
		return $this->relations;
	}

	/**
	 * @return SubjectId[]
	 */
	public function getReferencedSubjects(): array {
		return $this->relations->getTargetIds();
	}

}
