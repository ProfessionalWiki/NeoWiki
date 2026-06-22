<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use Countable;
use InvalidArgumentException;

class SubjectMap implements Countable {

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

	public function addOrUpdateSubject( Subject $subject ): void {
		$this->subjects[$subject->id->text] = $subject;
	}

	public function union( self $subjectMap ): self {
		$subjects = $this->subjects;

		foreach ( $subjectMap->subjects as $subject ) {
			$subjects[$subject->id->text] = $subject;
		}

		return new self( ...$subjects );
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

	/**
	 * @param SubjectId[] $idsInOrder
	 * @throws InvalidArgumentException if $idsInOrder does not match this map's ids exactly (no missing, extra, or duplicate ids)
	 */
	public function withOrdering( array $idsInOrder ): self {
		$reordered = [];

		foreach ( $idsInOrder as $id ) {
			if ( array_key_exists( $id->text, $reordered ) ) {
				throw new InvalidArgumentException( 'Duplicate subject id in ordering: ' . $id->text );
			}
			if ( !array_key_exists( $id->text, $this->subjects ) ) {
				throw new InvalidArgumentException( 'Unknown subject id in ordering: ' . $id->text );
			}
			$reordered[$id->text] = $this->subjects[$id->text];
		}

		if ( count( $reordered ) !== count( $this->subjects ) ) {
			throw new InvalidArgumentException( 'Ordering does not include every subject in the map' );
		}

		return new self( ...$reordered );
	}

	public function isEmpty(): bool {
		return empty( $this->subjects );
	}

	public function count(): int {
		return count( $this->subjects );
	}

}
