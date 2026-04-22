<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Actions;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\ManageSubjectsAction;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Actions\ManageSubjectsAction
 */
class ManageSubjectsActionTest extends TestCase {

	public function testActionNameIsManagesubjects(): void {
		$this->assertSame( 'managesubjects', ManageSubjectsAction::ACTION_NAME );
	}

	public function testNullTitleIsNotEligible(): void {
		$this->assertFalse( ManageSubjectsAction::isEligibleTitle( null ) );
	}

	public function testNonExistentTitleIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 0 );

		$this->assertFalse( ManageSubjectsAction::isEligibleTitle( $title ) );
	}

	public function testNonContentNamespaceTitleIsNotEligible(): void {
		// Special: namespace is not a content namespace.
		$title = Title::newFromText( 'Special:RecentChanges' );

		$this->assertFalse( ManageSubjectsAction::isEligibleTitle( $title ) );
	}

}
