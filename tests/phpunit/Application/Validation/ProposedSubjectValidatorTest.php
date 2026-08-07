<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator
 */
class ProposedSubjectValidatorTest extends TestCase {

	private const string SCHEMA_NAME = 'Person';

	private InMemorySchemaLookup $schemaLookup;

	protected function setUp(): void {
		$this->schemaLookup = new InMemorySchemaLookup();
	}

	private function newValidator(): ProposedSubjectValidator {
		return new ProposedSubjectValidator(
			schemaResolver: TestSources::newSchemaResolver( $this->schemaLookup ),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
		);
	}

	private function registerSchemaWithAge( bool $required ): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SCHEMA_NAME ),
			description: '',
			properties: new PropertyDefinitions( [
				'Age' => NumberProperty::fromPartialJson(
					new PropertyCore( description: '', required: $required, default: null ),
					[ 'minimum' => null, 'maximum' => null, 'precision' => null ],
				),
			] ),
		) );
	}

	private function newSubject( string $schemaName = self::SCHEMA_NAME ): Subject {
		return TestSubject::build(
			label: 'John Doe',
			schemaName: new SchemaName( $schemaName ),
		);
	}

	public function testReturnsNoViolationsForValidSubject(): void {
		$this->registerSchemaWithAge( required: false );

		$this->assertSame( [], $this->newValidator()->validate( $this->newSubject() ) );
	}

	public function testReturnsViolationFromSchemaForMissingRequiredProperty(): void {
		$this->registerSchemaWithAge( required: true );

		$violations = $this->newValidator()->validate( $this->newSubject() );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertSame( 'Age', $violations[0]->propertyName?->text );
	}

	public function testReturnsSchemaNotFoundViolationWhenSchemaIsMissing(): void {
		$this->registerSchemaWithAge( required: true );

		$violations = $this->newValidator()->validate( $this->newSubject( 'UnregisteredSchema' ) );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'schema-not-found', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertSame( [ 'UnregisteredSchema' ], $violations[0]->args );
	}

	/**
	 * A missing Schema costs the Schema-scoped checks, not the reachability one: the target would be
	 * written unreadable whether or not the Schema turns up later.
	 */
	public function testStillRejectsAnUnresolvableRelationTargetWhenTheSchemaIsMissing(): void {
		$subject = TestSubject::build(
			label: 'John Doe',
			schemaName: new SchemaName( 'UnregisteredSchema' ),
			statements: new StatementList( [
				new Statement(
					new PropertyName( 'Links' ),
					'relation',
					new RelationValue( TestRelation::build( targetId: 'neverinstalled:Q42' ) )
				),
			] ),
		);

		$violations = $this->newValidator()->validate( $subject );

		$this->assertSame(
			[ 'schema-not-found', 'relation-target-unresolvable-source' ],
			array_map( static fn ( $violation ): string => $violation->code, $violations )
		);
		$this->assertTrue( $violations[1]->alwaysBlocksWrites() );
	}

}
