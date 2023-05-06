<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationTypeId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * @see \ProfessionalWiki\NeoWiki\Domain\Relation\Relation
 */
class TestRelation {

	public static function build(
		?string $type = null,
		?string $targetId = null,
		?RelationProperties $properties = null
	): Relation {
		return new Relation(
			new RelationTypeId( $type ?? 'FriendOf' ),
			new SubjectId( $targetId ?? '130A09E1-54FF-4A20-8A14-4E62D700BBBB' ),
			$properties ?? new RelationProperties( [] )
		);
	}

}
