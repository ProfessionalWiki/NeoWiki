<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi
 * @group database
 */
class GetSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testSubjectIsFound(): void {
		$this->createPages();

		$response = $this->executeHandler(
			NeoWikiExtension::newGetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => '123e4567-e89b-12d3-a456-426655440000'
				]
			] )
		);

		$this->assertSame(
			'{"subject":{"id":"123e4567-e89b-12d3-a456-426655440000","label":"Test subject 426655440000","schema":"GetSubjectApiTest","properties":[]}}',
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	private function createPages(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				label: new SubjectLabel( 'Test subject 426655440000' ),
				schemaId: new SchemaId( 'GetSubjectApiTest' )
			)
		);
	}

	public function testSubjectIsNotFound(): void {
		$response = $this->executeHandler(
			NeoWikiExtension::newGetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => '404e4567-e89b-12d3-a456-426655440404'
				]
			] )
		);

		$this->assertSame(
			'{"subject":null}',
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

}
