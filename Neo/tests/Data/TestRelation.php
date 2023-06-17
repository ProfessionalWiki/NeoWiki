<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * @see \ProfessionalWiki\NeoWiki\Domain\Relation\Relation
 */
class TestRelation {

	public static function build(
		string|RelationId $id = null,
		?string $type = null,
		?string $targetId = null,
		?RelationProperties $properties = null
	): Relation {
		return new Relation(
			self::defaultId( $id ),
			new RelationType( $type ?? 'FriendOf' ),
			new SubjectId( $targetId ?? '130A09E1-54FF-4A20-8A14-4E62D700BBBB' ),
			$properties ?? new RelationProperties( [] )
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
		static $counter = 0;
		$suffix = str_pad( (string)++$counter, 12, '0', STR_PAD_LEFT );
		return new RelationId( "00000000-1111-2222-1111-$suffix" );
	}

}
