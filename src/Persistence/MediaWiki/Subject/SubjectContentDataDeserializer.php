<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentDataDeserializer {

	public function __construct(
		private readonly StatementDeserializer $statementDeserializer,
		private readonly SubjectIdParser $subjectIdParser,
	) {
	}

	/**
	 * Mirrors @see SubjectContentDataSerializer::serialize
	 */
	public function deserialize( string $json ): PageSubjects {
		$jsonArray = json_decode( $json, true );
		$subjects = $this->deserializeSubjects( $jsonArray );

		$mainSubject = $jsonArray['mainSubject'] ?? null;

		if ( !is_string( $mainSubject ) ) {
			return new PageSubjects(
				null,
				$subjects,
			);
		}

		$mainSubjectId = $this->subjectIdParser->parseOrThrow( $mainSubject );

		return new PageSubjects(
			$subjects->getSubject( $mainSubjectId ),
			$subjects->without( $mainSubjectId ),
		);
	}

	private function deserializeSubjects( array $jsonArray ): SubjectMap {
		$subjectsArray = $jsonArray['subjects'] ?? [];

		return new SubjectMap(
			...array_map(
				fn( string $id, array $subject ) => $this->deserializeSubject( $id, $subject ),
				array_keys( $subjectsArray ),
				$subjectsArray,
			)
		);
	}

	private function deserializeSubject( string $id, array $jsonArray ): Subject {
		return new Subject(
			id: $this->subjectIdParser->parseOrThrow( $id ),
			label: $this->deserializeLabel( $jsonArray['label'] ?? null ),
			schema: SchemaReference::fromJson( $jsonArray['schema'], $this->subjectIdParser->getLocalSourceKey() ),
			statements: $this->buildStatementList( $jsonArray ),
		);
	}

	private function deserializeLabel( mixed $label ): ?SubjectLabel {
		return is_string( $label ) ? SubjectLabel::fromText( $label ) : null;
	}

	private function buildStatementList( array $jsonArray ): StatementList {
		$statements = [];

		foreach ( $jsonArray['statements'] ?? [] as $propertyName => $value ) {
			if ( $value !== null ) {
				// A property named like a decimal integer comes back from json_decode as an int key.
				$statements[] = $this->statementDeserializer->deserialize( (string)$propertyName, $value );
			}
		}

		return new StatementList( $statements );
	}

	/**
	 * The ids the JSON holds, read without deserializing, so that a Subject too broken to deserialize
	 * still has an id. Ids no caller could ask about are left out, since nothing can be answered with
	 * them.
	 *
	 * Static and dependency-free: update.php rebuilds the subject -> page index on wikis whose NeoWiki
	 * configuration is not readable yet.
	 *
	 * @return string[]
	 */
	public static function deserializeSubjectIds( string $json ): array {
		// Cast so that content that is not a JSON object becomes an array without the key, leaving one
		// thing to check.
		$subjects = ( (array)json_decode( $json, true ) )['subjects'] ?? null;

		if ( !is_array( $subjects ) ) {
			return [];
		}

		// A Subject id that looks like a decimal integer comes back from json_decode as an int key.
		// The local Source's grammar, not the qualified one: the subject-to-page index keys ids bare
		// (ADR 32), and a Subject from another Source is not stored in a local slot. A qualified id is
		// also longer than the index column, so admitting one would truncate rather than record it.
		return array_values( array_filter(
			array_map( 'strval', array_keys( $subjects ) ),
			SubjectId::isValidLocalId( ... )
		) );
	}

}
