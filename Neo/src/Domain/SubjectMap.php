<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class SubjectMap {

	/**
	 * @var array<string, Subject>
	 */
	private $subjects = [];

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

}
