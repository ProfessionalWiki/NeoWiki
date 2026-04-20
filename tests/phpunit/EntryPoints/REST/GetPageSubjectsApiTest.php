<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi
 * @group Database
 */
class GetPageSubjectsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testReturnsEmptyResultForPageWithNoSubjects(): void {
		$revision = $this->createPageWithSubjects( 'EmptyPage' );
		$pageId = (string)$revision->getPageId();

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'pageId' => $pageId ] ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$data = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( [], $data['subjects'] );
		$this->assertSame( 0, $data['totalRows'] );
	}

	public function testReturnsMainAndChildSubjectsWithCorrectFlags(): void {
		$main = TestSubject::build(
			id: 's1zz1111111aaa1',
			label: 'ACME Inc.',
			schemaName: new SchemaName( 'Company' )
		);
		$child = TestSubject::build(
			id: 's1zz1111111aaa2',
			label: 'Widget X',
			schemaName: new SchemaName( 'Product' )
		);

		$revision = $this->createPageWithSubjects(
			'PageWithBoth',
			mainSubject: $main,
			childSubjects: new SubjectMap( $child )
		);

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'pageId' => (string)$revision->getPageId() ] ] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "subjects": [
        {
            "id": "s1zz1111111aaa1",
            "label": "ACME Inc.",
            "schema": "Company",
            "isMain": true
        },
        {
            "id": "s1zz1111111aaa2",
            "label": "Widget X",
            "schema": "Product",
            "isMain": false
        }
    ],
    "totalRows": 2
}
JSON,
			$response->getBody()->getContents()
		);
	}

	public function testChildrenSortedByLabelAscendingCaseInsensitive(): void {
		$schema = new SchemaName( 'X' );
		$main = TestSubject::build( id: 's1zz1111111bbb1', label: 'ZZZ Main', schemaName: $schema );
		$children = new SubjectMap(
			TestSubject::build( id: 's1zz1111111bbb2', label: 'banana', schemaName: $schema ),
			TestSubject::build( id: 's1zz1111111bbb3', label: 'Apple', schemaName: $schema ),
			TestSubject::build( id: 's1zz1111111bbb4', label: 'cherry', schemaName: $schema ),
		);

		$revision = $this->createPageWithSubjects( 'SortPage', $main, $children );

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'pageId' => (string)$revision->getPageId() ] ] )
		);

		$data = json_decode( $response->getBody()->getContents(), true );

		$labels = array_column( $data['subjects'], 'label' );
		$this->assertSame( [ 'ZZZ Main', 'Apple', 'banana', 'cherry' ], $labels );
	}

	public function testPageWithOnlyMainSubject(): void {
		$main = TestSubject::build(
			id: 's1zz1111111ccc1',
			label: 'Solo',
			schemaName: new SchemaName( 'X' )
		);
		$revision = $this->createPageWithSubjects( 'OnlyMain', mainSubject: $main );

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'pageId' => (string)$revision->getPageId() ] ] )
		);

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 1, $data['totalRows'] );
		$this->assertCount( 1, $data['subjects'] );
		$this->assertTrue( $data['subjects'][0]['isMain'] );
	}

	public function testPagination(): void {
		$schema = new SchemaName( 'X' );
		$main = TestSubject::build( id: 's1zz1111111ddd1', label: 'M', schemaName: $schema );
		$children = new SubjectMap(
			TestSubject::build( id: 's1zz1111111ddd2', label: 'a', schemaName: $schema ),
			TestSubject::build( id: 's1zz1111111ddd3', label: 'b', schemaName: $schema ),
			TestSubject::build( id: 's1zz1111111ddd4', label: 'c', schemaName: $schema ),
		);
		$revision = $this->createPageWithSubjects( 'PagedPage', $main, $children );

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$revision->getPageId() ],
				'queryParams' => [ 'limit' => '2', 'offset' => '1' ],
			] )
		);

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 4, $data['totalRows'] );
		$this->assertCount( 2, $data['subjects'] );
		$this->assertSame( 'a', $data['subjects'][0]['label'] );
		$this->assertSame( 'b', $data['subjects'][1]['label'] );
	}

	public function testReturns404ForMissingPage(): void {
		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'pageId' => '9999999' ] ] )
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testReturns403WhenUserCannotReadPage(): void {
		$revision = $this->createPageWithSubjects( 'NoReadPage' );

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$revision->getPageId() ],
			] ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$this->assertSame( 403, $response->getStatusCode() );
	}
}
