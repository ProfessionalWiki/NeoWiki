<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentDataDeserializer {

	public function __construct(
		private readonly StatementDeserializer $statementDeserializer,
	) {
	}

	/**
	 * Mirrors @see SubjectContentDataSerializer::serialize
	 */
	public function deserialize( string $json ): PageSubjects {
		$jsonArray = json_decode( $json, true );
		$subjects = $this->deserializeSubjects( $jsonArray );

		if ( ( $jsonArray['mainSubject'] ?? null ) === null ) {
			return new PageSubjects(
				null,
				$subjects,
			);
		}

		$mainSubjectId = new SubjectId( $jsonArray['mainSubject'] );

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
			id: new SubjectId( $id ),
			label: new SubjectLabel( $jsonArray['label'] ),
			schemaName: new SchemaName( $jsonArray['schema'] ),
			statements: $this->buildStatementList( $jsonArray ),
		);
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
		return array_values( array_filter(
			array_map( 'strval', array_keys( $subjects ) ),
			SubjectId::isValid( ... )
		) );
	}

}
