<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer
 */
class LayoutPersistenceDeserializerTest extends TestCase {

	public function testDeserializesMinimalLayout(): void {
		$deserializer = new LayoutPersistenceDeserializer( TestSources::newSchemaReferenceParser() );

		$layout = $deserializer->deserialize(
			new LayoutName( 'FinancialOverview' ),
			'{ "schema": "Company", "type": "infobox" }'
		);

		$this->assertSame( 'FinancialOverview', $layout->getName()->getText() );
		$this->assertSame( 'Company', $layout->getSchema()->getText() );
		$this->assertSame( 'infobox', $layout->getType() );
		$this->assertSame( '', $layout->getDescription() );
		$this->assertTrue( $layout->getDisplayRules()->isEmpty() );
		$this->assertSame( [], $layout->getSettings() );
	}

	/**
	 * A Layout references a Schema, and several spellings name one Schema page, so the reference is
	 * read the same way a Subject's is — otherwise the Layout would match no Subject's Schema.
	 */
	public function testSchemaIsReadAsTheNameOfTheSchemaItNames(): void {
		$deserializer = new LayoutPersistenceDeserializer(
			TestSources::newSchemaReferenceParser( [ 'person' => 'Person' ] )
		);

		$layout = $deserializer->deserialize(
			new LayoutName( 'PersonCard' ),
			'{ "schema": "person", "type": "infobox" }'
		);

		$this->assertSame( 'Person', $layout->getSchema()->getText() );
	}

	public function testDeserializesFullLayout(): void {
		$deserializer = new LayoutPersistenceDeserializer( TestSources::newSchemaReferenceParser() );

		$layout = $deserializer->deserialize(
			new LayoutName( 'FinancialOverview' ),
			json_encode( [
				'schema' => 'Company',
				'type' => 'infobox',
				'description' => 'Key financial data',
				'displayRules' => [
					[ 'property' => 'Revenue', 'displayAttributes' => [ 'precision' => 0 ] ],
					[ 'property' => 'Net Income' ],
					[ 'property' => 'Total Assets' ],
				],
				'settings' => [ 'borderColor' => '#336699' ],
			] )
		);

		$this->assertSame( 'Key financial data', $layout->getDescription() );
		$this->assertFalse( $layout->getDisplayRules()->isEmpty() );

		$rules = iterator_to_array( $layout->getDisplayRules() );
		$this->assertCount( 3, $rules );

		$this->assertSame( 'Revenue', (string)$rules[0]->getProperty() );
		$this->assertSame( [ 'precision' => 0 ], $rules[0]->getDisplayAttributes() );

		$this->assertSame( 'Net Income', (string)$rules[1]->getProperty() );
		$this->assertSame( [], $rules[1]->getDisplayAttributes() );

		$this->assertSame( 'Total Assets', (string)$rules[2]->getProperty() );

		$this->assertSame( [ 'borderColor' => '#336699' ], $layout->getSettings() );
	}

	public function testInvalidJsonThrows(): void {
		$deserializer = new LayoutPersistenceDeserializer( TestSources::newSchemaReferenceParser() );

		$this->expectException( InvalidArgumentException::class );

		$deserializer->deserialize(
			new LayoutName( 'Foo' ),
			'not json'
		);
	}

}
