<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
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
		$this->assertSame( [], ( new RelationType( TestSources::newSchemaReferenceParser() ) )->getDisplayAttributeNames() );
	}

	public function testSingleValuePropertyWithTwoTargetsReturnsSingleValueOnly(): void {
		$violations = ( new RelationType( TestSources::newSchemaReferenceParser() ) )->validate(
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
		$violations = ( new RelationType( TestSources::newSchemaReferenceParser() ) )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newRelationProperty( multiple: false ),
		);

		$this->assertSame( Severity::Warning, $violations[0]->severity );
	}

	public function testSingleValueOnlyUsesErrorWhenMultipleAnnotated(): void {
		$violations = ( new RelationType( TestSources::newSchemaReferenceParser() ) )->validate(
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
		$this->assertSame( [], ( new RelationType( TestSources::newSchemaReferenceParser() ) )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newRelationProperty( multiple: true ),
		) );
	}

	public function testSingleValuePropertyWithOneTargetReturnsNoViolation(): void {
		$this->assertSame( [], ( new RelationType( TestSources::newSchemaReferenceParser() ) )->validate(
			new RelationValue( TestRelation::build( targetId: 'srt111111111aaa' ) ),
			$this->newRelationProperty( multiple: false ),
		) );
	}

	public function testRequiredPropertyWithoutTargetsReturnsRequired(): void {
		$violations = ( new RelationType( TestSources::newSchemaReferenceParser() ) )->validate(
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
		$violations = ( new RelationType( TestSources::newSchemaReferenceParser() ) )->validate(
			new RelationValue(
				TestRelation::build( targetId: 'srt111111111aaa' ),
				TestRelation::build( targetId: 'srt111111111bbb' ),
			),
			$this->newRelationPropertyWithoutMultipleKey(),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'single-value-only', $violations[0]->code );
	}

	/**
	 * A targetSchema is a Schema reference like the one on a Subject, so several spellings name one
	 * Schema. Left as written it would not match the Subject it points at, whose own reference is
	 * normalized, and every relation to that Subject would be reported as the wrong target type.
	 */
	public function testTargetSchemaIsReadAsTheNameOfTheSchemaItNames(): void {
		$property = ( new RelationType( TestSources::newSchemaReferenceParser( [ 'person' => 'Person' ] ) ) )
			->buildPropertyDefinitionFromJson(
				new PropertyCore( description: '', required: false, default: null ),
				[ 'relation' => 'has', 'targetSchema' => 'person' ]
			);

		$this->assertEquals(
			SchemaReference::local( new SchemaName( 'Person' ) ),
			$property->getTargetSchema()
		);
	}

	private function newRelationProperty( bool $multiple, bool $required = false ): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'relation' => 'has', 'targetSchema' => 'Person', 'multiple' => $multiple ],
			TestSources::newSchemaReferenceParser(),
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
			PropertyTypeRegistry::withCoreTypes( TestSources::newSchemaReferenceParser() ),
		);
	}

	private function newRelationPropertyWithoutMultipleKey(): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'relation' => 'has', 'targetSchema' => 'Person' ],
			TestSources::newSchemaReferenceParser(),
		);
	}

}
