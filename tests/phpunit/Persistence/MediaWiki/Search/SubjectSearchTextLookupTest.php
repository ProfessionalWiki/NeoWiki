<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Search;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search\SubjectSearchTextLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search\SubjectSearchTextLookup
 */
class SubjectSearchTextLookupTest extends NeoWikiIntegrationTestCase {

	public function testReturnsLabelAndValuesOfThePagesSubjects(): void {
		$revision = $this->createPageWithSubjects( 'Rijksmuseum', $this->museumSubject( 'Amsterdam' ) );
		$this->assertNotNull( $revision );

		$this->assertSame(
			"Rijksmuseum\nAmsterdam",
			$this->newLookup()->getSearchTextForPage( $revision->getPageId() )
		);
	}

	public function testPageWithoutSubjectsHasNoSearchText(): void {
		$pageId = $this->insertPage( 'Plain page', 'Just wikitext' )['id'];

		$this->assertSame( '', $this->newLookup()->getSearchTextForPage( $pageId ) );
	}

	public function testUnknownPageHasNoSearchText(): void {
		$this->assertSame( '', $this->newLookup()->getSearchTextForPage( 4040404 ) );
	}

	public function testReadsTheLatestRevisionOfThePage(): void {
		$firstRevision = $this->createPageWithSubjects( 'Rijksmuseum', $this->museumSubject( 'Amsterdam' ) );
		$this->assertNotNull( $firstRevision );
		$this->changeSubjectsOfPage( 'Rijksmuseum', $this->museumSubject( 'Rotterdam' ) );

		$this->assertSame(
			"Rijksmuseum\nRotterdam",
			$this->newLookup()->getSearchTextForPage( $firstRevision->getPageId() )
		);
	}

	public function testReadsTheRevisionItIsHandedRatherThanTheLatestOne(): void {
		$firstRevision = $this->createPageWithSubjects( 'Rijksmuseum', $this->museumSubject( 'Amsterdam' ) );
		$this->assertNotNull( $firstRevision );
		$this->changeSubjectsOfPage( 'Rijksmuseum', $this->museumSubject( 'Rotterdam' ) );

		$this->assertSame(
			"Rijksmuseum\nAmsterdam",
			$this->newLookup()->getSearchTextForRevision( $firstRevision )
		);
	}

	private function museumSubject( string $city ): Subject {
		return TestSubject::build(
			label: 'Rijksmuseum',
			statements: new StatementList( [ TestStatement::build( property: 'City', value: $city ) ] )
		);
	}

	private function newLookup(): SubjectSearchTextLookup {
		return NeoWikiExtension::getInstance()->newSubjectSearchTextLookup();
	}

}
