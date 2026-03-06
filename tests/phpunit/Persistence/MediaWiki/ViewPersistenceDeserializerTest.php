<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\View\ViewName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\ViewPersistenceDeserializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\ViewPersistenceDeserializer
 */
class ViewPersistenceDeserializerTest extends TestCase {

	public function testDeserializesMinimalView(): void {
		$deserializer = new ViewPersistenceDeserializer();

		$view = $deserializer->deserialize(
			new ViewName( 'FinancialOverview' ),
			'{ "schema": "Company", "type": "infobox" }'
		);

		$this->assertSame( 'FinancialOverview', $view->getName()->getText() );
		$this->assertSame( 'Company', $view->getSchema()->getText() );
		$this->assertSame( 'infobox', $view->getType() );
		$this->assertSame( '', $view->getDescription() );
		$this->assertTrue( $view->getDisplayRules()->isEmpty() );
		$this->assertSame( [], $view->getSettings() );
	}

	public function testDeserializesFullView(): void {
		$deserializer = new ViewPersistenceDeserializer();

		$view = $deserializer->deserialize(
			new ViewName( 'FinancialOverview' ),
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

		$this->assertSame( 'Key financial data', $view->getDescription() );
		$this->assertFalse( $view->getDisplayRules()->isEmpty() );

		$rules = iterator_to_array( $view->getDisplayRules() );
		$this->assertCount( 3, $rules );

		$this->assertSame( 'Revenue', (string)$rules[0]->getProperty() );
		$this->assertSame( [ 'precision' => 0 ], $rules[0]->getDisplayAttributes() );

		$this->assertSame( 'Net Income', (string)$rules[1]->getProperty() );
		$this->assertSame( [], $rules[1]->getDisplayAttributes() );

		$this->assertSame( 'Total Assets', (string)$rules[2]->getProperty() );

		$this->assertSame( [ 'borderColor' => '#336699' ], $view->getSettings() );
	}

	public function testInvalidJsonThrows(): void {
		$deserializer = new ViewPersistenceDeserializer();

		$this->expectException( InvalidArgumentException::class );

		$deserializer->deserialize(
			new ViewName( 'Foo' ),
			'not json'
		);
	}

}
