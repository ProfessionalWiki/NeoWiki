<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\RejectedValueException;
use ProfessionalWiki\NeoWiki\Application\StatementNormalizer;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizationResult;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizesRawValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\StatementNormalizer
 */
class StatementNormalizerTest extends TestCase {

	private function newNormalizer(): StatementNormalizer {
		return new StatementNormalizer(
			PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY )
		);
	}

	private function newSchemaWithSelect(): Schema {
		return $this->newSchemaWithSelectNamed( 'Status' );
	}

	private function newSchemaWithSelectNamed( string $propertyName ): Schema {
		return new Schema(
			name: new SchemaName( 'SomeSchema' ),
			description: '',
			properties: new PropertyDefinitions( [
				$propertyName => new SelectProperty(
					core: new PropertyCore( description: '', required: false, default: null ),
					options: [
						new SelectOption( id: 'opt1', label: 'Draft' ),
						new SelectOption( id: 'opt2', label: 'Approved' ),
					],
					multiple: false,
				),
				'Name' => TestProperty::buildText(),
			] )
		);
	}

	public function testNormalizesScalarIdValue(): void {
		$statements = [ 'Status' => [ 'propertyType' => 'select', 'value' => 'opt2' ] ];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( 'opt2', $normalized['Status']['value'] );
	}

	public function testNormalizesScalarLabelValue(): void {
		$statements = [ 'Status' => [ 'propertyType' => 'select', 'value' => 'Approved' ] ];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( 'opt2', $normalized['Status']['value'] );
	}

	public function testNormalizesListOfMixedForms(): void {
		$statements = [
			'Status' => [
				'propertyType' => 'select',
				'value' => [ 'opt1', 'Approved', [ 'id' => 'opt1', 'label' => 'Draft' ] ],
			],
		];

		$normalized = $this->newNormalizer()->normalizeOrThrow(
			$this->newSchemaWithSelect(),
			$statements
		);

		$this->assertSame( [ 'opt1', 'opt2', 'opt1' ], $normalized['Status']['value'] );
	}

	public function testLeavesAPropertyWhoseTypeDoesNotNormalizeUntouched(): void {
		$statements = [ 'Name' => [ 'propertyType' => 'text', 'value' => 'Some Name' ] ];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( $statements, $normalized );
	}

	public function testNormalizesAnEntryAfterOneItPassesThrough(): void {
		$statements = [
			'Name' => [ 'propertyType' => 'text', 'value' => 'Some Name' ],
			'Status' => [ 'propertyType' => 'select', 'value' => 'Approved' ],
		];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( 'opt2', $normalized['Status']['value'] );
	}

	public function testLeavesANullEntryUntouched(): void {
		$statements = [ 'Status' => null ];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( $statements, $normalized );
	}

	public function testLeavesAnEntryWithoutAValueUntouched(): void {
		$statements = [ 'Status' => [ 'propertyType' => 'select' ] ];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( $statements, $normalized );
	}

	public function testLeavesAPropertyTheSchemaDoesNotDeclareUntouched(): void {
		$statements = [ 'Unknown' => [ 'propertyType' => 'select', 'value' => 'something' ] ];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( $statements, $normalized );
	}

	/**
	 * Writer's-schema drift (ADR 11): the caller names a type the Schema no longer gives the
	 * property. Normalizing against the Schema's current type would rewrite a value the caller
	 * never meant as a select one, so the entry is left for SubjectValidator to report.
	 */
	public function testLeavesAnEntryWhoseDeclaredTypeDiffersFromTheSchemaUntouched(): void {
		$statements = [ 'Status' => [ 'propertyType' => 'text', 'value' => 'Approved' ] ];

		$normalized = $this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( $statements, $normalized );
	}

	/**
	 * PHP turns a decimal-integer array key into an int, so a select property named like a year
	 * reaches the Schema lookups as one.
	 */
	public function testNormalizesValueOfPropertyNamedLikeAnInteger(): void {
		$statements = [ '2024' => [ 'propertyType' => 'select', 'value' => 'Draft' ] ];

		$normalized = $this->newNormalizer()->normalizeOrThrow(
			$this->newSchemaWithSelectNamed( '2024' ),
			$statements
		);

		$this->assertSame( 'opt1', $normalized['2024']['value'] );
	}

	public function testNormalizeResolvesValueOfPropertyNamedLikeAnInteger(): void {
		$statements = [ '2024' => [ 'propertyType' => 'select', 'value' => 'Draft' ] ];

		$normalized = $this->newNormalizer()->normalize(
			$this->newSchemaWithSelectNamed( '2024' ),
			$statements
		);

		$this->assertSame( 'opt1', $normalized['2024']['value'] );
	}

	public function testNormalizeDoesNotThrowOnAnUnresolvableValue(): void {
		$statements = [ 'Status' => [ 'propertyType' => 'select', 'value' => 'Nonexistent' ] ];

		$normalized = $this->newNormalizer()->normalize( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( 'Nonexistent', $normalized['Status']['value'] );
	}

	public function testNormalizeKeepsTheResolvablePartsOfAListThatHasAnUnresolvableOne(): void {
		$statements = [
			'Status' => [ 'propertyType' => 'select', 'value' => [ 'Draft', 'Nonexistent' ] ],
		];

		$normalized = $this->newNormalizer()->normalize(
			$this->newSchemaWithSelect(),
			$statements
		);

		$this->assertSame( [ 'opt1', 'Nonexistent' ], $normalized['Status']['value'] );
	}

	public function testAttachesThePropertyToAScalarViolation(): void {
		$statements = [ 'Status' => [ 'propertyType' => 'select', 'value' => 'Nonexistent' ] ];

		try {
			$this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );
			$this->fail( 'Expected a RejectedValueException' );
		} catch ( RejectedValueException $exception ) {
			$this->assertSame( 'Status', $exception->violation->propertyName->text );
			$this->assertSame( 0, $exception->violation->valuePartIndex );
		}
	}

	public function testAttachesThePropertyAndPartIndexToAListViolation(): void {
		$statements = [
			'Status' => [ 'propertyType' => 'select', 'value' => [ 'Draft', 'Bogus1', 'Bogus2' ] ],
		];

		try {
			$this->newNormalizer()->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );
			$this->fail( 'Expected a RejectedValueException' );
		} catch ( RejectedValueException $exception ) {
			$this->assertSame( 'Status', $exception->violation->propertyName->text );
			$this->assertSame( 1, $exception->violation->valuePartIndex );
		}
	}

	public function testPassesStatementsThroughWithoutASchema(): void {
		$statements = [ 'Status' => [ 'propertyType' => 'select', 'value' => 'Nonexistent' ] ];

		$this->assertSame( $statements, $this->newNormalizer()->normalizeOrThrow( null, $statements ) );
	}

	public function testAsksAnyTypeThatNormalizesNotOnlySelect(): void {
		$statements = [ 'Name' => [ 'propertyType' => 'text', 'value' => 'raw' ] ];

		$normalized = ( new StatementNormalizer( $this->newNormalizingTextTypeLookup() ) )
			->normalizeOrThrow( $this->newSchemaWithSelect(), $statements );

		$this->assertSame( 'canonical:raw', $normalized['Name']['value'] );
	}

	/**
	 * A lookup whose `text` type canonicalizes, so that nothing in the walk may single out SelectType.
	 */
	private function newNormalizingTextTypeLookup(): PropertyTypeLookup {
		return new class() implements PropertyTypeLookup {

			public function getType( string $typeName ): ?PropertyType {
				if ( $typeName !== 'text' ) {
					return null;
				}

				return new class() extends TextType implements NormalizesRawValue {

					public function normalizeRawValue( mixed $raw, PropertyDefinition $definition ): NormalizationResult {
						return NormalizationResult::normalized( 'canonical:' . $raw );
					}

				};
			}

		};
	}

}
