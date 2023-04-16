<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectTypeIdList;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionGuidGenerator;

class TestSubject {

	public const ZERO_GUID = '00000000-0000-0000-0000-000000000000';

	public static function build(
		string|SubjectId|null $id = null,
		?SubjectLabel $label = null,
		?SubjectTypeIdList $types = null,
		?RelationList $relations = null,
		?SubjectProperties $properties = null,
	): Subject {
		return new Subject(
			id: $id instanceof SubjectId ? $id : new SubjectId( $id ?? self::ZERO_GUID ),
			label: $label ?? new SubjectLabel( "Test subject" ),
			types: $types ?? new SubjectTypeIdList( [] ),
			properties: $properties ?? new SubjectProperties( [] ),
			relations: $relations ?? new RelationList( [] ),
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
