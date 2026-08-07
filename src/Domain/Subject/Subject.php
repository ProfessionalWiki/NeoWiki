<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public SubjectLabel $label,
		private readonly SchemaReference $schema,
		private StatementList $statements,
	) {
	}

	public static function createNew(
		IdGenerator $idGenerator,
		SubjectLabel $label,
		SchemaReference $schema,
		?StatementList $statements = null,
	): self {
		return new self(
			id: SubjectId::createNew( $idGenerator ),
			label: $label,
			schema: $schema,
			statements: $statements ?? new StatementList( [] ),
		);
	}

	public static function newSubject( SubjectId $id, SubjectLabel $label, SchemaReference $schema ): self {
		return new self(
			id: $id,
			label: $label,
			schema: $schema,
			statements: new StatementList( [] ),
		);
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

	public function getSchemaReference(): SchemaReference {
		return $this->schema;
	}

	/**
	 * The Schema's name without its Source. Resolve a Schema through {@see self::getSchemaReference()};
	 * this is for the places that name a Schema locally, such as the graph's Schema node label.
	 */
	public function getSchemaName(): SchemaName {
		return $this->schema->name;
	}

	public function getStatements(): StatementList {
		return $this->statements;
	}

	public function getTypedRelations( Schema $readerSchema ): TypedRelationList {
		return $this->statements->getTypedRelations( $readerSchema );
	}

	public function getReferencedSubjects(): SubjectIdList {
		return $this->statements->getReferencedSubjects();
	}

	public function setLabel( SubjectLabel $newLabel ): void {
		$this->label = $newLabel;
	}

	public function setStatements( StatementList $statements ): void {
		$this->statements = $statements;
	}

}
