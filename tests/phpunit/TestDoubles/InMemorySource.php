<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Source\Source;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class InMemorySource implements Source {

	/**
	 * @var array<string, Subject>
	 */
	private array $subjects = [];

	/**
	 * @var array<string, Schema>
	 */
	private array $schemas = [];

	public int $getSubjectsCallCount = 0;

	public function __construct( Subject ...$subjects ) {
		foreach ( $subjects as $subject ) {
			$this->subjects[$subject->id->text] = $subject;
		}
	}

	public function addSchema( Schema $schema ): void {
		$this->schemas[$schema->getName()->getText()] = $schema;
	}

	public function getSubject( SubjectId $id ): ?Subject {
		return $this->subjects[$id->text] ?? null;
	}

	public function getSubjects( SubjectIdList $ids ): SubjectMap {
		$this->getSubjectsCallCount++;

		$found = new SubjectMap();

		foreach ( $ids->asStringArray() as $idText ) {
			if ( array_key_exists( $idText, $this->subjects ) ) {
				$found->addOrUpdateSubject( $this->subjects[$idText] );
			}
		}

		return $found;
	}

	public function getSchema( SchemaName $name ): ?Schema {
		return $this->schemas[$name->getText()] ?? null;
	}

	public function isEditable(): bool {
		return false;
	}

	public function isValidLocalId( string $localId ): bool {
		return $localId !== '';
	}

	public function getBaseUri(): string {
		return 'https://example.org/entity/';
	}

}
