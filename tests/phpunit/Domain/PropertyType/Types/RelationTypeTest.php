<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
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

	public function testSingleValueOnlyDefaultsToWarning(): void {
		$violations = ( new RelationType() )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newRelationProperty( multiple: false ),
		);

		$this->assertSame( Severity::Warning, $violations[0]->severity );
	}

	public function testSingleValueOnlyUsesErrorWhenMultipleAnnotated(): void {
		$violations = ( new RelationType() )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newAnnotatedRelationProperty(),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'single-value-only', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
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

	public function testPropertyOmittingMultipleWithTwoTargetsReturnsSingleValueOnly(): void {
		// `multiple` is optional in the Schema JSON and defaults to false, so a relation property
		// authored or imported without the key is single-valued. Pinned here because that default
		// is what decides whether the violation fires at all, not the validation.
		$violations = ( new RelationType() )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newRelationPropertyWithoutMultipleKey(),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'single-value-only', $violations[0]->code );
	}

	private function newRelationProperty( bool $multiple, bool $required = false ): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'relation' => 'has', 'targetSchema' => 'Person', 'multiple' => $multiple ],
		);
	}

	private function newAnnotatedRelationProperty(): PropertyDefinition {
		return PropertyDefinition::fromJson(
			[
				'type' => 'relation',
				'relation' => 'has',
				'targetSchema' => 'Person',
				'multiple' => [ 'value' => false, 'severity' => 'error' ],
			],
			PropertyTypeRegistry::withCoreTypes(),
		);
	}

	private function newRelationPropertyWithoutMultipleKey(): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'relation' => 'has', 'targetSchema' => 'Person' ],
		);
	}

}
