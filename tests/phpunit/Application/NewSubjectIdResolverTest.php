<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\NewSubjectIdResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\NewSubjectIdResolver
 */
class NewSubjectIdResolverTest extends TestCase {

	private const string LOCAL_SOURCE_KEY = 'thiswiki';
	private const string MINTED = 'sMintedAAAAAAA1';
	private const string SUPPLIED = 'sSuppAAAAAAAAA1';

	public function testMintsAnIdForACallerWhoSuppliedNone(): void {
		$this->assertSame( self::MINTED, $this->newResolver()->resolve( null )->text );
	}

	public function testTakesTheIdTheCallerSupplied(): void {
		$this->assertSame( self::SUPPLIED, $this->newResolver()->resolve( self::SUPPLIED )->text );
	}

	/**
	 * A Subject is only ever created in the local Source, so an id qualified with this wiki's own
	 * key names the same Subject as the bare one and is stored under the bare form.
	 */
	public function testTakesAnIdNamingThisWikiUnderItsBareForm(): void {
		$resolved = $this->newResolver()->resolve( self::LOCAL_SOURCE_KEY . ':' . self::SUPPLIED );

		$this->assertSame( self::SUPPLIED, $resolved->text );
	}

	public function testRejectsAnIdFromAnotherSource(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Subjects can only be created in the local Source' );

		$this->newResolver()->resolve( 'otherwiki:sSuppAAAAAAAAA2' );
	}

	public function testRejectsAMalformedId(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->newResolver()->resolve( 'not-a-subject-id' );
	}

	public function testAnIdNoPageHoldsIsNotInUse(): void {
		$this->assertFalse( $this->newResolver()->isInUse( new SubjectId( self::SUPPLIED ) ) );
	}

	public function testAnIdThePageIndexKnowsIsInUse(): void {
		$resolver = $this->newResolver( [
			[ new SubjectId( self::SUPPLIED ), new PageIdentifiers( new PageId( 42 ), 'Amsterdam', NS_MAIN ) ],
		] );

		$this->assertTrue( $resolver->isInUse( new SubjectId( self::SUPPLIED ) ) );
	}

	/**
	 * @param array<int, array{0: SubjectId, 1: PageIdentifiers}> $indexed
	 */
	private function newResolver( array $indexed = [] ): NewSubjectIdResolver {
		return new NewSubjectIdResolver(
			subjectIdParser: new SubjectIdParser( self::LOCAL_SOURCE_KEY ),
			idGenerator: new StubIdGenerator( substr( self::MINTED, 1 ) ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( $indexed ),
		);
	}

}
