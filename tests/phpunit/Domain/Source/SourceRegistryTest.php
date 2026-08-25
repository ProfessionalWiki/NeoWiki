<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Source;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Source\Source;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySource;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry
 */
class SourceRegistryTest extends TestCase {

	private const string LOCAL_ID = 's11111111111111';

	public function testBareIdResolvesToTheLocalSource(): void {
		$local = new InMemorySource();
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, $local );

		$this->assertSame( $local, $registry->getSourceOf( new SubjectId( self::LOCAL_ID ) ) );
	}

	public function testQualifiedIdResolvesToItsOwnSource(): void {
		$local = new InMemorySource();
		$other = new InMemorySource();
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, $local );
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, $other );

		$id = new SubjectId( TestSubjectIds::OTHER_SOURCE_KEY . ':' . self::LOCAL_ID );

		$this->assertSame( $other, $registry->getSourceOf( $id ) );
		$this->assertTrue( $registry->canResolve( $id ) );
	}

	public function testUnknownSourceKeyResolvesToNothing(): void {
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, new InMemorySource() );

		$this->assertNull( $registry->getSourceOf( new SubjectId( 'neverinstalled:Q42' ) ) );
		$this->assertFalse( $registry->canResolve( new SubjectId( 'neverinstalled:Q42' ) ) );
	}

	public function testBareIdIsUnresolvableWithoutARegisteredLocalSource(): void {
		$this->assertFalse( $this->newRegistry()->canResolve( new SubjectId( self::LOCAL_ID ) ) );
	}

	public function testReportsTheRegisteredKeys(): void {
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, new InMemorySource() );
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, new InMemorySource() );

		$this->assertSame(
			[ TestSubjectIds::LOCAL_SOURCE_KEY, TestSubjectIds::OTHER_SOURCE_KEY ],
			$registry->getSourceKeys()
		);
	}

	public function testRegisteringAKeyAgainReplacesItsSource(): void {
		$replacement = new InMemorySource();
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, new InMemorySource() );
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, $replacement );

		$this->assertSame( $replacement, $registry->getSource( TestSubjectIds::OTHER_SOURCE_KEY ) );
	}

	public function testRegisteringAMalformedKeyIsRefused(): void {
		$registry = $this->newRegistry();

		$this->expectException( InvalidArgumentException::class );

		$registry->registerSource( '2wiki', new InMemorySource() );
	}

	public function testTheLocalSourceCannotBeReplaced(): void {
		$local = new InMemorySource();
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, $local );

		try {
			$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, new InMemorySource() );
		} catch ( InvalidArgumentException ) {
			$this->assertSame( $local, $registry->getSource( TestSubjectIds::LOCAL_SOURCE_KEY ) );
			return;
		}

		$this->fail( 'Replacing the local Source should be refused' );
	}

	public function testASourceRegisteredAsAFactoryIsBuiltOnceOnFirstUse(): void {
		$constructions = 0;
		$registry = $this->newRegistry();

		$registry->registerSource(
			TestSubjectIds::OTHER_SOURCE_KEY,
			function () use ( &$constructions ): Source {
				$constructions++;
				return new InMemorySource();
			}
		);

		$this->assertSame( 0, $constructions, 'Registering must not build the Source' );

		$first = $registry->getSource( TestSubjectIds::OTHER_SOURCE_KEY );
		$second = $registry->getSource( TestSubjectIds::OTHER_SOURCE_KEY );

		$this->assertSame( 1, $constructions );
		$this->assertSame( $first, $second );
	}

	public function testWithLocalSourceReplacesOnlyTheLocalSource(): void {
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, new InMemorySource() );
		$other = new InMemorySource();
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, $other );

		$replacement = new InMemorySource();
		$copy = $registry->withLocalSource( $replacement );

		$this->assertSame( $replacement, $copy->getSource( TestSubjectIds::LOCAL_SOURCE_KEY ) );
		$this->assertSame( $other, $copy->getSource( TestSubjectIds::OTHER_SOURCE_KEY ) );
	}

	public function testWithLocalSourceLeavesTheRegistryItCopiesAlone(): void {
		$local = new InMemorySource();
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, $local );

		$registry->withLocalSource( new InMemorySource() );

		$this->assertSame( $local, $registry->getSource( TestSubjectIds::LOCAL_SOURCE_KEY ) );
	}

	public function testWithLocalSourceDoesNotBuildTheSourcesItCopies(): void {
		$constructions = 0;
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, $this->countingFactory( $constructions ) );

		$registry->withLocalSource( new InMemorySource() );

		$this->assertSame( 0, $constructions );
	}

	/**
	 * The local key is the wiki's own id rather than anyone's choice here, so it is exempt from the
	 * Source-key grammar: a wiki whose id starts with a digit still has to resolve the bare ids of its
	 * own Subjects.
	 */
	public function testAGrammarInvalidLocalKeyStillRegistersAndResolvesBareIds(): void {
		$local = new InMemorySource();
		$registry = new SourceRegistry( '2wiki' );

		$registry->registerSource( '2wiki', $local );

		$this->assertSame( $local, $registry->getSourceOf( new SubjectId( self::LOCAL_ID ) ) );
		$this->assertTrue( $registry->canResolve( new SubjectId( self::LOCAL_ID ) ) );
	}

	public function testAskingWhetherAnIdResolvesDoesNotBuildItsSource(): void {
		$constructions = 0;
		$registry = $this->newRegistry();
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, $this->countingFactory( $constructions ) );

		$resolvable = $registry->canResolve(
			new SubjectId( TestSubjectIds::OTHER_SOURCE_KEY . ':' . self::LOCAL_ID )
		);

		$this->assertTrue( $resolvable );
		$this->assertSame( 0, $constructions );
	}

	/**
	 * @param int $constructions Counts how often the Source it returns is built.
	 * @return Closure(): Source
	 */
	private function countingFactory( int &$constructions ): Closure {
		return function () use ( &$constructions ): Source {
			$constructions++;
			return new InMemorySource();
		};
	}

	private function newRegistry(): SourceRegistry {
		return new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY );
	}

}
