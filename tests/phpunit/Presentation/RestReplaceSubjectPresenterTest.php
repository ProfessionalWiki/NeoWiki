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
use ProfessionalWiki\NeoWiki\Presentation\RestReplaceSubjectPresenter;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestReplaceSubjectPresenter
 */
class RestReplaceSubjectPresenterTest extends TestCase {

	private function newSubject( ?int $pageId = 42 ): GetSubjectResponseItem {
		return new GetSubjectResponseItem(
			id: 's1demo1aaaaaaa1',
			label: 'ACME Corp',
			displayName: 'ACME Corp',
			displayNameIsGenerated: false,
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

	public function testPresentUpdatedYields200WithSerializedViolations(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( $this->newSubject(), null, [
			new Violation( propertyName: new PropertyName( 'Status' ), code: 'required' ),
			new Violation( propertyName: null, code: 'schema-not-found', args: [ 'Person' ] ),
		] );

		$this->assertSame( 200, $presenter->getStatusCode() );
		$this->assertSame( 'updated', $presenter->getJsonArray()['status'] );
		$this->assertSame( 's1demo1aaaaaaa1', $presenter->getJsonArray()['subjectId'] );
		$this->assertSame(
			[
				[ 'propertyName' => 'Status', 'code' => 'required', 'args' => [], 'severity' => 'warning' ],
				[ 'propertyName' => null, 'code' => 'schema-not-found', 'args' => [ 'Person' ], 'severity' => 'warning' ],
			],
			$presenter->getJsonArray()['violations']
		);
	}

	public function testPresentUpdatedWithNoViolationsYieldsEmptyViolationsArray(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( $this->newSubject(), null, [] );

		$this->assertSame( 200, $presenter->getStatusCode() );
		$this->assertSame( [], $presenter->getJsonArray()['violations'] );
	}

	public function testPresentUpdatedIncludesTheCanonicalSubjectWithItsPageIdentifiers(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( $this->newSubject(), null, [] );

		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'displayName' => 'ACME Corp',
				'displayNameIsGenerated' => false,
				'schema' => 'Organization',
				'pageId' => 42,
				'pageTitle' => 'ACME Corp',
				'pageNamespaceId' => 0,
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			$presenter->getJsonArray()['subject']
		);
	}

	public function testPresentUpdatedOmitsPageKeysWhenTheHostingPageIsUnresolved(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( $this->newSubject( pageId: null ), null, [] );

		$this->assertSame(
			[
				'id' => 's1demo1aaaaaaa1',
				'label' => 'ACME Corp',
				'displayName' => 'ACME Corp',
				'displayNameIsGenerated' => false,
				'schema' => 'Organization',
				'statements' => [ 'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ] ],
			],
			$presenter->getJsonArray()['subject']
		);
	}

	public function testPresentUpdatedIncludesTheSchemaBodyWithoutItsName(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( $this->newSubject(), $this->newSchema(), [] );

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

	public function testPresentUpdatedOmitsTheSchemaKeyWhenTheSchemaIsMissing(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentUpdated( $this->newSubject(), null, [] );

		$this->assertArrayNotHasKey( 'schema', $presenter->getJsonArray() );
	}

	public function testPresentValidationFailedYields422WithErrorBody(): void {
		$presenter = new RestReplaceSubjectPresenter();

		$presenter->presentValidationFailed( [
			new Violation( propertyName: new PropertyName( 'Status' ), code: 'required' ),
		] );

		$this->assertSame( 422, $presenter->getStatusCode() );
		$this->assertSame(
			[
				'status' => 'error',
				'message' => 'Validation failed',
				'violations' => [
					[ 'propertyName' => 'Status', 'code' => 'required', 'args' => [], 'severity' => 'warning' ],
				],
			],
			$presenter->getJsonArray()
		);
	}

}
