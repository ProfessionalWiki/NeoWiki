<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\PageProjector;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageLoader;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfSubjectExporter;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedPageProjector;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\RdfSubjectExporter
 */
class RdfSubjectExporterTest extends TestCase {

	private const string SUBJECT_ID = 's1acmeaaaaaaaa1';
	private const string OTHER_ID = 's1janeaaaaaaaa2';

	public function testReturnsNullWhenTheSubjectIsNotInTheGraph(): void {
		$exporter = $this->exporter(
			lookup: new InMemoryPageIdentifiersLookup(),
			loadedPage: $this->pageWithTheSubject(),
			authorized: true,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testReturnsNullWhenTheHostingPageIsNotReadable(): void {
		$exporter = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: $this->pageWithTheSubject(),
			authorized: false,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testReturnsNullWhenTheHostingPageCannotBeLoaded(): void {
		$exporter = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: null,
			authorized: true,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testReturnsNullWhenTheGraphPointsToAPageThatNoLongerHasTheSubject(): void {
		// Stale graph: it resolves the Subject to a page whose current revision no longer carries it.
		$exporter = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::OTHER_ID ) ),
			authorized: true,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testSerializesTheProjectedSubjectWhenItIsPresentAndReadable(): void {
		$quads = new QuadList(
			new Quad(
				new Iri( 'https://wiki.example/entity/' . self::SUBJECT_ID ),
				new Iri( 'http://www.w3.org/2000/01/rdf-schema#label' ),
				RdfLiteralFactory::typed( 'ACME Corp', 'string' ),
				new Iri( 'https://wiki.example/graph/native/page/42' ),
			)
		);

		$document = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: $this->pageWithTheSubject(),
			authorized: true,
			projector: new FixedPageProjector( $quads ),
		)->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG );

		$this->assertSame(
			( new HardfRdfSerializer( [] ) )->serialize( $quads, RdfFormat::TriG ),
			$document,
			'The document is the projected Subject serialized in the requested format.'
		);
	}

	private function exporter(
		InMemoryPageIdentifiersLookup $lookup,
		?Page $loadedPage,
		bool $authorized,
		?PageProjector $projector = null
	): RdfSubjectExporter {
		return new RdfSubjectExporter(
			$lookup,
			$this->fixedLoader( $loadedPage ),
			$projector ?? new FixedPageProjector( new QuadList() ),
			new HardfRdfSerializer( [] ),
			new StubPageReadAuthorizer( $authorized ),
		);
	}

	private function lookupResolvingTheSubject(): InMemoryPageIdentifiersLookup {
		return new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 42 ), 'ACME Corp', 0 ) ],
		] );
	}

	private function pageWithTheSubject(): Page {
		return TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::SUBJECT_ID ) );
	}

	private function fixedLoader( ?Page $page ): RdfPageLoader {
		return new class( $page ) extends RdfPageLoader {

			public function __construct( private readonly ?Page $page ) {
			}

			public function loadByPageId( PageId $pageId ): ?Page {
				return $this->page;
			}

		};
	}

}
