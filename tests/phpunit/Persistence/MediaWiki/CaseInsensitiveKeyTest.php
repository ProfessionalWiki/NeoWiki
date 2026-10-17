<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CaseInsensitiveKey;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CaseInsensitiveKey
 */
class CaseInsensitiveKeyTest extends TestCase {

	public function testLettersBeyondAsciiAreFolded(): void {
		$this->assertSame( CaseInsensitiveKey::of( 'Émile Zola' ), CaseInsensitiveKey::of( 'émile zola' ) );
	}

	public function testALetterFoldingToSeveralSharesAKeyWithThem(): void {
		$this->assertSame( CaseInsensitiveKey::of( 'Straße' ), CaseInsensitiveKey::of( 'STRASSE' ) );
	}

	/**
	 * Folding turns the mark ypogegrammeni into a letter, so the order its marks are written in has to be made
	 * canonical before folding, or the two spellings fold apart.
	 */
	public function testSpellingsWhoseMarksComeInAnotherOrderShareAKey(): void {
		$this->assertSame(
			CaseInsensitiveKey::of( "\u{03B1}\u{0345}\u{0301}" ),
			CaseInsensitiveKey::of( "\u{03B1}\u{0301}\u{0345}" )
		);
	}

	public function testCuttingALongTextNeverSplitsACharacter(): void {
		$this->assertSame( str_repeat( 'é', 127 ), CaseInsensitiveKey::of( str_repeat( 'é', 200 ) ) );
	}

}
