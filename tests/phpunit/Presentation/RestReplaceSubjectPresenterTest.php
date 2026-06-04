<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Presentation\RestReplaceSubjectPresenter;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestReplaceSubjectPresenter
 */
class RestReplaceSubjectPresenterTest extends TestCase {

	public function testPresentUpdatedYields200WithSerializedViolations(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( 's1demo1aaaaaaa1', [
			new Violation( propertyName: new PropertyName( 'Status' ), code: 'required' ),
			new Violation( propertyName: null, code: 'schema-not-found', args: [ 'Person' ] ),
		] );

		$this->assertSame( 200, $presenter->getStatusCode() );
		$this->assertSame(
			[
				'status' => 'updated',
				'subjectId' => 's1demo1aaaaaaa1',
				'violations' => [
					[ 'propertyName' => 'Status', 'code' => 'required', 'args' => [] ],
					[ 'propertyName' => null, 'code' => 'schema-not-found', 'args' => [ 'Person' ] ],
				],
			],
			$presenter->getJsonArray()
		);
	}

	public function testPresentUpdatedWithNoViolationsYieldsEmptyViolationsArray(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( 's1demo1aaaaaaa1', [] );

		$this->assertSame( 200, $presenter->getStatusCode() );
		$this->assertSame( [], $presenter->getJsonArray()['violations'] );
	}

}
