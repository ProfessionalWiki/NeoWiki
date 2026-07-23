<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ResolveSubjectIriApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ResolveSubjectIriApi
 * @group Database
 */
class ResolveSubjectIriApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private const string SCHEMA = 'ResolveSubjectIriApiTestSchema';
	private const string SUBJECT_ID = 'sTestDeref11111';
	private const string ABSENT_ID = 'sTestDeref99999';

	private int $pageId;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema( self::SCHEMA );

		$this->pageId = $this->createPageWithSubjects(
			'ResolveSubjectIriApiTest_City',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Berlin' ),
				schemaName: new SchemaName( self::SCHEMA ),
			),
		)->getPage()->getId();
	}

	/**
	 * @param array<string, string> $headers
	 */
	private function deref( array $headers = [], ?string $subjectId = null, ?Authority $authority = null ): Response {
		return $this->executeHandler(
			new ResolveSubjectIriApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => $subjectId ?? self::SUBJECT_ID ],
				'headers' => $headers,
			] ),
			authority: $authority
		);
	}

	/**
	 * NeoWiki ships the Data tab as the dereference target (extension.json). The plain-hosting-page tests
	 * opt out explicitly; the default test leaves the setting unset to exercise the shipped default.
	 */
	private function disableDataTabDereference(): void {
		$this->overrideConfigValue( 'NeoWikiDereferenceSubjectsToDataTab', false );
	}

	public function testAcceptTriGRedirectsToTheSubjectTriGExport(): void {
		$response = $this->deref( headers: [ 'Accept' => 'application/trig' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSubjectRdfLocation( $response, 'trig' );
	}

	public function testAcceptTurtleRedirectsToTheSubjectTurtleExport(): void {
		$response = $this->deref( headers: [ 'Accept' => 'text/turtle' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSubjectRdfLocation( $response, 'turtle' );
	}

	public function testTriGWinsWhenAcceptListsBothRdfMediaTypes(): void {
		$response = $this->deref( headers: [ 'Accept' => 'text/turtle, application/trig' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSubjectRdfLocation( $response, 'trig' );
	}

	public function testAcceptHtmlRedirectsToThePlainHostingPageWhenDataTabDisabled(): void {
		$this->disableDataTabDereference();

		$response = $this->deref( headers: [ 'Accept' => 'text/html' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertHostingPageLocation( $response );
	}

	public function testWildcardAcceptRedirectsToThePlainHostingPageWhenDataTabDisabled(): void {
		$this->disableDataTabDereference();

		$response = $this->deref( headers: [ 'Accept' => '*/*' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertHostingPageLocation( $response );
	}

	public function testAbsentAcceptRedirectsToThePlainHostingPageWhenDataTabDisabled(): void {
		$this->disableDataTabDereference();

		$response = $this->deref();

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertHostingPageLocation( $response );
	}

	public function testHtmlDereferenceRedirectsToTheSubjectsDataTabRowByDefault(): void {
		// No config override: NeoWiki ships the Data tab as the default dereference target.
		$response = $this->deref( headers: [ 'Accept' => 'text/html' ] );

		$location = $response->getHeaderLine( 'Location' );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertMatchesRegularExpression( '#^https?://#', $location, 'The Location is an absolute URL.' );
		$this->assertStringContainsString(
			Title::newFromID( $this->pageId )->getPrefixedDBkey(),
			$location,
			'The redirect targets the hosting page.'
		);
		$this->assertStringContainsString( 'action=subjects', $location, 'The redirect opens the Data tab.' );
		$this->assertStringEndsWith(
			'#' . self::SUBJECT_ID,
			$location,
			'The fragment is the bare Subject id the Data tab expands and highlights.'
		);
	}

	public function testDataTabTargetLeavesTheRdfBranchesUnchanged(): void {
		$this->overrideConfigValue( 'NeoWikiDereferenceSubjectsToDataTab', true );

		$response = $this->deref( headers: [ 'Accept' => 'application/trig' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSubjectRdfLocation( $response, 'trig' );
	}

	public function testReturns404ForAnUnknownSubject(): void {
		$response = $this->deref( subjectId: self::ABSENT_ID );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertStringContainsString(
			'No NeoWiki data found for subject: ' . self::ABSENT_ID,
			$response->getBody()->getContents()
		);
	}

	public function testSubjectOnAnUnreadablePageIsByteIdenticalToAnAbsentSubject(): void {
		// An existing Subject whose hosting page the caller cannot read must answer exactly like an
		// absent Subject, so the concept URI cannot confirm a harvested Subject id exists (#1046). Only
		// the Subject id echoed in the message differs between the two.
		$denied = $this->deref(
			headers: [ 'Accept' => 'application/trig' ],
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);
		$absent = $this->deref( headers: [ 'Accept' => 'application/trig' ], subjectId: self::ABSENT_ID );

		$this->assertSame( 404, $denied->getStatusCode() );
		$this->assertSame( $absent->getStatusCode(), $denied->getStatusCode() );
		$this->assertSame(
			$absent->getHeaderLine( 'Content-Type' ),
			$denied->getHeaderLine( 'Content-Type' )
		);
		$this->assertSame(
			str_replace( self::ABSENT_ID, self::SUBJECT_ID, $absent->getBody()->getContents() ),
			$denied->getBody()->getContents()
		);
	}

	public function testReturns400ForAMalformedSubjectId(): void {
		$response = $this->deref( subjectId: 'not-a-valid-id' );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertStringContainsString( 'Invalid Subject ID: not-a-valid-id', $response->getBody()->getContents() );
	}

	private function assertSubjectRdfLocation( Response $response, string $format ): void {
		$location = $response->getHeaderLine( 'Location' );

		$this->assertMatchesRegularExpression( '#^https?://#', $location, 'The Location is an absolute URL.' );
		$this->assertStringContainsString( '/neowiki/v0/subject/' . self::SUBJECT_ID . '/rdf', $location );
		$this->assertStringContainsString( 'format=' . $format, $location );
		$this->assertStringNotContainsString(
			'projection=',
			$location,
			'RDF dereferencing targets the native projection without an explicit projection parameter.'
		);
	}

	private function assertHostingPageLocation( Response $response ): void {
		$location = $response->getHeaderLine( 'Location' );

		$this->assertMatchesRegularExpression( '#^https?://#', $location, 'The Location is an absolute URL.' );
		$this->assertSame(
			Title::newFromID( $this->pageId )->getCanonicalURL(),
			$location,
			'A browser dereference lands on the hosting page.'
		);
	}

}
