<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Presentation\RestCreateSubjectPresenter;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestCreateSubjectPresenter
 */
class RestCreateSubjectPresenterTest extends TestCase {

	public function testPresentCreatedYields201WithSerializedViolations(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( 's1demo1aaaaaaa1', [
			new Violation( propertyName: new PropertyName( 'Status' ), code: 'required' ),
		] );

		$this->assertSame( 201, $presenter->getStatusCode() );
		$this->assertSame(
			[
				'status' => 'created',
				'subjectId' => 's1demo1aaaaaaa1',
				'violations' => [
					[ 'propertyName' => 'Status', 'code' => 'required', 'args' => [] ],
				],
			],
			$presenter->getJsonArray()
		);
	}

	public function testPresentCreatedWithNoViolationsYieldsEmptyViolationsArray(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( 's1demo1aaaaaaa1', [] );

		$this->assertSame( 201, $presenter->getStatusCode() );
		$this->assertSame( [], $presenter->getJsonArray()['violations'] );
	}

	public function testPresentSubjectAlreadyExistsYields409ErrorWithoutViolations(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentSubjectAlreadyExists();

		$this->assertSame( 409, $presenter->getStatusCode() );
		$this->assertSame(
			[ 'status' => 'error', 'message' => 'Subject already exists' ],
			$presenter->getJsonArray()
		);
	}

	public function testPresentValidationFailedYields422WithErrorBody(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentValidationFailed( [
			new Violation( propertyName: new PropertyName( 'Required' ), code: 'required' ),
		] );

		$this->assertSame( 422, $presenter->getStatusCode() );
		$this->assertSame(
			[
				'status' => 'error',
				'message' => 'Validation failed',
				'violations' => [
					[ 'propertyName' => 'Required', 'code' => 'required', 'args' => [] ],
				],
			],
			$presenter->getJsonArray()
		);
	}

}
