<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Presentation\LuaGetSubjectPresenter;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\LuaGetSubjectPresenter
 */
class LuaGetSubjectPresenterTest extends TestCase {

	public function testGetLuaResponse(): void {
		$presenter = new LuaGetSubjectPresenter();

		$presenter->presentSubject( new GetSubjectResponse(
			id: 'Q1',
			label: 'Foo',
			schemaId: 'Employee',
			properties: [
				'P1' => [ 'Bar', 'Baz' ],
				'P2' => [ 'Hi' ],
			]
		) );

		$this->assertSame(
			[
				'id' => 'Q1',
				'label' => 'Foo',
				'schema' => 'Employee',
				'properties' => [
					'P1' => [ 1 => 'Bar', 2 => 'Baz' ],
					'P2' => [ 1 => 'Hi' ],
				],
			],
			$presenter->getLuaResponse()
		);
	}

}
