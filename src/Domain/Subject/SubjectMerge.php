<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;

/**
 * Combines a write based on an earlier revision with what changed since: each label, Statement and Subject comes from
 * the side that changed it.
 */
final class SubjectMerge {

	private bool $changedOnBothSides = false;

	private function __construct() {
	}

	/**
	 * @param SubjectMap $base What the write overwrites, as the revision it was based on holds it.
	 * @param SubjectMap $latest The same, as the latest revision holds it.
	 * @param SubjectMap $proposed What the write sets.
	 * @return SubjectMap|null Null when both sides changed the same label or Statement differently, or one removed a
	 *  Subject the other changed.
	 */
	public static function merge( SubjectMap $base, SubjectMap $latest, SubjectMap $proposed ): ?SubjectMap {
		$merge = new self();
		$merged = $merge->mergeSubjects( $base, $latest, $proposed );

		return $merge->changedOnBothSides ? null : $merged;
	}

	private function mergeSubjects( SubjectMap $base, SubjectMap $latest, SubjectMap $proposed ): SubjectMap {
		$subjects = [];

		foreach ( $proposed->union( $latest )->union( $base )->getIds()->asArray() as $id ) {
			$latestSubject = $latest->getSubject( $id );
			$proposedSubject = $proposed->getSubject( $id );

			$subject = $latestSubject !== null && $proposedSubject !== null
				? $this->mergeFields( $base->getSubject( $id ), $latestSubject, $proposedSubject )
				: $this->pick( $base->getSubject( $id ), $latestSubject, $proposedSubject, self::sameSubject( ... ) );

			if ( $subject !== null ) {
				$subjects[] = $subject;
			}
		}

		return new SubjectMap( ...$subjects );
	}

	private function mergeFields( ?Subject $base, Subject $latest, Subject $proposed ): Subject {
		$statements = [];

		foreach ( self::propertyNamesIn( $proposed, $latest, $base ) as $propertyName ) {
			$statement = $this->pick(
				$base?->getStatements()->getStatement( $propertyName ),
				$latest->getStatements()->getStatement( $propertyName ),
				$proposed->getStatements()->getStatement( $propertyName ),
				self::sameStatement( ... )
			);

			if ( $statement !== null ) {
				$statements[] = $statement;
			}
		}

		return new Subject(
			id: $proposed->id,
			label: $this->pick( $base?->label, $latest->label, $proposed->label, self::sameLabel( ... ) ),
			schema: $proposed->getSchemaReference(),
			statements: new StatementList( $statements ),
		);
	}

	/**
	 * @return PropertyName[]
	 */
	private static function propertyNamesIn( ?Subject ...$subjects ): array {
		$names = [];

		foreach ( $subjects as $subject ) {
			foreach ( $subject?->getStatements()->asArray() ?? [] as $statement ) {
				$names[$statement->getPropertyName()->text] = $statement->getPropertyName();
			}
		}

		return array_values( $names );
	}

	/**
	 * @template T
	 * @param T|null $base
	 * @param T|null $latest
	 * @param T|null $proposed
	 * @param Closure(T, T): bool $same
	 * @return T|null
	 */
	private function pick( mixed $base, mixed $latest, mixed $proposed, Closure $same ): mixed {
		$equal = static fn ( mixed $a, mixed $b ): bool => $a === null || $b === null ? $a === $b : $same( $a, $b );

		if ( $equal( $latest, $proposed ) || $equal( $base, $latest ) ) {
			return $proposed;
		}

		if ( $equal( $base, $proposed ) ) {
			return $latest;
		}

		$this->changedOnBothSides = true;
		return $proposed;
	}

	private static function sameSubject( Subject $a, Subject $b ): bool {
		return $a->hasSameContentAs( $b );
	}

	private static function sameStatement( Statement $a, Statement $b ): bool {
		return $a->equals( $b );
	}

	private static function sameLabel( SubjectLabel $a, SubjectLabel $b ): bool {
		return $a->text === $b->text;
	}

}
