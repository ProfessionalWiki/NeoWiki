<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SelectPatchResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SelectPatchResolver
 */
class SelectPatchResolverTest extends TestCase {

	private function newResolver(): SelectPatchResolver {
		return new SelectPatchResolver( new SelectValueResolver() );
	}

	private function newSchemaWithSelect( bool $multiple = false ): Schema {
		return new Schema(
			name: new SchemaName( 'SomeSchema' ),
			description: '',
			properties: new PropertyDefinitions( [
				'Status' => new SelectProperty(
					core: new PropertyCore( description: '', required: false, default: null ),
					options: [
						new SelectOption( id: 'opt1', label: 'Draft' ),
						new SelectOption( id: 'opt2', label: 'Approved' ),
					],
					multiple: $multiple,
				),
				'Name' => TestProperty::buildText(),
			] )
		);
	}

	public function testResolvesScalarIdValue(): void {
		$patch = [
			'Status' => [ 'propertyType' => 'select', 'value' => 'opt1' ],
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );

		$this->assertSame( 'opt1', $resolved['Status']['value'] );
	}

	public function testResolvesScalarLabelValue(): void {
		$patch = [
			'Status' => [ 'propertyType' => 'select', 'value' => 'Draft' ],
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );

		$this->assertSame( 'opt1', $resolved['Status']['value'] );
	}

	public function testResolvesIdLabelObjectValue(): void {
		$patch = [
			'Status' => [
				'propertyType' => 'select',
				'value' => [ 'id' => 'opt2', 'label' => 'Approved' ],
			],
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );

		$this->assertSame( 'opt2', $resolved['Status']['value'] );
	}

	public function testResolvesListOfIds(): void {
		$patch = [
			'Status' => [
				'propertyType' => 'select',
				'value' => [ 'opt1', 'opt2' ],
			],
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect( multiple: true ), $patch );

		$this->assertSame( [ 'opt1', 'opt2' ], $resolved['Status']['value'] );
	}

	public function testResolvesListOfMixedForms(): void {
		$patch = [
			'Status' => [
				'propertyType' => 'select',
				'value' => [
					'opt1',
					'Approved',
					[ 'id' => 'opt1', 'label' => 'Draft' ],
				],
			],
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect( multiple: true ), $patch );

		$this->assertSame( [ 'opt1', 'opt2', 'opt1' ], $resolved['Status']['value'] );
	}

	public function testLeavesNonSelectPropertyUntouched(): void {
		$patch = [
			'Name' => [ 'propertyType' => 'text', 'value' => 'Some Name' ],
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );

		$this->assertSame( $patch, $resolved );
	}

	public function testLeavesNullValueUntouchedForSelect(): void {
		$patch = [
			'Status' => null,
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );

		$this->assertSame( $patch, $resolved );
	}

	public function testLeavesPatchUntouchedWhenSchemaDoesNotHaveProperty(): void {
		$patch = [
			'Unknown' => [ 'propertyType' => 'select', 'value' => 'something' ],
		];

		$resolved = $this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );

		$this->assertSame( $patch, $resolved );
	}

	public function testThrowsOnUnknownValueForKnownSelectProperty(): void {
		$patch = [
			'Status' => [ 'propertyType' => 'select', 'value' => 'Nonexistent' ],
		];

		$this->expectException( InvalidArgumentException::class );

		$this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );
	}

	public function testIncludesPropertyNameInErrorMessage(): void {
		$patch = [
			'Status' => [ 'propertyType' => 'select', 'value' => 'Nonexistent' ],
		];

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Status' );

		$this->newResolver()->resolve( $this->newSchemaWithSelect(), $patch );
	}

}
