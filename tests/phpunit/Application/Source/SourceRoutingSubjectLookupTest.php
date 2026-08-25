<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Source;

use Psr\Log\LogLevel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\Application\Source\SourceRoutingSubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySource;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Source\SourceRoutingSubjectLookup
 */
class SourceRoutingSubjectLookupTest extends TestCase {

	private const string LOCAL_ID = 's11111111111111';
	private const string OTHER_ID = TestSubjectIds::OTHER_SOURCE_KEY . ':Q42';

	public function testResolvesABareIdThroughTheLocalSource(): void {
		$subject = TestSubject::build( id: self::LOCAL_ID, label: new SubjectLabel( 'Local' ) );

		$this->assertSame(
			$subject,
			$this->newLookup( new InMemorySource( $subject ) )->getSubject( new SubjectId( self::LOCAL_ID ) )
		);
	}

	public function testResolvesAQualifiedIdThroughItsOwnSource(): void {
		$sourced = TestSubject::build( id: self::OTHER_ID, label: new SubjectLabel( 'Elsewhere' ) );

		$this->assertSame(
			$sourced,
			$this->newLookup( new InMemorySource(), new InMemorySource( $sourced ) )
				->getSubject( new SubjectId( self::OTHER_ID ) )
		);
	}

	public function testUnknownSourceResolvesToNoSubject(): void {
		$this->assertNull(
			$this->newLookup( new InMemorySource() )->getSubject( new SubjectId( 'neverinstalled:Q42' ) )
		);
	}

	public function testUnknownSourceIsLoggedAsAWarning(): void {
		$logger = new LegacyLoggerSpy();

		$this->newLookup( new InMemorySource(), logger: $logger )
			->getSubject( new SubjectId( 'neverinstalled:Q42' ) );

		$this->assertCount( 1, $logger->getLogCalls()->getMessages() );
		$this->assertSame( LogLevel::WARNING, $logger->getFirstLogCall()->getLevel() );
		$this->assertStringContainsString( 'neverinstalled:Q42', $logger->getLogCalls()->getMessages()[0] );
	}

	public function testBatchResolvesEachIdThroughItsOwnSource(): void {
		$local = TestSubject::build( id: self::LOCAL_ID, label: new SubjectLabel( 'Local' ) );
		$sourced = TestSubject::build( id: self::OTHER_ID, label: new SubjectLabel( 'Elsewhere' ) );

		$subjects = $this->newLookup( new InMemorySource( $local ), new InMemorySource( $sourced ) )
			->getSubjects( new SubjectIdList( [
				new SubjectId( self::LOCAL_ID ),
				new SubjectId( self::OTHER_ID ),
			] ) );

		$this->assertSame( 2, $subjects->count() );
		$this->assertSame( $local, $subjects->getSubject( new SubjectId( self::LOCAL_ID ) ) );
		$this->assertSame( $sourced, $subjects->getSubject( new SubjectId( self::OTHER_ID ) ) );
	}

	public function testBatchAsksEachSourceOnce(): void {
		$localSource = new InMemorySource(
			TestSubject::build( id: self::LOCAL_ID ),
			TestSubject::build( id: 's11111111111112' ),
			TestSubject::build( id: 's11111111111113' ),
		);

		$this->newLookup( $localSource )->getSubjects( new SubjectIdList( [
			new SubjectId( self::LOCAL_ID ),
			new SubjectId( 's11111111111112' ),
			new SubjectId( 's11111111111113' ),
		] ) );

		$this->assertSame( 1, $localSource->getSubjectsCallCount );
	}

	public function testBatchSkipsIdsOfAnUnknownSource(): void {
		$local = TestSubject::build( id: self::LOCAL_ID );

		$subjects = $this->newLookup( new InMemorySource( $local ) )->getSubjects( new SubjectIdList( [
			new SubjectId( 'neverinstalled:Q42' ),
			new SubjectId( self::LOCAL_ID ),
		] ) );

		$this->assertSame( [ self::LOCAL_ID ], $subjects->getIdsAsTextArray() );
	}

	private function newLookup(
		InMemorySource $localSource,
		?InMemorySource $otherSource = null,
		?LoggerInterface $logger = null
	): SourceRoutingSubjectLookup {
		$registry = new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY );
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, $localSource );

		if ( $otherSource !== null ) {
			$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, $otherSource );
		}

		return new SourceRoutingSubjectLookup( $registry, $logger ?? new NullLogger() );
	}

}
