<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlUpdateEndpoint;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * The headline write+query loop, exercised against a REAL, live QLever store (the `test_qlever`
 * dev-stack service) with REAL HTTP — no mocking on either the write or the read side. A page edit that
 * stores a Subject projects into the store over the SPARQL 1.1 Update endpoint; the new SPARQL query
 * service reads it back over the SPARQL 1.1 Query endpoint. Proves the surfaces this PR adds work
 * end-to-end against a real SPARQL 1.1 store, not just against fakes.
 *
 * Deliberately does NOT skip when the store is unreachable: a missing QLEVER_TEST_URL fails the test
 * with a clear message, and an unreachable store surfaces as a loud query/HTTP failure. A silently
 * skipped system test would leave the headline deliverable unverified.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\SparqlPlugin
 * @group Database
 */
class QuerySparqlEndToEndTest extends NeoWikiIntegrationTestCase {

	private const string PAGE_NAME = 'Sparql query system test page';
	private const string LABEL_PREDICATE = 'http://www.w3.org/2000/01/rdf-schema#label';

	private string $storeUrl;
	private ?string $accessToken;

	protected function setUp(): void {
		parent::setUp();

		$this->storeUrl = $this->requireStoreUrl();
		$this->accessToken = getenv( 'QLEVER_TEST_ACCESS_TOKEN' ) ?: null;

		// Replace the integration harness's NullHttpRequestFactory (which blocks all outbound HTTP) with a
		// real one, so both the projection write path and the query read path reach the live store.
		$this->setService( 'HttpRequestFactory', $this->realHttpRequestFactory() );

		$this->overrideConfigValue( 'NeoWikiSparqlStores', [ [
			'updateUrl' => $this->storeUrl,
			'accessToken' => $this->accessToken,
			'projection' => NeoWikiExtension::PROJECTION_NATIVE,
		] ] );
		NeoWikiExtension::resetInstance();

		$this->clearStore();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	protected function tearDown(): void {
		NeoWikiExtension::resetInstance();
		parent::tearDown();
	}

	public function testSubjectLabelRoundTripsThroughTheSparqlQueryService(): void {
		$subjectId = TestSubject::uniqueId();
		$initialLabel = 'System test subject ' . uniqid( '', true );

		// CREATE: a page whose Subject projects into the store.
		$this->savePageWithSubjectLabel( $subjectId, $initialLabel );
		$this->assertSame(
			[ $initialLabel ],
			$this->queryLabelsOf( $subjectId ),
			'the created Subject label must come back through the SPARQL query service'
		);

		// EDIT: the projection is replaced, so the new label is visible and the old one is gone.
		$editedLabel = 'System test subject edited ' . uniqid( '', true );
		$this->savePageWithSubjectLabel( $subjectId, $editedLabel );
		$this->assertSame(
			[ $editedLabel ],
			$this->queryLabelsOf( $subjectId ),
			'the edited Subject label must replace the previous one'
		);

		// DELETE: the page's named graph is dropped, so the binding disappears.
		$this->deletePageUnderTest();
		$this->assertSame(
			[],
			$this->queryLabelsOf( $subjectId ),
			'the deleted Subject must no longer be queryable'
		);
	}

	private function savePageWithSubjectLabel( SubjectId $subjectId, string $label ): void {
		$this->createPageWithSubjects(
			self::PAGE_NAME,
			TestSubject::build( id: $subjectId, label: $label )
		);
		DeferredUpdates::doUpdates();
	}

	/**
	 * @return list<string> The rdfs:label literal values bound to the Subject, via the new query service.
	 */
	private function queryLabelsOf( SubjectId $subjectId ): array {
		$subjectIri = NeoWikiExtension::getInstance()->getRdfNamespaces()->subject( $subjectId )->value;

		$result = NeoWikiExtension::getInstance()->newSparqlQueryService()->execute(
			new SparqlQueryRequest(
				sparql: 'SELECT ?label WHERE { <' . $subjectIri . '> <' . self::LABEL_PREDICATE . '> ?label }',
				limits: new SparqlQueryLimits( 30 ),
			)
		);

		return array_map(
			static fn ( array $binding ): string => $binding['label']['value'],
			$result->document['results']['bindings']
		);
	}

	private function deletePageUnderTest(): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( self::PAGE_NAME ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage( $page, $this->getTestSysop()->getUser() );

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'system test cleanup' ) );
		DeferredUpdates::doUpdates();
	}

	private function clearStore(): void {
		( new HttpSparqlUpdateEndpoint(
			MediaWikiServices::getInstance()->getHttpRequestFactory(),
			$this->storeUrl,
			$this->accessToken,
		) )->postUpdate( 'DROP ALL' );
	}

	private function realHttpRequestFactory(): HttpRequestFactory {
		return new HttpRequestFactory(
			new ServiceOptions(
				HttpRequestFactory::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			LoggerFactory::getInstance( 'http' )
		);
	}

	private function requireStoreUrl(): string {
		$url = getenv( 'QLEVER_TEST_URL' );

		if ( $url === false || trim( $url ) === '' ) {
			$this->fail(
				'QLEVER_TEST_URL is not set. This SPARQL query system test requires a live QLever store '
				. '(the test_qlever dev-stack service). Run it via `make phpunit`, which sets QLEVER_TEST_URL '
				. 'from phpunit.xml.dist and reaches test_qlever in the dev network. It deliberately fails '
				. 'rather than skips, so the write+query loop is never silently left unverified.'
			);
		}

		return $url;
	}

}
