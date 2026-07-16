<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Importing a page projects its Subjects into the graph. Imported revisions bypass the edit path, so
 * RevisionFromEditComplete never fires for them. AfterImportPage is fired by WikiImporter itself and so
 * covers every import path, including importDump.php, which no reporter wraps.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onAfterImportPage
 * @group Database
 */
class ImportGraphProjectionTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testImportedPageProjectsItsSubjects(): void {
		$this->createPageWithSubjects(
			'Import source',
			TestSubject::build( label: 'Imported subject' )
		);
		$xml = $this->exportPageToXml( 'Import source' );
		$this->emptyTheGraph();

		$this->importXml( str_replace( 'Import source', 'Import target', $xml ) );

		$this->assertSame(
			[ 'Imported subject' ],
			$this->readSubjectLabels( $this->pageIdOf( 'Import target' ) )
		);
	}

	public function testImportedPageNodeUsesTheTitleItWasImportedUnder(): void {
		$this->createPageWithSubjects( 'Rename source', TestSubject::build() );
		$xml = $this->exportPageToXml( 'Rename source' );
		$this->emptyTheGraph();

		$this->importXml( str_replace( 'Rename source', 'Rename target', $xml ) );

		$this->assertSame( 'Rename target', $this->readPageNodeName( $this->pageIdOf( 'Rename target' ) ) );
	}

	public function testImportingNewerRevisionUpdatesProjectionOfExistingPage(): void {
		$this->createPageWithSubjects( 'Update source', TestSubject::build( label: 'Before import' ) );
		$outdatedXml = $this->exportPageToXml( 'Update source' );
		$this->createPageWithSubjects( 'Update source', TestSubject::build( label: 'After import' ) );
		$currentXml = $this->exportPageToXml( 'Update source' );
		$this->emptyTheGraph();

		$this->importXml( str_replace( 'Update source', 'Update target', $outdatedXml ) );
		$this->importXml( str_replace( 'Update source', 'Update target', $currentXml ) );

		$this->assertSame(
			[ 'After import' ],
			$this->readSubjectLabels( $this->pageIdOf( 'Update target' ) ),
			'the projection must follow the newly imported revision, not the one it replaced'
		);
	}

	public function testImportedPageWithoutSubjectSlotIsNotProjected(): void {
		$this->insertPage( 'Plain source', 'Just wikitext, no subjects.' );
		$xml = $this->exportPageToXml( 'Plain source' );
		$this->emptyTheGraph();

		$this->importXml( str_replace( 'Plain source', 'Plain target', $xml ) );

		$this->assertSame( 0, $this->countPageNodes( $this->pageIdOf( 'Plain target' ) ) );
	}

	/**
	 * Stands in for importing into another wiki, whose graph knows nothing of the exported page. Without
	 * this the source page's own projection stays behind, and since Subject nodes are keyed globally, the
	 * source and target pages would share one Subject node — leaving the assertions unable to tell a
	 * subject the import projected from one the source page had already put there.
	 */
	private function emptyTheGraph(): void {
		$this->writeGraph( 'MATCH (n) DETACH DELETE n' );
	}

	private function pageIdOf( string $pageName ): int {
		$pageId = Title::newFromText( $pageName )->getArticleID();

		$this->assertNotSame( 0, $pageId, "the import should have created {$pageName}" );

		return $pageId;
	}

	/**
	 * @return string[]
	 */
	private function readSubjectLabels( int $pageId ): array {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId})-[:HasSubject]->(subject:Subject) RETURN subject.name AS name ORDER BY name',
			[ 'pageId' => $pageId ]
		);

		return array_column( $result->toRecursiveArray(), 'name' );
	}

	private function countPageNodes( int $pageId ): int {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN count(page) AS count',
			[ 'pageId' => $pageId ]
		);

		return (int)$result->first()->toRecursiveArray()['count'];
	}

}
