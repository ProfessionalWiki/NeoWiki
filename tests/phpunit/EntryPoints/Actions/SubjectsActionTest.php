<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Actions;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction
 */
class SubjectsActionTest extends TestCase {

	public function testActionNameIsSubjects(): void {
		$this->assertSame( 'subjects', SubjectsAction::ACTION_NAME );
	}

	public function testNullTitleIsNotEligible(): void {
		$this->assertFalse( SubjectsAction::isEligibleTitle( null ) );
	}

	public function testNonExistentTitleIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 0 );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testNonContentNamespaceTitleIsNotEligible(): void {
		// Special: namespace is not a content namespace.
		$title = Title::newFromText( 'Special:RecentChanges' );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

}
