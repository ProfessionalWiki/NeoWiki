<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Search;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHitBuilder;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchTextBuilder;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search\SubjectSearchHitLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingSubjectSearchHitBuilder;
use Psr\Log\LoggerInterface;
use TestLogger;

/**
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search\SubjectSearchHitLookup
 */
class SubjectSearchHitLookupTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	private const string PAGE_NAME = 'SubjectSearchHitLookupTest Museum';

	public function testAPageAnswersOnceHoweverOftenItsRowAsks(): void {
		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( label: 'Rijksmuseum' ) );
		$hits = $this->newHits( $this->newRealBuilder(), new TestLogger() );

		$this->assertSame(
			$hits->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' ),
			$hits->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' )
		);
	}

	public function testWhatARowMayShowIsAnsweredPerReader(): void {
		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( label: 'Rijksmuseum' ) );
		$hits = $this->newHits( $this->newRealBuilder(), new TestLogger() );

		$forAReaderOfThePage = $hits->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' );
		$forAnyoneElse = $hits->forRow(
			$this->authorityWithGlobalReadButNoPageRead(),
			$this->title(),
			[],
			'Rijksmuseum'
		);

		$this->assertNotNull( $forAReaderOfThePage );
		$this->assertNull( $forAnyoneElse );
	}

	public function testAnEngineThatNamesNoTermsIsAttributedFromTheQuery(): void {
		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( label: 'Rijksmuseum' ) );

		$hit = $this->newHits( $this->newRealBuilder(), new TestLogger() )
			->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' );

		$this->assertNotNull( $hit?->match );
		$this->assertSame( [ '', 'Rijksmuseum', '' ], $hit->match->lines[0]->parts );
	}

	public function testAQueryMatchingNoneOfThePagesSubjectsLeavesTheRowUnattributed(): void {
		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( label: 'Rijksmuseum' ) );

		$hit = $this->newHits( $this->newRealBuilder(), new TestLogger() )
			->forRow( $this->authority(), $this->title(), [], 'berlin' );

		$this->assertNotNull( $hit );
		$this->assertNull( $hit->match );
	}

	public function testAFailureToReadAPagesSubjectsCostsOnlyThatRow(): void {
		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( label: 'Rijksmuseum' ) );

		$hits = $this->newHits( $this->newThrowingBuilder(), new TestLogger() );

		$this->assertNull( $hits->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' ) );
	}

	public function testAFailureToReadAPagesSubjectsIsLoggedWithThePageAndTheCause(): void {
		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( label: 'Rijksmuseum' ) );
		$logger = new TestLogger( collect: true, collectContext: true );

		$this->newHits( $this->newThrowingBuilder(), $logger )
			->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' );

		$context = $logger->getBuffer()[0][2] ?? [];

		$this->assertSame( self::PAGE_NAME, $context['page'] ?? null );
		$this->assertSame( ThrowingSubjectSearchHitBuilder::MESSAGE, $context['reason'] ?? null );
	}

	private function newHits( SubjectSearchHitBuilder $hitBuilder, LoggerInterface $logger ): SubjectSearchHitLookup {
		return new SubjectSearchHitLookup(
			$this->getServiceContainer()->getRevisionLookup(),
			$hitBuilder,
			$logger
		);
	}

	private function newRealBuilder(): SubjectSearchHitBuilder {
		$extension = NeoWikiExtension::getInstance();

		return new SubjectSearchHitBuilder(
			new SubjectSearchTextBuilder( $extension->getPropertyTypeLookup(), $extension->getSchemaResolver() )
		);
	}

	private function newThrowingBuilder(): SubjectSearchHitBuilder {
		return new ThrowingSubjectSearchHitBuilder();
	}

	private function title(): Title {
		return Title::newFromTextThrow( self::PAGE_NAME );
	}

	private function authority(): Authority {
		return $this->getTestSysop()->getAuthority();
	}

}
