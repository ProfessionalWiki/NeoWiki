<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetView;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetView\GetViewPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetView\GetViewQuery;
use ProfessionalWiki\NeoWiki\Application\ViewLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\View\DisplayRules;
use ProfessionalWiki\NeoWiki\Domain\View\View;
use ProfessionalWiki\NeoWiki\Domain\View\ViewName;
use ProfessionalWiki\NeoWiki\Presentation\ViewPresentationSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetView\GetViewQuery
 */
class GetViewQueryTest extends TestCase {

	public function testPresentsViewWhenFound(): void {
		$view = new View(
			name: new ViewName( 'TestView' ),
			schema: new SchemaName( 'Company' ),
			type: 'infobox',
			description: '',
			displayRules: new DisplayRules( [] ),
			settings: [],
		);
		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $view )->execute( 'TestView' );

		$this->assertFalse( $presenter->notFound );
		$data = json_decode( $presenter->json, true );
		$this->assertSame( 'Company', $data['schema'] );
		$this->assertSame( 'infobox', $data['type'] );
	}

	public function testPresentsNotFoundWhenViewDoesNotExist(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, null )->execute( 'NonExistent' );

		$this->assertTrue( $presenter->notFound );
	}

	private function newQuery( GetViewPresenter $presenter, ?View $view ): GetViewQuery {
		$viewLookup = $this->createStub( ViewLookup::class );
		$viewLookup->method( 'getView' )->willReturn( $view );
		return new GetViewQuery( $presenter, $viewLookup, new ViewPresentationSerializer() );
	}

	private function newSpyPresenter(): GetViewPresenter {
		return new class implements GetViewPresenter {
			public string $json = '';
			public bool $notFound = false;

			public function presentView( string $json ): void {
				$this->json = $json;
			}

			public function presentViewNotFound(): void {
				$this->notFound = true;
			}
		};
	}

}
