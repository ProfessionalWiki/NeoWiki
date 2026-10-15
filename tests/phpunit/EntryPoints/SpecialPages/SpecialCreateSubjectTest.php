<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use MediaWiki\Title\Title;
use PermissionsError;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialCreateSubject;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialCreateSubject
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\EnforcesRestriction
 * @group Database
 */
class SpecialCreateSubjectTest extends SpecialPageTestBase {

	use HelpLinkAssertions;

	protected function newSpecialPage(): SpecialCreateSubject {
		return new SpecialCreateSubject();
	}

	public function testOutputContainsMountPointWithoutASchema(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'id="ext-neowiki-create-subject"', $output );
		$this->assertStringNotContainsString( 'data-mw-neowiki-schema', $output );
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$output = $this->outputOf( $this->newSpecialPage(), $this->getTestUser()->getUser() );

		$this->assertHelpLinkLeadsToTheDocs( $output );
	}

	public function testSubpageNamesTheSchemaToPin(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( 'Person', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'data-mw-neowiki-schema="Person"', $output );
	}

	public function testSubpageUnderscoresBecomeSpacesInTheSchemaName(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( 'Legal_Entity', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'data-mw-neowiki-schema="Legal Entity"', $output );
	}

	public function testASchemaWhoseSubjectsAreDependentIsRefused(): void {
		// The singleton caches Schemas by page and revision id, which repeat across tests' tables.
		NeoWikiExtension::resetInstance();
		$this->editPage(
			Title::makeTitle( NeoWikiExtension::NS_SCHEMA, 'Birth' ),
			new SchemaContent( '{"subjectsAreDependent":true,"propertyDefinitions":{}}' )
		);

		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( 'Birth', null, 'qqx', $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'neowiki-create-subject-error-dependent-subjects', $output );
		$this->assertStringNotContainsString( 'id="ext-neowiki-create-subject"', $output );
	}

	public function testAnUnparseableSubpagePinsNoSchema(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '<', null, null, $this->getTestUser()->getUser() );

		$this->assertStringNotContainsString( 'data-mw-neowiki-schema', $output );
	}

	public function testAUserWithoutTheEditRightIsRefused(): void {
		$this->setGroupPermissions( '*', 'edit', false );
		$this->setGroupPermissions( 'user', 'edit', false );

		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );
	}

	public function testTheSpecialPageIsAlsoReachableAsNewSubject(): void {
		$factory = $this->getServiceContainer()->getSpecialPageFactory();

		[ $name ] = $factory->resolveAlias( 'NewSubject' );

		$this->assertInstanceOf( SpecialCreateSubject::class, $factory->getPage( (string)$name ) );
	}

	public function testAUserWithoutTheCreatepageRightIsRefused(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );
	}

}
