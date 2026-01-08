<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\IncrementalIdGenerator;

/**
 * @see \ProfessionalWiki\NeoWiki\Domain\Relation\Relation
 */
class TestRelation {

	public const string DEFAULT_TARGET_ID = 'srt555555555555';

	public static function build(
		string|RelationId $id = null,
		string $targetId = self::DEFAULT_TARGET_ID, // TODO: also generate predictable IDs
		array|RelationProperties $properties = []
	): Relation {
		return new Relation(
			self::defaultId( $id ),
			new SubjectId( $targetId ),
			is_array( $properties ) ? new RelationProperties( $properties ) : $properties
		);
	}

	private static function defaultId( string|RelationId|null $id ): RelationId {
		if ( $id === null ) {
			return self::newUniqueId();
		}

		if ( is_string( $id ) ) {
			return new RelationId( $id );
		}

		return $id;
	}

	public static function newUniqueId(): RelationId {
		static $generator = new IncrementalIdGenerator();
		return RelationId::createNew( $generator );
	}

}
