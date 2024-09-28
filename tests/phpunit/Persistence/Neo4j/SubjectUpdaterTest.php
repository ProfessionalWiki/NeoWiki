<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\TransactionInterface;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\TextFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatLookup;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatRegistry;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j\SubjectUpdater;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use Psr\Log\LogLevel;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j\SubjectUpdater
 */
class SubjectUpdaterTest extends TestCase {

	private const string SCHEMA_NAME = 'SubjectUpdaterTestSchema';

	private TransactionInterface $transaction;
	private InMemorySchemaLookup $schemaLookup;
	private PageId $pageId;
	private LegacyLoggerSpy $logger;
	private Subject $subject;

	protected function setUp(): void {
		$this->transaction = $this->createMock( TransactionInterface::class );
		$this->pageId = new PageId( 1333333337 );

		$this->schemaLookup = new InMemorySchemaLookup();
		$this->logger = new LegacyLoggerSpy();

		$subjectId = new SubjectId( 'sTestSUT1111111' );
		$this->subject = new Subject(
			$subjectId,
			new SubjectLabel( 'Test Label' ),
			new SchemaName( self::SCHEMA_NAME ),
			new StatementList( [] )
		);
	}

	private function newSubjectUpdater( ValueFormatLookup $valueFormatLookup = null ): SubjectUpdater {
		return new SubjectUpdater(
			$this->transaction,
			$this->pageId,
			$this->schemaLookup,
			$valueFormatLookup ?? NeoWikiExtension::getInstance()->getValueFormatLookup(),
			$this->logger
		);
	}

	public function testUpdateSubjectWithMissingSchemaDoesNotRunTransaction(): void {
		$this->transaction
			->expects( $this->never() )
			->method( 'run' );

		$this->newSubjectUpdater()->updateSubject( $this->subject, false );
	}

	public function testUpdateSubjectWithMissingSchemaLogsWarning(): void {
		$this->newSubjectUpdater()->updateSubject( $this->subject, false );

		$this->assertSame(
			[ 'Schema not found: SubjectUpdaterTestSchema' ],
			$this->logger->getLogCalls()->getMessages()
		);
		$this->assertSame(
			LogLevel::WARNING,
			$this->logger->getFirstLogCall()->getLevel()
		);
	}

	public function testSkipsStatementsWithUnknownFormat(): void {
		$lookup = new ValueFormatRegistry();
		$lookup->registerFormat( new TextFormat() );

		$statements = new StatementList( [
			TestStatement::build( property: 'P1', value: new StringValue( 'foo' ), format: 'text' ),
			TestStatement::build( property: 'P2', value: new StringValue( 'https://bar.com' ), format: 'url' ),
			TestStatement::build( property: 'P3', value: new StringValue( 'baz' ), format: 'text' ),
		] );

		$this->assertEquals(
			[
				'P1' => [ 'foo' ],
				'P3' => [ 'baz' ],
			],
			$this->newSubjectUpdater( $lookup )->statementsToNodeProperties( $statements )
		);
	}

	public function testSkipsStatementsWithRelationFormat(): void {
		$lookup = new ValueFormatRegistry();
		$lookup->registerFormat( new TextFormat() );

		$statements = new StatementList( [
			TestStatement::build( property: 'P1', value: new StringValue( 'foo' ), format: 'text' ),
			TestStatement::build( property: 'P2', value: new RelationValue( TestRelation::build() ), format: 'relation' ),
			TestStatement::build( property: 'P3', value: new StringValue( 'baz' ), format: 'text' ),
		] );

		$this->assertEquals(
			[
				'P1' => [ 'foo' ],
				'P3' => [ 'baz' ],
			],
			$this->newSubjectUpdater( $lookup )->statementsToNodeProperties( $statements )
		);
	}

}
