<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialSubject;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * The page is rendered in qqx, so what is asserted is which message the error box and the title use
 * rather than what those messages happen to say in English.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialSubject
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectNamePresenter
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::getSubjectUiJsConfigVars
 * @group Database
 */
class SpecialSubjectTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	private const string SUBJECT_ID = 's1demo8aaaaaab5';
	private const string ABSENT_ID = 's1demo8aaaaaab6';
	private const string INVALID_ID_ERROR = '(neowiki-special-subject-invalid-id)';
	private const string PAGE_DESCRIPTION = '(neowiki-special-subject)';

	private function outputFor( ?string $subPage ): string {
		return $this->executeWith( $subPage )->getHTML();
	}

	public function testMountPointCarriesTheRequestedSubjectId(): void {
		$output = $this->outputFor( self::SUBJECT_ID );

		$this->assertStringContainsString( 'id="ext-neowiki-subject"', $output );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="' . self::SUBJECT_ID . '"', $output );
		$this->assertStringNotContainsString( self::INVALID_ID_ERROR, $output );
	}

	public function testMountPointCarriesASubjectOfAnotherSource(): void {
		$output = $this->outputFor( 'otherwiki:abc-123' );

		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="otherwiki:abc-123"', $output );
		$this->assertStringNotContainsString( self::INVALID_ID_ERROR, $output );
	}

	/**
	 * An id naming this wiki as its Source names a local Subject, so what the page mounts is the bare
	 * local id every other surface uses for it.
	 */
	public function testAnIdNamingThisWikiIsMountedInItsBareForm(): void {
		$localSourceKey = NeoWikiExtension::getInstance()->getSubjectIdParser()->getLocalSourceKey();

		$output = $this->outputFor( $localSourceKey . ':' . self::SUBJECT_ID );

		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="' . self::SUBJECT_ID . '"', $output );
	}

	public function testAMalformedSubjectIdIsRefusedWithoutAMountPoint(): void {
		$output = $this->outputFor( 'not-a-subject-id' );

		$this->assertStringContainsString( self::INVALID_ID_ERROR, $output );
		$this->assertStringNotContainsString( 'id="ext-neowiki-subject"', $output );
	}

	public function testTheBareSpecialPageAsksForASubjectId(): void {
		$output = $this->outputFor( null );

		$this->assertStringContainsString( self::INVALID_ID_ERROR, $output );
		$this->assertStringNotContainsString( 'id="ext-neowiki-subject"', $output );
	}

	/**
	 * The mount point is an empty div until the frontend fills it, so a page that forgot to load the
	 * module renders as a blank page rather than as an error.
	 */
	public function testLoadsTheFrontendModule(): void {
		$out = $this->executeWith( self::SUBJECT_ID );

		$this->assertContains( 'ext.neowiki', $out->getModules() );
	}

	public function testExposesReadableRdfProjectionsAsConfigVar(): void {
		$this->createMapping( 'EDM', '{ "version": 1, "schemas": {} }' );

		$out = $this->executeWith( self::SUBJECT_ID );

		$this->assertSame(
			[ 'native', 'EDM' ],
			$out->getJsConfigVars()['wgNeoWikiRdfProjections']
		);
	}

	public function testOmitsRdfProjectionsTheViewingUserCannotRead(): void {
		$this->createMapping( 'EDM', '{ "version": 1, "schemas": {} }' );

		$out = $this->executeWith( self::SUBJECT_ID, $this->authorityWithGlobalReadButNoPageRead() );

		$this->assertSame(
			[ 'native' ],
			$out->getJsConfigVars()['wgNeoWikiRdfProjections'],
			'A read-restricted Mapping page name must not reach a reader who cannot see it.'
		);
	}

	public function testExposesTheSubjectIriBaseAsConfigVar(): void {
		$this->overrideConfigValue( 'NeoWikiRdfBaseUri', 'https://data.example.org' );
		NeoWikiExtension::resetInstance();

		$out = $this->executeWith( self::SUBJECT_ID );

		$this->assertSame(
			'https://data.example.org/entity/',
			$out->getJsConfigVars()['wgNeoWikiSubjectIriBase']
		);
	}

	/**
	 * The page a concept URI leads to, so its title, the browser tab, a bookmark and a history entry
	 * name the Subject rather than the page showing it.
	 */
	public function testTheTitleIsTheSubjectsName(): void {
		$this->createPageWithSubjects(
			'SpecialSubjectTest_Company',
			mainSubject: TestSubject::build( id: self::SUBJECT_ID, label: 'ACME Inc' )
		);

		$this->assertSame( 'ACME Inc', $this->executeWith( self::SUBJECT_ID )->getPageTitle() );
	}

	public function testTheTitleMarksANameNobodyChoseAsTheStandInItIs(): void {
		$this->createPageWithSubjects(
			'SpecialSubjectTest_Unnamed',
			childSubjects: new SubjectMap( TestSubject::build(
				id: self::SUBJECT_ID,
				label: null,
				schemaName: new SchemaName( 'Company' )
			) )
		);

		$this->assertSame(
			'(neowiki-subject-generated-name: Company)',
			$this->executeWith( self::SUBJECT_ID )->getPageTitle()
		);
	}

	public function testTheTitleStaysThePagesOwnForASubjectTheWikiDoesNotHave(): void {
		$this->assertSame( self::PAGE_DESCRIPTION, $this->executeWith( self::ABSENT_ID )->getPageTitle() );
	}

	/**
	 * Naming a Subject in the title would tell a reader it exists, which the whole page must not do for
	 * one on a page they may not read: that answers exactly as an id the wiki never minted (#1046).
	 */
	public function testASubjectOnAnUnreadablePageRendersExactlyLikeAnAbsentOne(): void {
		$this->createPageWithSubjects(
			'SpecialSubjectTest_Restricted',
			mainSubject: TestSubject::build( id: self::SUBJECT_ID, label: 'Restricted Inc' )
		);
		$restricted = $this->authorityWithGlobalReadButNoPageRead();

		$denied = $this->executeWith( self::SUBJECT_ID, $restricted );
		$absent = $this->executeWith( self::ABSENT_ID, $restricted );

		$this->assertSame( self::PAGE_DESCRIPTION, $denied->getPageTitle() );
		$this->assertSame( $absent->getPageTitle(), $denied->getPageTitle() );
		$this->assertSame(
			str_replace( self::ABSENT_ID, self::SUBJECT_ID, $absent->getHTML() ),
			$denied->getHTML(),
			'Only the Subject id echoed into the mount point may differ.'
		);
	}

	/**
	 * Covers what instantiating the class directly cannot: that extension.json registers the page, and
	 * that it is listed for everyone.
	 */
	public function testThePageIsRegisteredAndListedForEveryone(): void {
		// The context argument is mandatory from MediaWiki 1.47 and ignored before 1.45, so it is always
		// passed (as in SpecialGraphStoresTest).
		$this->assertArrayHasKey(
			'Subject',
			$this->getServiceContainer()->getSpecialPageFactory()
				->getUsablePages( RequestContext::getMain()->getUser(), RequestContext::getMain() )
		);
	}

	/**
	 * Runs the page against a context the test keeps hold of, which exposes the title and the
	 * configuration variables alongside the HTML.
	 */
	private function executeWith( ?string $subPage, ?Authority $authority = null ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( SpecialPage::getTitleFor( 'Subject' ) );
		$context->setAuthority( $authority ?? $this->getTestSysop()->getAuthority() );
		// After the authority, which invalidates the context's cached language.
		$context->setLanguage( 'qqx' );

		$page = new SpecialSubject();
		$page->setContext( $context );
		$page->execute( $subPage );

		return $context->getOutput();
	}

}
