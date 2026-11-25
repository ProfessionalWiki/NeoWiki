<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\MemoizingSubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\MemoizingSubjectLookup
 */
class MemoizingSubjectLookupTest extends TestCase {

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string MISSING = 's1cmissing11111';

	private InMemorySubjectLookup $inner;

	protected function setUp(): void {
		$this->inner = new InMemorySubjectLookup( TestSubject::build( id: self::ADA ), TestSubject::build( id: self::GRACE ) );
	}

	public function testReturnsTheSubjectsItIsAskedForThatExist(): void {
		$subjects = $this->newLookup( capacity: 10 )->getSubjects( $this->ids( self::ADA, self::MISSING ) );

		$this->assertSame( [ self::ADA ], $subjects->getIdsAsTextArray() );
	}

	public function testReadsASubjectOnlyOnce(): void {
		$lookup = $this->newLookup( capacity: 10 );

		$lookup->getSubjects( $this->ids( self::ADA ) );
		$lookup->getSubjects( $this->ids( self::ADA, self::GRACE ) );

		$this->assertSame( [ [ self::ADA ], [ self::GRACE ] ], $this->inner->requestedIdBatches );
	}

	public function testRemembersThatASubjectWasNotFound(): void {
		$lookup = $this->newLookup( capacity: 10 );

		$lookup->getSubjects( $this->ids( self::MISSING ) );
		$lookup->getSubjects( $this->ids( self::MISSING ) );

		$this->assertSame( [ [ self::MISSING ] ], $this->inner->requestedIdBatches );
	}

	public function testForgetsTheSubjectReadLongestAgoBeyondItsCapacity(): void {
		$lookup = $this->newLookup( capacity: 1 );

		$lookup->getSubjects( $this->ids( self::ADA ) );
		$lookup->getSubjects( $this->ids( self::GRACE ) );
		$lookup->getSubjects( $this->ids( self::ADA ) );

		$this->assertSame( [ [ self::ADA ], [ self::GRACE ], [ self::ADA ] ], $this->inner->requestedIdBatches );
	}

	private function newLookup( int $capacity ): MemoizingSubjectLookup {
		return new MemoizingSubjectLookup( $this->inner, $capacity );
	}

	private function ids( string ...$ids ): SubjectIdList {
		return new SubjectIdList( array_map( static fn ( string $id ): SubjectId => new SubjectId( $id ), $ids ) );
	}

}
