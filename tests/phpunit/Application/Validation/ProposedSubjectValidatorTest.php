<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

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
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

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
			schemaLookup: $this->schemaLookup,
			subjectValidator: new SubjectValidator( propertyTypeLookup: PropertyTypeRegistry::withCoreTypes() ),
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

	public function testReturnsNoViolationsWhenSchemaNotFound(): void {
		$this->registerSchemaWithAge( required: true );

		$this->assertSame( [], $this->newValidator()->validate( $this->newSubject( 'UnregisteredSchema' ) ) );
	}

}
