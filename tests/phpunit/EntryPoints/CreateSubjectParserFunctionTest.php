<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Interwiki\ClassicInterwikiLookup;
use MediaWiki\Language\RawMessage;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\CreateSubjectParserFunction;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;

/**
 * Runs against a real wiki: title existence, content namespaces and normalization are MediaWiki's answers.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\CreateSubjectParserFunction
 * @group Database
 */
class CreateSubjectParserFunctionTest extends NeoWikiIntegrationTestCase {

	private const string SCHEMA_NAME = 'Person';
	private const string MAIN_SUBJECT_ID = 's11111111111111';

	private Title $contentPage;
	private Title $helpPage;
	private InMemorySubjectRepository $subjectRepository;

	public function setUp(): void {
		parent::setUp();

		$this->contentPage = $this->getExistingTestPage( 'A content page' )->getTitle();
		$this->helpPage = $this->getExistingTestPage( 'Help:Using subjects' )->getTitle();

		$this->createSchema( self::SCHEMA_NAME );
		$this->subjectRepository = new InMemorySubjectRepository();

		$this->givePageAMainSubject( $this->contentPage );
	}

	private function givePageAMainSubject( Title $title ): void {
		$this->subjectRepository->savePageSubjects(
			new PageSubjects(
				new Subject(
					id: new SubjectId( self::MAIN_SUBJECT_ID ),
					label: new SubjectLabel( 'Main' ),
					schema: TestSchema::reference( self::SCHEMA_NAME ),
					statements: new StatementList(),
				),
				new SubjectMap()
			),
			new PageId( $title->getId() )
		);
	}

	public function testEmitsButtonPlaceholderWithoutArguments(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->helpPage ) );

		$this->assertStringNotContainsString( 'data-mw-neowiki-schema', $html );
		$this->assertStringNotContainsString( 'data-mw-neowiki-text', $html );
		$this->assertStringNotContainsString( 'data-mw-neowiki-page=', $html );
	}

	public function testIgnoresAnEmptyArgument(): void {
		// {{#create_subject:}} reaches the hook as one empty argument.
		$this->assertRendersButton( $this->callOn( $this->helpPage, '' ) );
	}

	public function testEmitsTheSchemaAndTextAttributes(): void {
		$html = $this->assertRendersButton(
			$this->callOn( $this->contentPage, 'schema=' . self::SCHEMA_NAME, 'text=Add a person' )
		);

		$this->assertStringContainsString( 'data-mw-neowiki-schema="' . self::SCHEMA_NAME . '"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-text="Add a person"', $html );
	}

	public function testEmitsTheSchemaNameAsItsPageIsTitled(): void {
		$this->createSchema( 'Person record' );

		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, 'schema=person_record' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-schema="Person record"', $html );
	}

	public function testAcceptsTheSchemaNamespacePrefix(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, 'schema=Schema:' . self::SCHEMA_NAME ) );

		$this->assertStringContainsString( 'data-mw-neowiki-schema="' . self::SCHEMA_NAME . '"', $html );
	}

	public function testReportsAPageOutsideTheSchemaNamespaceAsAnUnknownSchema(): void {
		$result = $this->callOn( $this->contentPage, 'schema=' . $this->helpPage->getPrefixedText() );

		$this->assertRendersError( $result, 'neowiki-create-subject-error-unknown-schema', 'Help:Using subjects' );
	}

	public function testTreatsEmptyValuesAsAbsent(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->helpPage, 'schema=', 'text= ', 'page=' ) );

		$this->assertStringNotContainsString( 'data-mw-neowiki-schema', $html );
		$this->assertStringNotContainsString( 'data-mw-neowiki-text', $html );
		$this->assertStringNotContainsString( 'data-mw-neowiki-page=', $html );
	}

	public function testEscapesTheTextAttribute(): void {
		$html = $this->assertRendersButton(
			$this->callOn( $this->contentPage, 'text=</div><script>alert(1)</script>' )
		);

		$this->assertStringContainsString(
			'data-mw-neowiki-text="&lt;/div&gt;&lt;script&gt;alert(1)&lt;/script&gt;"',
			$html
		);
	}

	public function testEmitsTheHostPageMainSubjectFlagWithoutAPageArgument(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->contentPage ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page-has-main-subject="true"', $html );
		$this->assertStringNotContainsString( 'data-mw-neowiki-page=', $html );
	}

	public function testEmitsNoHostPageAttributeOnAnIneligiblePage(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->helpPage ) );

		$this->assertStringNotContainsString( 'data-mw-neowiki-page-has-main-subject', $html );
	}

	public function testPageThisEmitsThisPageAndItsMainSubjectFlag(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, 'page=this' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page="this"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-page-has-main-subject="true"', $html );
	}

	public function testPageThisEmitsFalseWhenThePageHasNoMainSubject(): void {
		$emptyPage = $this->getExistingTestPage( 'A page without subjects' )->getTitle();

		$html = $this->assertRendersButton( $this->callOn( $emptyPage, 'page=this' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page-has-main-subject="false"', $html );
	}

	public function testPageThisFallsBackToNewOnAnIneligiblePage(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->helpPage, 'page=this' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page="new"', $html );
		$this->assertStringNotContainsString( 'data-mw-neowiki-page-has-main-subject', $html );
	}

	public function testMarksItsOutputAsDependingOnThePageId(): void {
		$output = $this->parserOutputOf( $this->contentPage );

		$this->assertTrue( $output->getOutputFlag( ParserOutputFlags::VARY_PAGE_ID ) );
		$this->assertSame( $this->contentPage->getId(), $output->getSpeculativePageIdUsed() );
	}

	/**
	 * @dataProvider buttonArgumentsProvider
	 * @param string[] $args
	 */
	public function testRecordsNoPageIdForAPageNotCreatedYet( array $args ): void {
		$output = $this->parserOutputOf( Title::makeTitle( NS_MAIN, 'A page not created yet' ), ...$args );

		$this->assertTrue( $output->getOutputFlag( ParserOutputFlags::VARY_PAGE_ID ) );
		$this->assertNull( $output->getSpeculativePageIdUsed() );
	}

	public static function buttonArgumentsProvider(): iterable {
		yield 'without a page argument' => [ [] ];
		yield 'page=this' => [ [ 'page=this' ] ];
	}

	public function testPageNewEmitsANewPage(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, 'page=new' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page="new"', $html );
	}

	public function testNamedPageEmitsItsTitleAndCurrentId(): void {
		$target = $this->getExistingTestPage( 'The target page' )->getTitle();

		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, 'page=The target page' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page-title="The target page"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-page-id="' . $target->getId() . '"', $html );
	}

	/**
	 * @dataProvider fixedPageArgumentProvider
	 */
	public function testKeepsTheHostPageMainSubjectFlagWithAFixedPage( string $pageArgument ): void {
		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, $pageArgument ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page-has-main-subject="true"', $html );
	}

	public static function fixedPageArgumentProvider(): iterable {
		yield 'a new page' => [ 'page=new' ];
		yield 'a named page' => [ 'page=Not a page yet' ];
	}

	public function testNamedPageThatDoesNotExistEmitsIdZero(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, 'page=Not a page yet' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page-title="Not a page yet"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-page-id="0"', $html );
	}

	public function testNormalizesTheNamedPageToItsPrefixedTitle(): void {
		$html = $this->assertRendersButton( $this->callOn( $this->contentPage, 'page=help:using_subjects' ) );

		$this->assertStringContainsString( 'data-mw-neowiki-page-title="Help:Using subjects"', $html );
	}

	public function testAcceptsAnExistingPageOutsideTheMainNamespace(): void {
		$html = $this->assertRendersButton(
			$this->callOn( $this->contentPage, 'page=' . $this->helpPage->getPrefixedText() )
		);

		$this->assertStringContainsString( 'data-mw-neowiki-page-id="' . $this->helpPage->getId() . '"', $html );
	}

	public function testRejectsAMissingPageOutsideTheMainNamespace(): void {
		$result = $this->callOn( $this->contentPage, 'page=Help:Not a page yet' );

		$this->assertRendersError(
			$result,
			'neowiki-create-subject-error-uncreatable-page',
			'Help:Not a page yet'
		);
	}

	public function testRegistersARejectedPageAsALink(): void {
		$output = $this->parserOutputOf( $this->contentPage, 'page=Help:Not a page yet' );

		$this->assertSame( [ [ NS_HELP, 'Not_a_page_yet', 0 ] ], $this->linksOf( $output ) );
	}

	public function testRegistersTheNamedPageAsALink(): void {
		$output = $this->parserOutputOf( $this->contentPage, 'page=Not a page yet' );

		$this->assertSame( [ [ NS_MAIN, 'Not_a_page_yet', 0 ] ], $this->linksOf( $output ) );
	}

	/**
	 * @return list<array{0: int, 1: string, 2: int}>
	 */
	private function linksOf( ParserOutput $output ): array {
		return array_map(
			static fn ( array $link ): array =>
				[ $link['link']->getNamespace(), $link['link']->getDBkey(), $link['pageid'] ],
			$output->getLinkList( ParserOutputLinkTypes::LOCAL )
		);
	}

	public function testAddsTheFrontendModules(): void {
		$output = $this->parserOutputOf( $this->helpPage );

		$this->assertContains( 'ext.neowiki', $output->getModules() );
		$this->assertContains( 'ext.neowiki.styles', $output->getModuleStyles() );
	}

	public function testReportsASchemaThatDoesNotExist(): void {
		$result = $this->callOn( $this->contentPage, 'schema=No such schema' );

		$this->assertRendersError( $result, 'neowiki-create-subject-error-unknown-schema', 'No such schema' );
	}

	public function testReportsAnInvalidSchemaNameAsUnknown(): void {
		$result = $this->callOn( $this->contentPage, 'schema=Page' );

		$this->assertRendersError( $result, 'neowiki-create-subject-error-unknown-schema', 'Page' );
	}

	public function testReportsAnUnparsablePageTitle(): void {
		$result = $this->callOn( $this->contentPage, 'page=Broken [ title' );

		$this->assertRendersError( $result, 'neowiki-create-subject-error-invalid-page' );
	}

	public function testReportsInterwikiAndSpecialPageTitles(): void {
		$this->overrideConfigValue( MainConfigNames::InterwikiCache, ClassicInterwikiLookup::buildCdbHash( [
			[ 'iw_prefix' => 'en', 'iw_url' => 'https://en.example.org/wiki/$1', 'iw_local' => 0 ],
		] ) );

		$this->assertRendersError(
			$this->callOn( $this->contentPage, 'page=en:Somewhere else' ),
			'neowiki-create-subject-error-invalid-page'
		);
		$this->assertRendersError(
			$this->callOn( $this->contentPage, 'page=Special:Watchlist' ),
			'neowiki-create-subject-error-invalid-page'
		);
	}

	public function testRegistersTheSchemaPageAsALink(): void {
		$output = $this->parserOutputOf( $this->contentPage, 'schema=No such schema' );

		$this->assertContains(
			[ NeoWikiExtension::NS_SCHEMA, 'No_such_schema', 0 ],
			$this->linksOf( $output )
		);
	}

	public function testReportsAPositionalArgument(): void {
		$result = $this->callOn( $this->contentPage, self::SCHEMA_NAME );

		$this->assertRendersError( $result, 'neowiki-create-subject-error-positional', self::SCHEMA_NAME );
	}

	public function testReportsAnUnknownArgumentName(): void {
		$result = $this->callOn( $this->contentPage, 'layout=Finances' );

		$this->assertRendersError( $result, 'neowiki-create-subject-error-unknown-arg', 'layout' );
	}

	public function testReportsAnArgumentWithAnEmptyName(): void {
		$result = $this->callOn( $this->contentPage, '=Finances' );

		$this->assertRendersError( $result, 'neowiki-create-subject-error-unknown-arg', '=Finances' );
	}

	public function testEmitsNoButtonAlongsideAnError(): void {
		$result = $this->callOn( $this->contentPage, 'schema=No such schema' );

		$this->assertIsString( $result );
		$this->assertStringNotContainsString( 'ext-neowiki-create-subject-button', $result );
	}

	public function testEscapesTheOffendingValueInAnError(): void {
		$result = $this->callOn( $this->contentPage, '<script>alert(1)</script>=x' );

		$this->assertIsString( $result );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * @return string|array{0: string, noparse: true, isHTML: true}
	 */
	private function callOn( Title $title, string ...$args ): string|array {
		return $this->newFunction()->handle( $this->newParser( $title ), ...$args );
	}

	private function parserOutputOf( Title $title, string ...$args ): ParserOutput {
		$parser = $this->newParser( $title );

		$this->newFunction()->handle( $parser, ...$args );

		return $parser->getOutput();
	}

	private function newFunction(): CreateSubjectParserFunction {
		return new CreateSubjectParserFunction( new PageSubjectsLookup( $this->subjectRepository ) );
	}

	private function newParser( Title $title ): Parser {
		$parser = $this->createStub( Parser::class );
		$parser->method( 'getTitle' )->willReturn( $title );
		$parser->method( 'getOutput' )->willReturn( new ParserOutput() );
		$parser->method( 'msg' )->willReturnCallback(
			static fn ( string $key, ...$params ) => new RawMessage( $key . ': $1', $params )
		);

		return $parser;
	}

	/**
	 * @param string|array{0: string, noparse: true, isHTML: true} $result
	 * @return string the placeholder HTML
	 */
	private function assertRendersButton( string|array $result ): string {
		$this->assertIsArray( $result, 'Expected a placeholder array; got an error string.' );
		$this->assertTrue( $result['isHTML'] );
		$this->assertTrue( $result['noparse'] );
		$this->assertStringContainsString( 'class="ext-neowiki-create-subject-button"', $result[0] );

		return $result[0];
	}

	/**
	 * @param string|array{0: string, noparse: true, isHTML: true} $result
	 */
	private function assertRendersError( string|array $result, string $messageKey, ?string $insertion = null ): void {
		$this->assertIsString( $result, 'Expected an error HTML string; got a placeholder array.' );
		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringContainsString( $messageKey, $result );

		if ( $insertion !== null ) {
			$this->assertStringContainsString( $insertion, $result );
		}
	}

}
