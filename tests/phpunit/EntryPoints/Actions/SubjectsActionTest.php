<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Actions;

use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction
 */
class SubjectsActionTest extends MediaWikiIntegrationTestCase {

	public function testNullTitleIsNotEligible(): void {
		$this->assertFalse( SubjectsAction::isEligibleTitle( null ) );
	}

	public function testNonExistentTitleIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 0 );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testTitleInNonContentNamespaceIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( 'getNamespace' )->willReturn( NS_USER_TALK );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testTitleInContentNamespaceIsEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( 'getNamespace' )->willReturn( NS_MAIN );

		$this->assertTrue( SubjectsAction::isEligibleTitle( $title ) );
	}

}
