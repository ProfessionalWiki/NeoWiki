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
use Psr\Log\LogLevel;
use TestLogger;

/**
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search\SubjectSearchHitLookup
 */
class SubjectSearchHitLookupTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	private const string PAGE_NAME = 'SubjectSearchHitLookupTest Museum';
	private const string OTHER_PAGE_NAME = 'SubjectSearchHitLookupTest Gallery';
	private const string OTHER_ID = 's22222222222222';

	protected function setUp(): void {
		parent::setUp();

		$this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( label: 'Rijksmuseum' ) );
	}

	public function testWhatARowMayShowIsAnsweredPerReader(): void {
		$hits = $this->newRealHits();

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
		$hit = $this->newRealHits()->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' );

		$this->assertNotNull( $hit?->match );
	}

	public function testEachPageAnswersForItself(): void {
		$this->createPageWithSubjects( self::OTHER_PAGE_NAME, TestSubject::build( id: self::OTHER_ID, label: 'Gemaldegalerie' ) );
		$hits = $this->newRealHits();
		$hits->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' );

		$otherHit = $hits->forRow( $this->authority(), Title::newFromTextThrow( self::OTHER_PAGE_NAME ), [], 'Rijksmuseum' );

		$this->assertSame( self::OTHER_ID, $otherHit?->landing?->subjectId->text );
	}

	public function testAnEngineThatNamesTermsIsAttributedFromThemRatherThanTheQuery(): void {
		$hit = $this->newRealHits()->forRow( $this->authority(), $this->title(), [ '\brijksmuseum\b' ], 'berlin' );

		$this->assertNotNull( $hit?->match );
	}

	public function testAQueryMatchingNoneOfThePagesSubjectsLeavesTheRowUnattributed(): void {
		$hit = $this->newRealHits()->forRow( $this->authority(), $this->title(), [], 'berlin' );

		$this->assertNotNull( $hit );
		$this->assertNull( $hit->match );
	}

	public function testAFailureToReadAPagesSubjectsLeavesTheRowPlain(): void {
		$hits = $this->newHits( new ThrowingSubjectSearchHitBuilder(), new TestLogger() );

		$this->assertNull( $hits->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' ) );
	}

	public function testAFailureToReadAPagesSubjectsIsLoggedAsAWarning(): void {
		$logger = new TestLogger( collect: true );

		$this->newHits( new ThrowingSubjectSearchHitBuilder(), $logger )
			->forRow( $this->authority(), $this->title(), [], 'Rijksmuseum' );

		$this->assertSame( [ LogLevel::WARNING ], array_column( $logger->getBuffer(), 0 ) );
	}

	private function newHits( SubjectSearchHitBuilder $hitBuilder, LoggerInterface $logger ): SubjectSearchHitLookup {
		return new SubjectSearchHitLookup(
			$this->getServiceContainer()->getRevisionLookup(),
			$hitBuilder,
			$logger
		);
	}

	private function newRealHits(): SubjectSearchHitLookup {
		$extension = NeoWikiExtension::getInstance();

		return $this->newHits(
			new SubjectSearchHitBuilder(
				new SubjectSearchTextBuilder( $extension->getPropertyTypeLookup(), $extension->getSchemaResolver() ),
				$extension->newSubjectNamer()
			),
			new TestLogger()
		);
	}

	private function title(): Title {
		return Title::newFromTextThrow( self::PAGE_NAME );
	}

	private function authority(): Authority {
		return $this->getTestSysop()->getAuthority();
	}

}
