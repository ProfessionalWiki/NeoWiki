<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Presentation\RestCreateSubjectPresenter;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestCreateSubjectPresenter
 */
class RestCreateSubjectPresenterTest extends TestCase {

	private function newSubject( ?int $pageId = 42 ): GetSubjectResponseItem {
		return new GetSubjectResponseItem(
			id: 's1demo1aaaaaaa1',
			label: 'ACME Corp',
			schemaName: 'Organization',
			statements: [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			pageId: $pageId,
			pageTitle: $pageId === null ? null : 'ACME Corp',
			pageNamespaceId: $pageId === null ? null : 0,
		);
	}

	private function newSchema(): Schema {
		return new Schema(
			name: new SchemaName( 'Organization' ),
			description: 'Companies and the like',
			properties: new PropertyDefinitions( [
				'Animal' => new TextProperty(
					core: new PropertyCore( description: 'Mascot', required: false, default: null ),
					multiple: false,
					uniqueItems: false,
					minLength: null,
					maxLength: null,
				),
			] )
		);
	}

	public function testPresentCreatedYields201WithSerializedViolations(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [
			new Violation( propertyName: new PropertyName( 'Status' ), code: 'required' ),
		] );

		$this->assertSame( 201, $presenter->getStatusCode() );
		$this->assertSame( 'created', $presenter->getJsonArray()['status'] );
		$this->assertSame( 's1demo1aaaaaaa1', $presenter->getJsonArray()['subjectId'] );
		$this->assertSame(
			[ [ 'propertyName' => 'Status', 'code' => 'required', 'args' => [] ] ],
			$presenter->getJsonArray()['violations']
		);
	}

	public function testPresentCreatedWithNoViolationsYieldsEmptyViolationsArray(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [] );

		$this->assertSame( 201, $presenter->getStatusCode() );
		$this->assertSame( [], $presenter->getJsonArray()['violations'] );
	}

	public function testPresentCreatedIncludesTheCanonicalSubjectWithItsPageIdentifiers(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [] );

		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'schema' => 'Organization',
				'pageId' => 42,
				'pageTitle' => 'ACME Corp',
				'pageNamespaceId' => 0,
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			$presenter->getJsonArray()['subject']
		);
	}

	public function testPresentCreatedOmitsPageKeysWhenTheHostingPageIsUnresolved(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject( pageId: null ), null, [] );

		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'schema' => 'Organization',
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			$presenter->getJsonArray()['subject']
		);
	}

	public function testPresentCreatedIncludesTheSchemaBodyWithoutItsName(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), $this->newSchema(), [] );

		$this->assertSame(
			[
				'description' => 'Companies and the like',
				'propertyDefinitions' => [
					'Animal' => [
						'type' => 'text',
						'description' => 'Mascot',
						'required' => false,
						'default' => null,
						'multiple' => false,
						'uniqueItems' => false,
						'minLength' => null,
						'maxLength' => null,
					],
				],
			],
			$presenter->getJsonArray()['schema']
		);
	}

	public function testPresentCreatedOmitsTheSchemaKeyWhenTheSchemaIsMissing(): void {
		$presenter = new RestCreateSubjectPresenter();

		$presenter->presentCreated( $this->newSubject(), null, [] );

		$this->assertArrayNotHasKey( 'schema', $presenter->getJsonArray() );
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
