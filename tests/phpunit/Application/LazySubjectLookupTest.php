<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\LazySubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\LazySubjectLookup
 */
class LazySubjectLookupTest extends TestCase {

	public function testGetSubjectReturnsTheSubjectFromTheWrappedLookup(): void {
		$subject = TestSubject::build( id: 's11111111111111' );

		$lookup = new LazySubjectLookup(
			static fn (): SubjectLookup => new InMemorySubjectLookup( $subject )
		);

		$this->assertSame( $subject, $lookup->getSubject( new SubjectId( 's11111111111111' ) ) );
	}

	public function testTheWrappedLookupIsNotBuiltAtConstructionTime(): void {
		$lookup = new LazySubjectLookup(
			static fn (): SubjectLookup => throw new RuntimeException( 'The factory ran at construction time.' )
		);

		$this->assertInstanceOf( LazySubjectLookup::class, $lookup );
	}

}
