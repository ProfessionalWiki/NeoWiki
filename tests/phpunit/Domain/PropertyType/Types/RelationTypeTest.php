<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType
 */
class RelationTypeTest extends TestCase {

	public function testDisplayAttributeNamesIsEmpty(): void {
		$this->assertSame( [], ( new RelationType() )->getDisplayAttributeNames() );
	}

	public function testSingleValuePropertyWithTwoTargetsReturnsSingleValueOnly(): void {
		$violations = ( new RelationType() )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newRelationProperty( multiple: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'single-value-only', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testMultiValuePropertyWithTwoTargetsReturnsNoViolation(): void {
		$this->assertSame( [], ( new RelationType() )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newRelationProperty( multiple: true ),
		) );
	}

	public function testSingleValuePropertyWithOneTargetReturnsNoViolation(): void {
		$this->assertSame( [], ( new RelationType() )->validate(
			new RelationValue( TestRelation::build( targetId: 'srt111111111aaa' ) ),
			$this->newRelationProperty( multiple: false ),
		) );
	}

	public function testRequiredPropertyWithoutTargetsReturnsRequired(): void {
		$violations = ( new RelationType() )->validate(
			new RelationValue(),
			$this->newRelationProperty( multiple: false, required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
	}

	private function newRelationProperty( bool $multiple, bool $required = false ): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'relation' => 'has', 'targetSchema' => 'Person', 'multiple' => $multiple ],
		);
	}

}
