<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

class SubjectMap implements \Countable {

	/**
	 * @var array<string, Subject>
	 */
	private array $subjects = [];

	public function __construct( Subject ...$subjects ) {
		foreach ( $subjects as $subject ) {
			$this->subjects[$subject->id->text] = $subject;
		}
	}

	public function getSubject( SubjectId $id ): ?Subject {
		return $this->subjects[$id->text] ?? null;
	}

	public function hasSubject( SubjectId $id ): bool {
		return array_key_exists( $id->text, $this->subjects );
	}

	/**
	 * @return array<int, Subject>
	 */
	public function asArray(): array {
		return array_values( $this->subjects );
	}

	public function append( self $subjectMap ): self {
		$subjects = $this->subjects;

		foreach ( $subjectMap->subjects as $subject ) {
			$subjects[$subject->id->text] = $subject;
		}

		return new self( ...$subjects );
	}

	public function addOrUpdateSubject( Subject $subject ): void {
		$this->subjects[$subject->id->text] = $subject;
	}

	/**
	 * @return string[]
	 */
	public function getIdsAsTextArray(): array {
		return array_keys( $this->subjects );
	}

	public function prepend( ?Subject $subject ): self {
		$subjects = $this->subjects;

		if ( $subject !== null ) {
			$subjects = [ $subject ] + $subjects;
		}

		return new self( ...$subjects );
	}

	public function without( SubjectId $id ): self {
		$subjects = $this->subjects;

		if ( array_key_exists( $id->text, $subjects ) ) {
			unset( $subjects[$id->text] );
		}

		return new self( ...$subjects );
	}

	public function isEmpty(): bool {
		return empty( $this->subjects );
	}

	public function count(): int {
		return count( $this->subjects );
	}

}
