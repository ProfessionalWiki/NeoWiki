<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType
 */
class RelationTypeValidateTest extends TestCase {

	private RelationType $type;

	protected function setUp(): void {
		$this->type = new RelationType();
	}

	public function testOptionalAndEmptyReturnsNoViolations(): void {
		$violations = $this->type->validate( new RelationValue(), $this->newProperty( required: false ) );

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndEmptyReturnsRequiredViolation(): void {
		$violations = $this->type->validate( new RelationValue(), $this->newProperty( required: true ) );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testRequiredAndNonRelationValueReturnsRequired(): void {
		$violations = $this->type->validate( new NumberValue( 42 ), $this->newProperty( required: true ) );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testRequiredAndNonEmptyReturnsNoViolations(): void {
		$relation = new Relation(
			id: new RelationId( 'r123456789abcde' ),
			targetId: new SubjectId( 's12345678abcdef' ),
			properties: new RelationProperties( [] ),
		);

		$violations = $this->type->validate( new RelationValue( $relation ), $this->newProperty( required: true ) );

		$this->assertSame( [], $violations );
	}

	private function newProperty( bool $required ): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'relation' => 'has', 'targetSchema' => 'Person', 'multiple' => false ],
		);
	}

}
