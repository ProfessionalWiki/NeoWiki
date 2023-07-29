<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Presentation\LuaGetSubjectPresenter;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\LuaGetSubjectPresenter
 */
class LuaGetSubjectPresenterTest extends TestCase {

	public function testGetLuaResponse(): void {
		$presenter = new LuaGetSubjectPresenter();

		$presenter->presentSubject( new GetSubjectResponse(
			'Q1',
			[
				'Q1' => new GetSubjectResponseItem(
					id: 'Q1',
					label: 'Foo',
					schemaId: 'Employee',
					statements: [
						'P1' => [ 'Bar', 'Baz' ],
						'P2' => [ 'Hi' ],
					],
					pageId: 42,
					pageTitle: 'Foo',
				)
			]
		) );

		$this->assertSame(
			[
				'id' => 'Q1',
				'label' => 'Foo',
				'schema' => 'Employee',
				'statements' => [
					'P1' => [ 1 => 'Bar', 2 => 'Baz' ],
					'P2' => [ 1 => 'Hi' ],
				],
				'pageId' => 42,
				'pageTitle' => 'Foo',
			],
			$presenter->getLuaResponse()
		);
	}

}
