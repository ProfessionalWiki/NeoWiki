<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Validation\PageViolationChecker;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageViolationChecker;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyViolationIndex;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\PageViolationChecker
 */
class PageViolationCheckerTest extends TestCase {

	private const int PAGE = 42;

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string MISSING = 's1cmissing11111';

	private SpyViolationIndex $violationIndex;

	protected function setUp(): void {
		$this->violationIndex = new SpyViolationIndex();
	}

	public function testRecordsTheViolationsOfEachSubjectOnThePage(): void {
		$this->checkPageWith(
			$this->person( self::ADA, new StatementList() ),
			$this->person( self::GRACE, new StatementList() )
		);

		$this->assertSame(
			[ [ self::ADA, 'Person', 'Name', 'required' ], [ self::GRACE, 'Person', 'Name', 'required' ] ],
			$this->recorded()
		);
	}

	public function testRecordsThePageAsWithoutViolationsWhenItsSubjectsAreValid(): void {
		$this->checkPageWith( $this->person( self::ADA, $this->named() ) );

		$this->assertSame( [], $this->violationIndex->violationsByPageId[self::PAGE] ?? null );
	}

	public function testRecordsTheTargetOfAViolationAboutARelation(): void {
		$this->checkPageWith(
			$this->person(
				self::ADA,
				$this->named()->withStatement( TestStatement::buildRelation( 'Employer', [ TestRelation::build( targetId: self::MISSING ) ] ) )
			)
		);

		$violation = $this->violationIndex->violationsByPageId[self::PAGE][0];
		$this->assertSame( 'relation-target-not-found', $violation->violation->code );
		$this->assertSame( self::MISSING, $violation->relationTarget?->text );
	}

	public function testRecordsTheSchemaOfASubjectWhoseSchemaIsNotFound(): void {
		$this->checkPageWith( TestSubject::build( id: self::ADA, schemaName: new SchemaName( 'Gone' ) ) );

		$this->assertSame( [ [ self::ADA, 'Gone', null, 'schema-not-found' ] ], $this->recorded() );
	}

	private function person( string $id, StatementList $statements ): Subject {
		return TestSubject::build( id: $id, schemaName: new SchemaName( 'Person' ), statements: $statements );
	}

	private function named(): StatementList {
		return new StatementList( [ TestStatement::build( 'Name', 'Ada' ) ] );
	}

	private function checkPageWith( Subject ...$subjects ): void {
		$this->newChecker( new PageSubjects( null, new SubjectMap( ...$subjects ) ) )->checkPage( new PageId( self::PAGE ) );
	}

	private function newChecker( PageSubjects $pageSubjects ): PageViolationChecker {
		return TestPageViolationChecker::build(
			violationIndex: $this->violationIndex,
			pageSubjects: $pageSubjects,
			schemaLookup: new InMemorySchemaLookup(
				TestSchema::build(
					name: 'Person',
					properties: new PropertyDefinitions( [
						'Name' => TestProperty::buildText( required: true ),
						'Employer' => TestProperty::buildRelation( targetSchemas: [ 'Company' ] ),
					] )
				)
			),
		);
	}

	/**
	 * @return list<array{string, string, ?string, string}> Subject id, Schema, property and code of each Violation recorded for the page
	 */
	private function recorded(): array {
		return array_map(
			static fn ( SubjectViolation $violation ): array => [
				$violation->subjectId->text,
				$violation->schemaName,
				$violation->violation->propertyName === null ? null : (string)$violation->violation->propertyName,
				$violation->violation->code,
			],
			$this->violationIndex->violationsByPageId[self::PAGE] ?? []
		);
	}

}
