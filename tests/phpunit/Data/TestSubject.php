<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionIdGenerator;

class TestSubject {

	public const string ZERO_GUID = 's11111111111111'; // TODO: rename
	public const string DEFAULT_SCHEMA_ID = 'TestSubjectSchemaId';

	public static function build(
		string|SubjectId $id = self::ZERO_GUID,
		SubjectLabel|string $label = 'Test subject',
		?SchemaName $schemaName = null,
		?StatementList $statements = null,
	): Subject {
		return new Subject(
			id: $id instanceof SubjectId ? $id : new SubjectId( $id ),
			label: $label instanceof SubjectLabel ? $label : new SubjectLabel( $label ),
			schemaName: $schemaName ?? new SchemaName( self::DEFAULT_SCHEMA_ID ),
			statements: $statements ?? new StatementList( [] ),
		);
	}

	public static function newMap(): SubjectMap {
		return new SubjectMap(
			self::build(
				id: 's1zz1111111azz1',
				label: new SubjectLabel( 'Test subject azz1' ),
			),
			self::build(
				id: 's1zz1111111azz2',
				label: new SubjectLabel( 'Test subject azz2' ),
			),
			self::build(
				id: 's1zz1111111azz3',
				label: new SubjectLabel( 'Test subject azz3' ),
			)
		);
	}

	/**
	 * Generates a new GUID
	 */
	public static function uniqueId(): SubjectId {
		return SubjectId::createNew( new ProductionIdGenerator() );
	}

}
