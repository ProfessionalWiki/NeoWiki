<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\ParseTimePermissionFixtures;

/**
 * A parse that reads the Subjects of another page depends on that page, the way a page depends on a
 * template it uses, so that MediaWiki refreshes the parsed page when the read one changes.
 *
 * Needs the integration base and the database for the real parser, the Lua library, and the pages
 * and subject-to-page index the reads go through.
 *
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\ParserPageDependencyRecorder
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectResolver
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiValueParserFunction
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary
 * @group Database
 */
class CrossPageSubjectReadDependencyTest extends NeoWikiIntegrationTestCase {

	use ParseTimePermissionFixtures;

	private const string PARSED_PAGE = 'CrossPageSubjectReadDependencyTestParsedPage';
	private const string READ_PAGE = 'CrossPageSubjectReadDependencyTestReadPage';
	private const string TARGET_PAGE = 'CrossPageSubjectReadDependencyTestTargetPage';
	private const string MISSING_PAGE = 'CrossPageSubjectReadDependencyTestMissingPage';
	private const string MODULE = 'CrossPageSubjectReadDependencyTest';

	private const string READ_SUBJECT_ID = 'sreadsubject111';
	private const string TARGET_SUBJECT_ID = 'stargetsubject1';

	private const string MODULE_CODE = "local nw = require( 'mw.neowiki' )\n"
		. "local p = {}\n"
		. "function p.getValue( frame ) nw.getValue( 'Motto', { page = frame.args[1] } ) end\n"
		. "function p.getAll( frame ) nw.getAll( 'Motto', { subject = frame.args[1] } ) end\n"
		. "function p.getMainSubject( frame ) nw.getMainSubject( frame.args[1] ) end\n"
		. "function p.getSubject( frame ) nw.getSubject( frame.args[1] ) end\n"
		. "function p.getSubjects( frame ) nw.getSubjects( frame.args[1] ) end\n"
		. "function p.getRelationLabel( frame ) nw.getValue( 'Owner', { page = frame.args[1] } ) end\n"
		. 'return p';

	/**
	 * The read page's Subject has a relation to a Subject on a third page, whose label is read off
	 * that page.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->createPageWithSubjects(
			self::TARGET_PAGE,
			TestSubject::build( id: self::TARGET_SUBJECT_ID, label: 'Target' )
		);

		$this->createPageWithSubjects(
			self::READ_PAGE,
			TestSubject::build(
				id: self::READ_SUBJECT_ID,
				statements: new StatementList( [
					TestStatement::build( 'Motto', 'Read value' ),
					TestStatement::buildRelation( 'Owner', [ TestRelation::build( targetId: self::TARGET_SUBJECT_ID ) ] ),
				] )
			)
		);
	}

	/**
	 * @return string[]
	 */
	private function pagesTheParseDependsOn( string $wikitext, string $parsedPage = self::PARSED_PAGE ): array {
		return array_map(
			fn ( array $template ): string => $this->getServiceContainer()->getTitleFormatter()
				->getPrefixedText( $template['link'] ),
			$this->parserOutputOn( $parsedPage, $wikitext )->getLinkList( ParserOutputLinkTypes::TEMPLATE )
		);
	}

	/**
	 * @return string[]
	 */
	private function pagesTheInvocationDependsOn( string $function, string $argument ): array {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );
		$this->editPage( Title::makeTitle( NS_MODULE, self::MODULE ), new ScribuntoContent( self::MODULE_CODE ) );

		return $this->pagesTheParseDependsOn( '{{#invoke:' . self::MODULE . '|' . $function . '|' . $argument . '}}' );
	}

	public function testReadingAValueByPageNameDependsOnThatPage(): void {
		$this->assertContains(
			self::READ_PAGE,
			$this->pagesTheParseDependsOn( '{{#neowiki_value: Motto | page=' . self::READ_PAGE . ' }}' )
		);
	}

	public function testReadingAValueBySubjectIdDependsOnThePageHostingTheSubject(): void {
		$this->assertContains(
			self::READ_PAGE,
			$this->pagesTheParseDependsOn( '{{#neowiki_value: Motto | subject=' . self::READ_SUBJECT_ID . ' }}' )
		);
	}

	public function testShowingARelationOfItsOwnSubjectDependsOnThePageHostingTheTarget(): void {
		$this->assertContains(
			self::TARGET_PAGE,
			$this->pagesTheParseDependsOn( '{{#neowiki_value: Owner }}', self::READ_PAGE )
		);
	}

	public function testReadingItsOwnSubjectsDoesNotMakeThePageDependOnItself(): void {
		$this->assertNotContains(
			self::READ_PAGE,
			$this->pagesTheParseDependsOn( '{{#neowiki_value: Owner }}', self::READ_PAGE )
		);
	}

	public function testReadingAPageThatDoesNotExistDependsOnIt(): void {
		$this->assertContains(
			self::MISSING_PAGE,
			$this->pagesTheParseDependsOn( '{{#neowiki_value: Motto | page=' . self::MISSING_PAGE . ' }}' )
		);
	}

	/**
	 * Read access is denied first: the name is recorded before the permission check, and reading a
	 * special page's Subjects throws (https://github.com/ProfessionalWiki/NeoWiki/issues/1536).
	 */
	public function testReadingATitleThatCannotBeAPageDoesNotDependOnIt(): void {
		$this->denyAnonymousReadOf( 'Special:Version' );

		$this->assertNotContains(
			'Special:Version',
			$this->pagesTheParseDependsOn( '{{#neowiki_value: Motto | page=Special:Version }}' )
		);
	}

	/**
	 * @dataProvider luaReadProvider
	 */
	public function testLuaReadDependsOnThePageItReadsFrom( string $function, string $argument, string $readPage ): void {
		$this->assertContains( $readPage, $this->pagesTheInvocationDependsOn( $function, $argument ) );
	}

	public static function luaReadProvider(): iterable {
		yield 'getValue by page name' => [ 'getValue', self::READ_PAGE, self::READ_PAGE ];
		yield 'getAll by Subject ID' => [ 'getAll', self::READ_SUBJECT_ID, self::READ_PAGE ];
		yield 'getMainSubject' => [ 'getMainSubject', self::READ_PAGE, self::READ_PAGE ];
		yield 'getSubject' => [ 'getSubject', self::READ_SUBJECT_ID, self::READ_PAGE ];
		yield 'getSubjects' => [ 'getSubjects', self::READ_PAGE, self::READ_PAGE ];
		yield 'a relation label, off the page hosting its target' => [ 'getRelationLabel', self::READ_PAGE, self::TARGET_PAGE ];
	}

}
