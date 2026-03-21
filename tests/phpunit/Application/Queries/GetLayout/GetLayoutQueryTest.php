<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetLayout;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetLayout\GetLayoutPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetLayout\GetLayoutQuery;
use ProfessionalWiki\NeoWiki\Application\LayoutLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Layout\DisplayRules;
use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\Presentation\LayoutPresentationSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetLayout\GetLayoutQuery
 */
class GetLayoutQueryTest extends TestCase {

	public function testPresentsLayoutWhenFound(): void {
		$layout = new Layout(
			name: new LayoutName( 'TestView' ),
			schema: new SchemaName( 'Company' ),
			type: 'infobox',
			description: '',
			displayRules: new DisplayRules( [] ),
			settings: [],
		);
		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $layout )->execute( 'TestView' );

		$this->assertFalse( $presenter->notFound );
		$data = json_decode( $presenter->json, true );
		$this->assertSame( 'Company', $data['schema'] );
		$this->assertSame( 'infobox', $data['type'] );
	}

	public function testPresentsNotFoundWhenLayoutDoesNotExist(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, null )->execute( 'NonExistent' );

		$this->assertTrue( $presenter->notFound );
	}

	private function newQuery( GetLayoutPresenter $presenter, ?Layout $layout ): GetLayoutQuery {
		$layoutLookup = $this->createStub( LayoutLookup::class );
		$layoutLookup->method( 'getLayout' )->willReturn( $layout );
		return new GetLayoutQuery( $presenter, $layoutLookup, new LayoutPresentationSerializer() );
	}

	private function newSpyPresenter(): GetLayoutPresenter {
		return new class implements GetLayoutPresenter {
			public string $json = '';
			public bool $notFound = false;

			public function presentLayout( string $json ): void {
				$this->json = $json;
			}

			public function presentLayoutNotFound(): void {
				$this->notFound = true;
			}
		};
	}

}
