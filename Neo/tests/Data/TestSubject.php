<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionGuidGenerator;

class TestSubject {

	public const ZERO_GUID = '00000000-0000-0000-0000-000000000000';
	public const DEFAULT_SCHEMA_ID = 'TestSubjectSchemaId';

	public static function build(
		string|SubjectId $id = self::ZERO_GUID,
		SubjectLabel|string $label = 'Test subject',
		?SchemaName $schemaId = null,
		?StatementList $properties = null,
	): Subject {
		return new Subject(
			id: $id instanceof SubjectId ? $id : new SubjectId( $id ),
			label: $label instanceof SubjectLabel ? $label : new SubjectLabel( $label ),
			schemaId: $schemaId ?? new SchemaName( self::DEFAULT_SCHEMA_ID ),
			statements: $properties ?? new StatementList( [] ),
		);
	}

	public static function newMap(): SubjectMap {
		return new SubjectMap(
			self::build(
				id: '70ba6d09-4ca4-4f2a-93e4-4f4f9c48a001',
				label: new SubjectLabel( 'Test subject a001' ),
			),
			self::build(
				id: '93e58a18-dc3e-41aa-8d67-79a18e98b002',
				label: new SubjectLabel( 'Test subject b002' ),
			),
			self::build(
				id: '9d6b4927-0c04-41b3-8daa-3b1d83f4c003',
				label: new SubjectLabel( 'Test subject c003' ),
			)
		);
	}

	/**
	 * Generates a new GUID
	 */
	public static function uniqueId(): SubjectId {
		return SubjectId::createNew( new ProductionGuidGenerator() );
	}

}
