<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use Laudis\Neo4j\Contracts\TransactionInterface;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\SubjectUpdater;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;
use Psr\Log\LogLevel;

class SubjectUpdaterTest extends TestCase {

	private TransactionInterface $transaction;
	private InMemorySchemaLookup $schemaLookup;
	private PageId $pageId;
	private LegacyLoggerSpy $logger;
	private Subject $subject;
	private SubjectUpdater $subjectUpdater;
	private SchemaId $schemaId;

	protected function setUp(): void {
		$this->transaction = $this->createMock( TransactionInterface::class );
		$this->pageId = new PageId( 1 );

		$this->schemaLookup = new InMemorySchemaLookup();
		$this->logger = new LegacyLoggerSpy();

		$subjectId = new SubjectId( '00000000-0000-0000-0015-000000000000' );
		$this->schemaId = new SchemaId( 'null' );
		$this->subject = new Subject(
			$subjectId,
			new SubjectLabel( 'Test Label' ),
			$this->schemaId,
			new StatementList( [] )
		);

		$this->subjectUpdater = new SubjectUpdater(
			$this->schemaLookup,
			$this->transaction,
			$this->pageId,
			$this->logger
		);
	}

	public function testUpdateSubjectWithMissingSchemaDoesNotRunTransaction(): void {
		$this->transaction
			->expects( $this->never() )
			->method( 'run' );

		$this->subjectUpdater->updateSubject( $this->subject, false );
	}

	public function testUpdateSubjectWithMissingSchemaLogsWarning(): void {
		$this->subjectUpdater->updateSubject( $this->subject, false );

		$this->assertCount( 1, $this->logger->getLogCalls() );
		$firstLogCall = $this->logger->getFirstLogCall();

		$this->assertSame( 'Schema not found: ' . $this->schemaId->getText(), $firstLogCall->getMessage() );
		$this->assertSame( LogLevel::WARNING, $firstLogCall->getLevel() );
	}

}
