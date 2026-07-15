<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SourceRoutingSubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyLogger;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubSource;
use Psr\Log\LogLevel;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SourceRoutingSubjectLookup
 */
class SourceRoutingSubjectLookupTest extends TestCase {

	private const string LOCAL_KEY = 'localwiki';

	public function testRoutesBareIdToTheLocalSource(): void {
		$localSubject = TestSubject::build( id: 's11111111111111' );

		$result = $this->newLookup( localSource: new StubSource( $localSubject ) )
			->getSubject( new SubjectId( 's11111111111111' ) );

		$this->assertSame( $localSubject, $result );
	}

	public function testRoutesLocalQualifiedIdToTheLocalSource(): void {
		$localSubject = TestSubject::build( id: 's11111111111111' );

		$result = $this->newLookup( localSource: new StubSource( $localSubject ) )
			->getSubject( new SubjectId( self::LOCAL_KEY . ':s11111111111111' ) );

		$this->assertSame( $localSubject, $result );
	}

	public function testUnknownSourceReturnsNull(): void {
		$result = $this->newLookup( localSource: new StubSource() )
			->getSubject( new SubjectId( 'unknownwiki:s11111111111111' ) );

		$this->assertNull( $result );
	}

	public function testUnknownSourceLogsOneDiagnosticWarning(): void {
		$logger = new SpyLogger();

		$this->newLookup( localSource: new StubSource(), logger: $logger )
			->getSubject( new SubjectId( 'unknownwiki:s11111111111111' ) );

		$this->assertCount( 1, $logger->getLogCalls() );
		$this->assertSame( LogLevel::WARNING, $logger->getLogCalls()[0]['level'] );
		$this->assertSame( 'unknownwiki', $logger->getLogCalls()[0]['context']['sourceKey'] );
		$this->assertSame( 'unknownwiki:s11111111111111', $logger->getLogCalls()[0]['context']['subjectId'] );
	}

	public function testKnownSourceWithMissingSubjectReturnsNullWithoutLogging(): void {
		$logger = new SpyLogger();

		$result = $this->newLookup( localSource: new StubSource( subject: null ), logger: $logger )
			->getSubject( new SubjectId( 's11111111111111' ) );

		$this->assertNull( $result );
		$this->assertSame( [], $logger->getLogCalls() );
	}

	private function newLookup( StubSource $localSource, ?SpyLogger $logger = null ): SourceRoutingSubjectLookup {
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( 'otherwiki', new StubSource( TestSubject::build( id: 's22222222222222' ) ) );
		$registry->register( self::LOCAL_KEY, $localSource );

		return new SourceRoutingSubjectLookup( $registry, $logger ?? new SpyLogger() );
	}

}
