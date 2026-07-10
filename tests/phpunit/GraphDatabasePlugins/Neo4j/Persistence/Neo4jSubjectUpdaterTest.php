<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use DateTimeImmutable;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdater;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use Psr\Log\LogLevel;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdater
 */
class Neo4jSubjectUpdaterTest extends TestCase {

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

	private function newSubjectUpdater( Neo4jValueBuilderRegistry $valueBuilderRegistry = null ): Neo4jSubjectUpdater {
		return new Neo4jSubjectUpdater(
			$this->transaction,
			$this->pageId,
			$this->schemaLookup,
			$valueBuilderRegistry ?? Neo4jValueBuilderRegistry::withCoreBuilders(),
			$this->logger,
			'test_wiki',
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

	public function testSkipsStatementsWithUnknownPropertyType(): void {
		$registry = new Neo4jValueBuilderRegistry();
		$registry->registerBuilder( 'text', static fn( $value ) => $value->toScalars() );

		$statements = new StatementList( [
			TestStatement::build( property: 'P1', value: new StringValue( 'foo' ), propertyType: 'text' ),
			TestStatement::build( property: 'P2', value: new StringValue( 'https://bar.com' ), propertyType: 'url' ),
			TestStatement::build( property: 'P3', value: new StringValue( 'baz' ), propertyType: 'text' ),
		] );

		$this->assertEquals(
			[
				'P1' => [ 'foo' ],
				'P3' => [ 'baz' ],
			],
			$this->newSubjectUpdater( $registry )->statementsToNodeProperties( $statements )
		);
	}

	public function testConvertsDateTimeStatementValuesToDateTimeObjects(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$statements = new StatementList( [
			TestStatement::build( property: 'P1', value: new StringValue( 'plain' ), propertyType: 'text' ),
			TestStatement::build(
				property: 'P2',
				value: new StringValue( '2024-01-01T12:00:00Z' ),
				propertyType: 'dateTime'
			),
		] );

		$this->assertEquals(
			[
				'P1' => [ 'plain' ],
				'P2' => [ new DateTimeImmutable( '2024-01-01T12:00:00Z' ) ],
			],
			$this->newSubjectUpdater( $registry )->statementsToNodeProperties( $statements )
		);
	}

	public function testWarnsWhenDateTimeValuesAreDroppedFromTheProjection(): void {
		$statements = new StatementList( [
			TestStatement::build(
				property: 'P1',
				value: new StringValue( '2024-01-01T12:00:00Z', 'not a datetime', '2025-06-15T08:30:00+02:00' ),
				propertyType: 'dateTime'
			),
		] );

		$this->newSubjectUpdater()->statementsToNodeProperties( $statements );

		$this->assertSame(
			[ 'Dropped 1 unpersistable value(s) of property "P1" on page 1333333337 when projecting to the graph' ],
			$this->logger->getLogCalls()->getMessages()
		);
	}

	public function testDoesNotWarnWhenAllDateTimeValuesPersist(): void {
		$statements = new StatementList( [
			TestStatement::build(
				property: 'P1',
				value: new StringValue( '2024-01-01T12:00:00Z', '2025-06-15T08:30:00+02:00' ),
				propertyType: 'dateTime'
			),
		] );

		$this->newSubjectUpdater()->statementsToNodeProperties( $statements );

		$this->assertSame( [], $this->logger->getLogCalls()->getMessages() );
	}

	public function testSkipsStatementsWithRelationType(): void {
		$registry = Neo4jValueBuilderRegistry::withCoreBuilders();

		$statements = new StatementList( [
			TestStatement::build( property: 'P1', value: new StringValue( 'foo' ), propertyType: 'text' ),
			TestStatement::build( property: 'P2', value: new RelationValue( TestRelation::build() ), propertyType: 'relation' ),
			TestStatement::build( property: 'P3', value: new StringValue( 'baz' ), propertyType: 'text' ),
		] );

		$this->assertEquals(
			[
				'P1' => [ 'foo' ],
				'P3' => [ 'baz' ],
			],
			$this->newSubjectUpdater( $registry )->statementsToNodeProperties( $statements )
		);
	}

}
