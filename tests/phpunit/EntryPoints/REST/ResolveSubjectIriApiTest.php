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
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
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
	 * NeoWiki ships Special:Subject as the dereference target (extension.json). The hosting-page tests
	 * opt in explicitly; the default tests leave the setting unset to exercise the shipped default.
	 */
	private function dereferenceToHostingPage(): void {
		$this->overrideConfigValue( 'NeoWikiDereferenceSubjectsToHostingPage', true );
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

	public function testAcceptHtmlRedirectsToTheSubjectsOwnPage(): void {
		$response = $this->deref( headers: [ 'Accept' => 'text/html' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSpecialSubjectLocation( $response );
	}

	public function testWildcardAcceptRedirectsToTheSubjectsOwnPage(): void {
		$response = $this->deref( headers: [ 'Accept' => '*/*' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSpecialSubjectLocation( $response );
	}

	public function testAbsentAcceptRedirectsToTheSubjectsOwnPage(): void {
		$response = $this->deref();

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSpecialSubjectLocation( $response );
	}

	/**
	 * An id naming this wiki as its Source names a local Subject, so the dereference lands on the
	 * bare-id URL every other surface uses for it.
	 */
	public function testAnIdNamingThisWikiRedirectsToItsBareForm(): void {
		$localSourceKey = NeoWikiExtension::getInstance()->getSubjectIdParser()->getLocalSourceKey();

		$response = $this->deref(
			headers: [ 'Accept' => 'text/html' ],
			subjectId: $localSourceKey . ':' . self::SUBJECT_ID
		);

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSpecialSubjectLocation( $response );
	}

	public function testHtmlDereferenceRedirectsToTheHostingPageWhenTheWikiAsksForThat(): void {
		$this->dereferenceToHostingPage();

		$response = $this->deref( headers: [ 'Accept' => 'text/html' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertHostingPageLocation( $response );
	}

	public function testTheHostingPageTargetLeavesTheRdfBranchesUnchanged(): void {
		$this->dereferenceToHostingPage();

		$response = $this->deref( headers: [ 'Accept' => 'application/trig' ] );

		$this->assertSame( 303, $response->getStatusCode() );
		$this->assertSubjectRdfLocation( $response, 'trig' );
	}

	public function testNegotiatedRedirectsVaryOnAccept(): void {
		$this->assertSame( 'Accept', $this->deref( [ 'Accept' => 'application/trig' ] )->getHeaderLine( 'Vary' ) );
		$this->assertSame( 'Accept', $this->deref( [ 'Accept' => 'text/html' ] )->getHeaderLine( 'Vary' ) );
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

	public function testTheBrowserTargetIsGatedOnReadingTheHostingPageToo(): void {
		// The Subject's own page needs no hosting page to build its URL, so without this gate the browser
		// branch would confirm a restricted Subject exists where the RDF branches refuse to (#1046).
		$response = $this->deref(
			headers: [ 'Accept' => 'text/html' ],
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$this->assertSame( 404, $response->getStatusCode() );
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

	private function assertSpecialSubjectLocation( Response $response ): void {
		$location = $response->getHeaderLine( 'Location' );

		$this->assertMatchesRegularExpression( '#^https?://#', $location, 'The Location is an absolute URL.' );
		$this->assertStringEndsWith(
			'Special:Subject/' . self::SUBJECT_ID,
			$location,
			'A browser dereference lands on the Subject\'s own page.'
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
