<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Hiding who made a revision creates no revision of its own, so none of the hooks the projection is
 * driven from fires and the graph keeps serving a name MediaWiki no longer shows — readable by anyone
 * the Cypher endpoint is open to (#1246). Changing the visibility of a page's current revision
 * therefore has to reproject the page.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onArticleRevisionVisibilitySet
 * @group Database
 */
class RevisionVisibilityGraphProjectionTest extends NeoWikiIntegrationTestCase {

	private const string PAGE_NAME = 'Page whose author gets hidden';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	public function testHidingTheAuthorOfTheCurrentRevisionTakesTheirNameOutOfTheGraph(): void {
		$author = $this->getTestUser()->getUser();
		$revision = $this->editPageAs( $author );
		$this->assertSame(
			$author->getName(),
			$this->readLastEditor( $revision->getPageId() ),
			'precondition: the graph names the author'
		);

		$this->hideRevisionAuthor( $this->pageTitle(), $revision->getId() );

		$this->assertSame( '', $this->readLastEditor( $revision->getPageId() ) );
	}

	public function testShowingTheAuthorOfTheCurrentRevisionAgainPutsTheirNameBack(): void {
		$author = $this->getTestUser()->getUser();
		$revision = $this->editPageAs( $author );
		$this->hideRevisionAuthor( $this->pageTitle(), $revision->getId() );
		$this->assertSame(
			'',
			$this->readLastEditor( $revision->getPageId() ),
			'precondition: the name is gone'
		);

		$this->showRevisionAuthor( $this->pageTitle(), $revision->getId() );

		$this->assertSame( $author->getName(), $this->readLastEditor( $revision->getPageId() ) );
	}

	/**
	 * Only the current revision is projected, so hiding the author of an older one changes nothing the
	 * graph holds — including the name of the author it does hold.
	 */
	public function testHidingTheAuthorOfAnOlderRevisionLeavesTheProjectedNameAlone(): void {
		$firstAuthor = $this->getTestUser()->getUser();
		$firstRevision = $this->editPageAs( $firstAuthor );

		$lastAuthor = $this->getTestSysop()->getUser();
		$lastRevision = $this->editPageAs( $lastAuthor );

		$this->hideRevisionAuthor( $this->pageTitle(), $firstRevision->getId() );

		$this->assertSame( $lastAuthor->getName(), $this->readLastEditor( $lastRevision->getPageId() ) );
	}

	private function editPageAs( User $author ): RevisionRecord {
		return $this->editPage( self::PAGE_NAME, 'Edited by ' . $author->getName(), '', NS_MAIN, $author )
			->getNewRevision();
	}

	private function pageTitle(): Title {
		return Title::newFromText( self::PAGE_NAME );
	}

	private function readLastEditor( int $pageId ): ?string {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN page.lastEditor AS lastEditor',
			[ 'pageId' => $pageId ]
		);

		return $result->first()->toRecursiveArray()['lastEditor'] ?? null;
	}

}
