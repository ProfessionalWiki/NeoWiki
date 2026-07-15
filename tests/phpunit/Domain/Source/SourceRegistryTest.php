<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Source;

use LogicException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubSource;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry
 */
class SourceRegistryTest extends TestCase {

	private const string LOCAL_KEY = 'localwiki';

	public function testRegisteredSourceIsReturnedByItsKey(): void {
		$source = new StubSource();
		$registry = new SourceRegistry( self::LOCAL_KEY );

		$registry->register( 'somewiki', $source );

		$this->assertSame( $source, $registry->getSource( 'somewiki' ) );
	}

	public function testGetSourceReturnsNullForUnregisteredKey(): void {
		$registry = new SourceRegistry( self::LOCAL_KEY );

		$this->assertNull( $registry->getSource( 'somewiki' ) );
	}

	public function testBareIdMapsToTheLocalSource(): void {
		$localSource = new StubSource();
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( 'otherwiki', new StubSource() );
		$registry->register( self::LOCAL_KEY, $localSource );

		$this->assertSame( $localSource, $registry->getSourceForId( new SubjectId( 's11111111111111' ) ) );
	}

	public function testLocalQualifiedIdMapsToTheLocalSource(): void {
		$localSource = new StubSource();
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( 'otherwiki', new StubSource() );
		$registry->register( self::LOCAL_KEY, $localSource );

		$this->assertSame(
			$localSource,
			$registry->getSourceForId( new SubjectId( self::LOCAL_KEY . ':s11111111111111' ) )
		);
	}

	public function testSourceQualifiedIdMapsToItsSource(): void {
		$otherSource = new StubSource();
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( self::LOCAL_KEY, new StubSource() );
		$registry->register( 'otherwiki', $otherSource );

		$this->assertSame(
			$otherSource,
			$registry->getSourceForId( new SubjectId( 'otherwiki:s11111111111111' ) )
		);
	}

	public function testIdWithUnregisteredSourceMapsToNull(): void {
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( self::LOCAL_KEY, new StubSource() );

		$this->assertNull( $registry->getSourceForId( new SubjectId( 'unknownwiki:s11111111111111' ) ) );
	}

	public function testIdWithCaseVariantOfTheLocalKeyMapsToNull(): void {
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( self::LOCAL_KEY, new StubSource() );

		$this->assertNull( $registry->getSourceForId( new SubjectId( 'LOCALWIKI:s11111111111111' ) ) );
	}

	public function testRegisteringAnAlreadyTakenKeyThrows(): void {
		$registry = new SourceRegistry( self::LOCAL_KEY );
		$registry->register( self::LOCAL_KEY, new StubSource() );

		$this->expectException( LogicException::class );
		$registry->register( self::LOCAL_KEY, new StubSource() );
	}

	public function testSourceKeysAreComparedVerbatim(): void {
		$lowerSource = new StubSource();
		$upperSource = new StubSource();
		$registry = new SourceRegistry( self::LOCAL_KEY );

		$registry->register( 'wiki', $lowerSource );
		$registry->register( 'WIKI', $upperSource );

		$this->assertSame( $lowerSource, $registry->getSource( 'wiki' ) );
		$this->assertSame( $upperSource, $registry->getSource( 'WIKI' ) );
	}

}
