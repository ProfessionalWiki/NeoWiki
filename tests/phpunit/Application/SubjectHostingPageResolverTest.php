<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver
 */
class SubjectHostingPageResolverTest extends TestCase {

	private const string SUBJECT_ID = 's1acmeaaaaaaaa1';
	private const string OTHER_ID = 's1janeaaaaaaaa2';
	private const string SOURCED_ID = TestSubjectIds::OTHER_SOURCE_KEY . ':Q42';

	public function testResolvesTheHostingPageOfAReadableSubject(): void {
		$hostingPage = new PageIdentifiers( new PageId( 42 ), 'ACME Corp', 0 );

		$resolved = $this->resolver(
			lookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::OTHER_ID ), new PageIdentifiers( new PageId( 99 ), 'Jane Doe', 0 ) ],
				[ new SubjectId( self::SUBJECT_ID ), $hostingPage ],
			] ),
			authorized: true,
		)->resolveReadableHostingPage( new SubjectId( self::SUBJECT_ID ) );

		$this->assertSame( $hostingPage, $resolved, 'The Subject resolves to its own hosting page.' );
	}

	public function testReturnsNullWhenNoPageHostsTheSubject(): void {
		$resolved = $this->resolver(
			lookup: new InMemoryPageIdentifiersLookup(),
			authorized: true,
		)->resolveReadableHostingPage( new SubjectId( self::SUBJECT_ID ) );

		$this->assertNull( $resolved );
	}

	public function testReturnsNullWhenTheHostingPageIsNotReadable(): void {
		$resolved = $this->resolver(
			lookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 42 ), 'ACME Corp', 0 ) ],
			] ),
			authorized: false,
		)->resolveReadableHostingPage( new SubjectId( self::SUBJECT_ID ) );

		$this->assertNull( $resolved, 'A Subject on an unreadable page is not served, so a harvested id cannot be probed.' );
	}

	public function testSubjectFromAnotherSourceIsAnsweredWithoutConsultingTheIndex(): void {
		$lookup = new InMemoryPageIdentifiersLookup();

		$resolved = $this->resolver( lookup: $lookup, authorized: true )
			->resolveReadableHostingPage( new SubjectId( self::SOURCED_ID ) );

		$this->assertNull( $resolved );
		$this->assertSame( 0, $lookup->getPageIdOfSubjectCallCount, 'The index holds local ids alone, so it is not asked.' );
	}

	private function resolver( InMemoryPageIdentifiersLookup $lookup, bool $authorized ): SubjectHostingPageResolver {
		return new SubjectHostingPageResolver(
			$lookup,
			new StubPageReadAuthorizer( $authorized ),
		);
	}

}
