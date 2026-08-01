<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlUpdateEndpoint;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * The headline write+query loop, exercised against a REAL, live SPARQL 1.1 store with REAL HTTP — no
 * mocking on either the write or the read side. A page edit that stores a Subject projects into the
 * store over the SPARQL 1.1 Update endpoint; the SPARQL query service reads it back over the SPARQL
 * 1.1 Query endpoint.
 *
 * Two projections of the same wiki sharing one store (#1027) are exercised here too, for the same
 * reason: only a real store shows that their per-page named graphs (#1053) coexist rather than
 * overwrite, and that one query can join across them.
 *
 * Each subclass names one live store, so the loop runs once per SPARQL engine the development stack
 * ships. Running it against more than one is the point: it keeps NeoWiki honest about targeting SPARQL
 * 1.1 itself rather than one implementation's dialect and defaults. Those defaults differ in ways a
 * query can silently come to depend on — most sharply whether an unscoped query sees the named graphs
 * NeoWiki writes into, which SPARQL 1.1 says it does not and QLever nonetheless does.
 *
 * Deliberately does NOT skip when a store is unreachable: an unset store URL fails the test through
 * {@see requireEnv}, and an unreachable store surfaces as a loud query/HTTP failure. A silently
 * skipped system test would leave the headline deliverable unverified.
 */
abstract class QuerySparqlEndToEndTestCase extends NeoWikiIntegrationTestCase {

	private const string PAGE_NAME = 'Sparql query system test page';
	private const string LABEL_PREDICATE = 'http://www.w3.org/2000/01/rdf-schema#label';
	private const string EDM_PROJECTION = 'EDM';
	private const string EDM_AGENT_CLASS = 'http://www.europeana.eu/schemas/edm/Agent';

	private string $updateUrl;
	private string $queryUrl;
	private ?string $accessToken;

	/**
	 * The store's SPARQL 1.1 Update endpoint. Read it from the environment through {@see requireEnv},
	 * so a stack without the store fails the test rather than skipping it.
	 */
	abstract protected function storeUpdateUrl(): string;

	/**
	 * The store's SPARQL 1.1 Query endpoint: the same URL as the update endpoint for a store serving
	 * both on one path, a different one for a store that splits them.
	 */
	abstract protected function storeQueryUrl(): string;

	/**
	 * The HTTP Bearer token both endpoints are called with, or null for a store without authentication.
	 */
	abstract protected function storeAccessToken(): ?string;

	protected function setUp(): void {
		parent::setUp();

		$this->updateUrl = $this->storeUpdateUrl();
		$this->queryUrl = $this->storeQueryUrl();
		$this->accessToken = $this->storeAccessToken();

		// Replace the integration harness's NullHttpRequestFactory (which blocks all outbound HTTP) with a
		// real one, so both the projection write path and the query read path reach the live store.
		$this->setService( 'HttpRequestFactory', $this->realHttpRequestFactory() );

		$this->configureStoresForProjections( RdfPageProjector::PROJECTION );

		$this->clearStore();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	protected function tearDown(): void {
		NeoWikiExtension::resetInstance();
		parent::tearDown();
	}

	/**
	 * Reads a store URL from the environment, failing the test when it is unset. A failure and not a
	 * skip deliberately: a system test that quietly does nothing leaves the write+query loop
	 * unverified, which is the one thing it exists to prove.
	 *
	 * @param string $store Named in the failure message, so the reader knows which store to bring up.
	 */
	protected function requireEnv( string $variable, string $store ): string {
		$value = getenv( $variable );

		if ( $value === false || trim( $value ) === '' ) {
			$this->fail(
				$variable . ' is not set. This SPARQL query system test requires a live ' . $store
				. ' store. Run it via `make phpunit`, which sets the variable from phpunit.xml.dist and '
				. 'reaches the store in the dev network. It deliberately fails rather than skips, so the '
				. 'write+query loop is never silently left unverified.'
			);
		}

		return $value;
	}

	/**
	 * Points one store entry per projection at the single test store, so the projections are siblings in
	 * one index — the shape the development stack ships (native + EDM).
	 */
	private function configureStoresForProjections( string ...$projections ): void {
		$this->overrideConfigValue(
			'NeoWikiSparqlStores',
			array_map(
				fn ( string $projection ): array => [
					'updateUrl' => $this->updateUrl,
					'queryUrl' => $this->queryUrl,
					'accessToken' => $this->accessToken,
					'projection' => $projection,
				],
				$projections
			)
		);
		NeoWikiExtension::resetInstance();
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

	public function testEachProjectionTypesTheSubjectInItsOwnGraphOfTheSharedStore(): void {
		$subjectId = TestSubject::uniqueId();
		$pageId = $this->configureBothProjectionsAndSavePage( $subjectId );

		$classesByGraph = $this->queryClassesByGraphOf( $subjectId );

		$this->assertCount(
			2,
			$classesByGraph,
			'Both projections must survive in the store, neither overwriting the other.'
		);
		$this->assertSame(
			self::EDM_AGENT_CLASS,
			$classesByGraph[ '/graph/EDM/page/' . $pageId ] ?? null,
			'The EDM graph types the Subject with the mapped class.'
		);
		$this->assertStringEndsWith(
			'/schema/' . TestSubject::DEFAULT_SCHEMA_ID,
			$classesByGraph[ '/graph/native/page/' . $pageId ] ?? '',
			'The native graph keeps typing it with its Schema class.'
		);
	}

	public function testOneQueryJoinsDataFromBothProjectionsInTheSharedStore(): void {
		$subjectId = TestSubject::uniqueId();
		$this->configureBothProjectionsAndSavePage( $subjectId );

		$namespaces = NeoWikiExtension::getInstance()->getRdfNamespaces();
		$subjectIri = $namespaces->subject( $subjectId )->value;

		$bindings = $this->runQuery(
			'SELECT ?pageName WHERE {'
			. ' GRAPH ?edmGraph { <' . $subjectIri . '> <' . $namespaces->rdfType()->value . '> <' . self::EDM_AGENT_CLASS . '> }'
			. ' GRAPH ?nativeGraph {'
			. ' ?page <' . $namespaces->term( RdfNamespaces::TERM_MAIN_SUBJECT )->value . '> <' . $subjectIri . '> ;'
			. ' <' . $namespaces->term( RdfNamespaces::TERM_PAGE_NAME )->value . '> ?pageName }'
			. ' }'
		);

		$this->assertSame(
			[ self::PAGE_NAME ],
			array_map( static fn ( array $binding ): string => $binding['pageName']['value'], $bindings ),
			'A single query must combine the mapped class, which only the EDM projection carries, with the '
			. 'page name, which only the native projection carries.'
		);
	}

	public function testDeletingThePageDropsBothProjectionsOfIt(): void {
		$subjectId = TestSubject::uniqueId();
		$this->configureBothProjectionsAndSavePage( $subjectId );

		$this->deletePageUnderTest();

		$this->assertSame(
			[],
			$this->queryClassesByGraphOf( $subjectId ),
			'A delete must drop the page graph of every projection, not only the queried store\'s own.'
		);
	}

	/**
	 * Reconfigures the store to hold both the native and an EDM projection — the latter through a Mapping
	 * typing the test Schema as edm:Agent — then saves one page carrying the Subject.
	 *
	 * @return int The id of the saved page.
	 */
	private function configureBothProjectionsAndSavePage( SubjectId $subjectId ): int {
		$schemaName = TestSubject::DEFAULT_SCHEMA_ID;

		$this->createMapping( self::EDM_PROJECTION, <<<JSON
			{
				"version": 1,
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/"
				},
				"schemas": {
					"$schemaName": {
						"subject": { "class": "edm:Agent" },
						"properties": {}
					}
				}
			}
			JSON );

		$this->configureStoresForProjections( RdfPageProjector::PROJECTION, self::EDM_PROJECTION );

		$pageId = $this->createPageWithSubjects(
			self::PAGE_NAME,
			TestSubject::build( id: $subjectId, label: 'Sibling projection system test subject' )
		)->getPage()->getId();
		DeferredUpdates::doUpdates();

		return $pageId;
	}

	/**
	 * The class each named graph types the Subject with, keyed by the graph IRI with the wiki's RDF base
	 * URI stripped so the keys are the projection-qualified paths.
	 *
	 * @return array<string, string>
	 */
	private function queryClassesByGraphOf( SubjectId $subjectId ): array {
		$namespaces = NeoWikiExtension::getInstance()->getRdfNamespaces();
		$subjectIri = $namespaces->subject( $subjectId )->value;

		$bindings = $this->runQuery(
			'SELECT ?graph ?class WHERE { GRAPH ?graph { <' . $subjectIri . '> <'
			. $namespaces->rdfType()->value . '> ?class } }'
		);

		$classesByGraph = [];

		foreach ( $bindings as $binding ) {
			$graphPath = str_replace( $namespaces->baseUri, '', $binding['graph']['value'] );
			$classesByGraph[$graphPath] = $binding['class']['value'];
		}

		return $classesByGraph;
	}

	private function savePageWithSubjectLabel( SubjectId $subjectId, string $label ): void {
		$this->createPageWithSubjects(
			self::PAGE_NAME,
			TestSubject::build( id: $subjectId, label: $label )
		);
		DeferredUpdates::doUpdates();
	}

	/**
	 * @return list<string> The rdfs:label literal values bound to the Subject, via the query service.
	 */
	private function queryLabelsOf( SubjectId $subjectId ): array {
		$subjectIri = NeoWikiExtension::getInstance()->getRdfNamespaces()->subject( $subjectId )->value;

		// GRAPH-scoped, because NeoWiki writes each page into a named graph and never the default
		// graph. An unscoped pattern matches only the default graph under SPARQL 1.1, so it finds
		// nothing on a conforming store; it appears to work only against a store configured to union
		// the named graphs in, as QLever unconditionally does.
		return array_map(
			static fn ( array $binding ): string => $binding['label']['value'],
			$this->runQuery(
				'SELECT ?label WHERE { GRAPH ?graph { <' . $subjectIri . '> <'
				. self::LABEL_PREDICATE . '> ?label } }'
			)
		);
	}

	/**
	 * @return list<array<string, array<string, string>>> The query's solution bindings.
	 */
	private function runQuery( string $sparql ): array {
		return NeoWikiExtension::getInstance()->newSparqlQueryService()->execute(
			new SparqlQueryRequest(
				sparql: $sparql,
				limits: new SparqlQueryLimits( 30 ),
			)
		)->document['results']['bindings'];
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
			$this->updateUrl,
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

}
