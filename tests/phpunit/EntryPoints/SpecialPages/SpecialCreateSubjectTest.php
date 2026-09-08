<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use PermissionsError;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialCreateSubject;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialCreateSubject
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\EnforcesRestriction
 * @group Database
 */
class SpecialCreateSubjectTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialCreateSubject {
		return new SpecialCreateSubject();
	}

	public function testOutputContainsMountPointWithoutASchema(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'id="ext-neowiki-create-subject"', $output );
		$this->assertStringNotContainsString( 'data-mw-neowiki-schema', $output );
	}

	public function testSubpageNamesTheSchemaToPin(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( 'Person', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'data-mw-neowiki-schema="Person"', $output );
	}

	public function testAUserWithoutTheEditRightIsRefused(): void {
		$this->setGroupPermissions( '*', 'edit', false );
		$this->setGroupPermissions( 'user', 'edit', false );

		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );
	}

	public function testAUserWithoutTheCreatepageRightIsRefused(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );
	}

}
