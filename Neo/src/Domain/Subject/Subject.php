<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public readonly SubjectLabel $label,
		private readonly SchemaId $schemaId,
		private StatementList $statements,
	) {
	}

	public static function createNew(
		GuidGenerator $guidGenerator,
		SubjectLabel $label,
		SchemaId $schemaId,
		?StatementList $properties = null,
	): self {
		return new self(
			id: SubjectId::createNew( $guidGenerator ),
			label: $label,
			schemaId: $schemaId,
			statements: $properties ?? new StatementList( [] ),
		);
	}

	public static function newSubject( SubjectId $id, SubjectLabel $label, SchemaId $schemaId ): self {
		return new self(
			id: $id,
			label: $label,
			schemaId: $schemaId,
			statements: new StatementList( [] ),
		);
	}

	/**
	 * @param array<string, mixed> $patch Property name to list of new values
	 */
	public function applyPatch( array $patch ): void {
		$this->statements = $this->statements->applyPatch( $patch );
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

	public function getStatements(): StatementList {
		return $this->statements;
	}

	public function getRelations( Schema $readerSchema ): RelationList {
		return $this->statements->getRelations( $readerSchema );
	}

	public function getReferencedSubjects( Schema $readerSchema ): SubjectIdList {
		return $this->getRelations( $readerSchema )->getTargetIds();
	}

}
