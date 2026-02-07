<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public SubjectLabel $label,
		private readonly SchemaName $schemaName,
		private StatementList $statements,
	) {
	}

	public static function createNew(
		IdGenerator $guidGenerator,
		SubjectLabel $label,
		SchemaName $schemaName,
		?StatementList $statements = null,
	): self {
		return new self(
			id: SubjectId::createNew( $guidGenerator ),
			label: $label,
			schemaName: $schemaName,
			statements: $statements ?? new StatementList( [] ),
		);
	}

	public static function newSubject( SubjectId $id, SubjectLabel $label, SchemaName $schemaName ): self {
		return new self(
			id: $id,
			label: $label,
			schemaName: $schemaName,
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

	public function getSchemaName(): SchemaName {
		return $this->schemaName;
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

	public function patchStatements( StatementListPatcher $patcher, array $patch ): void {
		$this->statements = $patcher->buildStatementList(
			statements: $this->statements,
			patch: $patch,
		);
	}

}
