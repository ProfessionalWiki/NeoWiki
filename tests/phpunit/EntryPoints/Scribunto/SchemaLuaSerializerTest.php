<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\SchemaLuaSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\SchemaLuaSerializer
 */
class SchemaLuaSerializerTest extends TestCase {

	private function newSerializer(): SchemaLuaSerializer {
		return new SchemaLuaSerializer();
	}

	private function coreRequired(): PropertyCore {
		return new PropertyCore( description: '', required: true, default: null );
	}

	private function coreOptional(): PropertyCore {
		return new PropertyCore( description: '', required: false, default: null );
	}

	public function testEmptySchema(): void {
		$schema = new Schema(
			new SchemaName( 'Empty' ),
			'',
			new PropertyDefinitions( [] )
		);

		$this->assertSame(
			[ 'name' => 'Empty', 'properties' => [] ],
			$this->newSerializer()->toLuaTable( $schema )
		);
	}

	public function testSchemaWithDescription(): void {
		$schema = new Schema(
			new SchemaName( 'Doc' ),
			'Describes a document',
			new PropertyDefinitions( [] )
		);

		$result = $this->newSerializer()->toLuaTable( $schema );

		$this->assertSame( 'Describes a document', $result['description'] );
	}

	public function testTextPropertyMinimal(): void {
		$schema = $this->schemaWith( [
			'LegalName' => new TextProperty( $this->coreRequired(), multiple: false, uniqueItems: false, minLength: null, maxLength: null ),
		] );

		$this->assertSame(
			[
				1 => [
					'name' => 'LegalName',
					'type' => 'text',
					'required' => true,
					'multiple' => false,
					'uniqueItems' => false,
				],
			],
			$this->newSerializer()->toLuaTable( $schema )['properties']
		);
	}

	public function testTextPropertyWithDescriptionAndDefault(): void {
		$core = new PropertyCore( description: 'City of residence', required: false, default: 'Berlin' );
		$schema = $this->schemaWith( [
			'City' => new TextProperty( $core, multiple: false, uniqueItems: false, minLength: null, maxLength: null ),
		] );

		$prop = $this->newSerializer()->toLuaTable( $schema )['properties'][1];

		$this->assertSame( 'City of residence', $prop['description'] );
		$this->assertSame( 'Berlin', $prop['default'] );
	}

	public function testTextPropertyMultipleAndUniqueItems(): void {
		$schema = $this->schemaWith( [
			'Skills' => new TextProperty( $this->coreOptional(), multiple: true, uniqueItems: true, minLength: null, maxLength: null ),
		] );

		$prop = $this->newSerializer()->toLuaTable( $schema )['properties'][1];

		$this->assertTrue( $prop['multiple'] );
		$this->assertTrue( $prop['uniqueItems'] );
	}

	public function testUrlProperty(): void {
		$schema = $this->schemaWith( [
			'Homepage' => new UrlProperty( $this->coreOptional(), multiple: false, uniqueItems: false ),
		] );

		$prop = $this->newSerializer()->toLuaTable( $schema )['properties'][1];

		$this->assertSame( 'url', $prop['type'] );
		$this->assertArrayNotHasKey( 'description', $prop );
		$this->assertArrayNotHasKey( 'default', $prop );
	}

	public function testNumberPropertyWithBounds(): void {
		$schema = $this->schemaWith( [
			'Score' => new NumberProperty(
				$this->coreOptional(), precision: 2, minimum: 0, maximum: 100
			),
		] );

		$this->assertSame(
			[
				1 => [
					'name' => 'Score',
					'type' => 'number',
					'required' => false,
					'precision' => 2,
					'minimum' => 0,
					'maximum' => 100,
				],
			],
			$this->newSerializer()->toLuaTable( $schema )['properties']
		);
	}

	public function testNumberPropertyWithoutBoundsOmitsKeys(): void {
		$schema = $this->schemaWith( [
			'Year' => new NumberProperty(
				$this->coreOptional(), precision: null, minimum: null, maximum: null
			),
		] );

		$prop = $this->newSerializer()->toLuaTable( $schema )['properties'][1];

		$this->assertArrayNotHasKey( 'precision', $prop );
		$this->assertArrayNotHasKey( 'minimum', $prop );
		$this->assertArrayNotHasKey( 'maximum', $prop );
	}

	public function testSelectPropertyOptionsAreOneIndexed(): void {
		$schema = $this->schemaWith( [
			'Status' => new SelectProperty(
				$this->coreRequired(),
				options: [
					new SelectOption( 'opt1', 'Active' ),
					new SelectOption( 'opt2', 'Inactive' ),
					new SelectOption( 'opt3', 'Archived' ),
				],
				multiple: false
			),
		] );

		$prop = $this->newSerializer()->toLuaTable( $schema )['properties'][1];

		$this->assertSame(
			[
				1 => [ 'id' => 'opt1', 'label' => 'Active' ],
				2 => [ 'id' => 'opt2', 'label' => 'Inactive' ],
				3 => [ 'id' => 'opt3', 'label' => 'Archived' ],
			],
			$prop['options']
		);
	}

	public function testRelationProperty(): void {
		$schema = $this->schemaWith( [
			'Employer' => new RelationProperty(
				$this->coreOptional(),
				new RelationType( 'Works for' ),
				new SchemaName( 'Company' ),
				multiple: false
			),
		] );

		$prop = $this->newSerializer()->toLuaTable( $schema )['properties'][1];

		$this->assertSame( 'relation', $prop['type'] );
		$this->assertSame( 'Works for', $prop['relation'] );
		$this->assertSame( 'Company', $prop['targetSchema'] );
		$this->assertFalse( $prop['multiple'] );
	}

	public function testListWithNullGapIsCompactedAndOneIndexed(): void {
		$property = new class( $this->coreOptional() ) extends PropertyDefinition {

			public function getPropertyType(): string {
				return 'fake';
			}

			public function nonCoreToJson(): array {
				return [ 'items' => [ 'a', null, 'b', '', 'c' ] ];
			}

		};
		$schema = $this->schemaWith( [ 'Fake' => $property ] );

		$prop = $this->newSerializer()->toLuaTable( $schema )['properties'][1];

		$this->assertSame(
			[ 1 => 'a', 2 => 'b', 3 => 'c' ],
			$prop['items']
		);
	}

	public function testPropertyOrderMatchesDefinitionOrder(): void {
		$schema = $this->schemaWith( [
			'Zeta'  => new TextProperty( $this->coreOptional(), multiple: false, uniqueItems: false, minLength: null, maxLength: null ),
			'Alpha' => new TextProperty( $this->coreOptional(), multiple: false, uniqueItems: false, minLength: null, maxLength: null ),
			'Mu'    => new TextProperty( $this->coreOptional(), multiple: false, uniqueItems: false, minLength: null, maxLength: null ),
		] );

		$names = array_column(
			$this->newSerializer()->toLuaTable( $schema )['properties'],
			'name'
		);

		$this->assertSame( [ 'Zeta', 'Alpha', 'Mu' ], $names );
	}

	/**
	 * @param array<string, \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition> $props
	 */
	private function schemaWith( array $props ): Schema {
		return new Schema(
			new SchemaName( 'Test' ),
			'',
			new PropertyDefinitions( $props )
		);
	}

}
