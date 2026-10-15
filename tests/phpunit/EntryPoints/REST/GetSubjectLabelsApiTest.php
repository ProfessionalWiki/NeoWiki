<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the integration base class for the pages the Subjects are saved on and the database the suggestions are
 * read from.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi
 * @group Database
 */
class GetSubjectLabelsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private const string SCHEMA = 'GetSubjectLabelsApiTestSchema';
	private const string SUBJECT_ID = 'sTestSubject111';

	public function testNamesTheHostingPageOfEachResult(): void {
		// The label and the page are separate facts, so the Subject is labelled unlike its page: a
		// body echoing the label twice would pass an expectation built from one name. The page sits
		// outside the main namespace, so the prefix the field promises is asserted rather than
		// coinciding with the bare title.
		$this->createSchema( self::SCHEMA );
		$this->createPageWithSubjects(
			'Help:Rembrandt van Rijn',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Rembrandt' ),
				schemaName: new SchemaName( self::SCHEMA ),
			),
		);

		$response = $this->executeHandler(
			new GetSubjectLabelsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'schema' => self::SCHEMA, 'search' => 'Rem' ],
			] )
		);

		$this->assertSame(
			[ [
				'id' => self::SUBJECT_ID,
				'label' => 'Rembrandt',
				'pageTitle' => 'Help:Rembrandt van Rijn',
			] ],
			json_decode( $response->getBody()->getContents(), true )
		);
	}

	public function testRejectsLimitAboveMaximum(): void {
		// The endpoint runs a per-result read-permission check, so an uncapped limit is an
		// unbounded-work vector. The cap is enforced before the handler runs any query (#1060).
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new GetSubjectLabelsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'schema' => 'Person', 'limit' => '100000' ],
			] )
		);
	}

	/**
	 * What Special:Subject's own picker sends. A default standing in for the omitted `schema`
	 * would name a Schema no Subject has, and find nothing.
	 */
	public function testASearchNamingNoSchemaFindsSubjectsOfEverySchema(): void {
		$this->createSchema( 'Recipe' );
		$this->createSchema( 'Plant' );
		$this->createPageWithSubjects(
			'GetSubjectLabelsApiTest_Apples',
			otherSubjects: new SubjectMap(
				TestSubject::build( id: 'sTestGSL1111111', label: 'Apple Pie', schemaName: new SchemaName( 'Recipe' ) ),
				TestSubject::build( id: 'sTestGSL1111112', label: 'Apple Tree', schemaName: new SchemaName( 'Plant' ) ),
			)
		);

		$data = $this->executeHandlerAndGetBodyData(
			new GetSubjectLabelsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'search' => 'Apple' ],
			] )
		);

		$this->assertSame( [ 'sTestGSL1111111', 'sTestGSL1111112' ], array_column( $data, 'id' ) );
	}

	/**
	 * What the picker of a relation with several target Schemas sends.
	 */
	public function testASearchNamingSeveralSchemasFindsSubjectsOfEachOfThem(): void {
		$this->createSchema( 'Recipe' );
		$this->createSchema( 'Plant' );
		$this->createSchema( 'Company' );
		$this->createPageWithSubjects(
			'GetSubjectLabelsApiTest_SeveralSchemas',
			otherSubjects: new SubjectMap(
				TestSubject::build( id: 'sTestGSL1111121', label: 'Apple Pie', schemaName: new SchemaName( 'Recipe' ) ),
				TestSubject::build( id: 'sTestGSL1111122', label: 'Apple Tree', schemaName: new SchemaName( 'Plant' ) ),
				TestSubject::build( id: 'sTestGSL1111123', label: 'Apple Inc.', schemaName: new SchemaName( 'Company' ) ),
			)
		);

		$data = $this->executeHandlerAndGetBodyData(
			new GetSubjectLabelsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'search' => 'Apple', 'schema' => 'Recipe|Company' ],
			] )
		);

		$this->assertSame( [ 'sTestGSL1111123', 'sTestGSL1111121' ], array_column( $data, 'id' ) );
	}

}
