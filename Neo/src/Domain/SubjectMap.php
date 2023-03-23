<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class SubjectMap {

	/**
	 * @var array<string, Subject>
	 */
	private array $subjects = [];

	public function __construct( Subject ...$subjects ) {
		foreach ( $subjects as $subject ) {
			$this->subjects[$subject->id->text] = $subject;
		}
	}

	public function getSubject( SubjectId $id ): Subject {
		return $this->subjects[$id->text];
	}

	/**
	 * @return array<int, Subject>
	 */
	public function asArray(): array {
		return array_values( $this->subjects );
	}

	public function append( self $subjectMap ): void {
		foreach ( $subjectMap->asArray() as $subject ) {
			$this->subjects[$subject->id->text] = $subject;
		}
	}

	/**
	 * @return string[]
	 */
	public function getIdsAsTextArray(): array {
		return array_keys( $this->subjects );
	}

}
