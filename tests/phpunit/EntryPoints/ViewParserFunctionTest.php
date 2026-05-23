<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Language\RawMessage;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\ViewParserFunction;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ViewParserFunction
 */
class ViewParserFunctionTest extends TestCase {

	private const string MAIN_SUBJECT_ID = 's11111111111111';
	private const string EXPLICIT_SUBJECT_ID = 's22222222222222';
	private const string OTHER_SUBJECT_ID = 's33333333333333';

	public function testEmitsPlaceholderWithExplicitPositionalSubject(): void {
		$result = $this->callView( self::EXPLICIT_SUBJECT_ID );

		$this->assertRendersSubject( $result, self::EXPLICIT_SUBJECT_ID );
	}

	public function testEmitsPlaceholderWithLayoutAsNamedArg(): void {
		$result = $this->callView( 'layout=Finances' );

		$this->assertRendersSubject( $result, self::MAIN_SUBJECT_ID, 'Finances' );
	}

	public function testEmitsPlaceholderWithSubjectAsNamedArg(): void {
		$result = $this->callView( 'subject=' . self::EXPLICIT_SUBJECT_ID );

		$this->assertRendersSubject( $result, self::EXPLICIT_SUBJECT_ID );
	}

	public function testEmitsPlaceholderWithSubjectAndLayoutNamedArgs(): void {
		$result = $this->callView( 'subject=' . self::EXPLICIT_SUBJECT_ID, 'layout=Finances' );

		$this->assertRendersSubject( $result, self::EXPLICIT_SUBJECT_ID, 'Finances' );
	}

	public function testEmitsPlaceholderWithMixedPositionalAndNamed(): void {
		$result = $this->callView( self::EXPLICIT_SUBJECT_ID, 'layout=Finances' );

		$this->assertRendersSubject( $result, self::EXPLICIT_SUBJECT_ID, 'Finances' );
	}

	public function testEmitsPlaceholderWhenNamedArgComesBeforePositional(): void {
		$result = $this->callView( 'layout=Finances', self::EXPLICIT_SUBJECT_ID );

		$this->assertRendersSubject( $result, self::EXPLICIT_SUBJECT_ID, 'Finances' );
	}

	public function testTreatsEmptyNamedSubjectAsFallbackToMainSubject(): void {
		$result = $this->callView( 'subject=', 'layout=Finances' );

		$this->assertRendersSubject( $result, self::MAIN_SUBJECT_ID, 'Finances' );
	}

	public function testTreatsEmptyNamedLayoutAsUnset(): void {
		$result = $this->callView( self::EXPLICIT_SUBJECT_ID, 'layout=' );

		$this->assertRendersSubject( $result, self::EXPLICIT_SUBJECT_ID );
	}

	public function testFallsBackToMainSubjectWhenNoArgs(): void {
		$result = $this->callView();

		$this->assertRendersSubject( $result, self::MAIN_SUBJECT_ID );
	}

	public function testReturnsEmptyStringWhenNoSubjectAvailable(): void {
		$parserFunction = new ViewParserFunction( $this->createRepositoryWithNoContent() );

		$result = $parserFunction->handle( $this->createMockParser() );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringWhenPageHasNoMainSubject(): void {
		$parserFunction = new ViewParserFunction( $this->createRepositoryWithNoMainSubject() );

		$result = $parserFunction->handle( $this->createMockParser() );

		$this->assertSame( '', $result );
	}

	public function testRendersErrorOnExtraPositional(): void {
		$result = $this->callView( self::EXPLICIT_SUBJECT_ID, self::OTHER_SUBJECT_ID );

		$this->assertRendersError( $result, 'neowiki-view-error-extra-positional', self::OTHER_SUBJECT_ID );
	}

	public function testOldPositionalLayoutFormProducesExtraPositionalError(): void {
		$result = $this->callView( self::EXPLICIT_SUBJECT_ID, 'Finances' );

		$this->assertRendersError( $result, 'neowiki-view-error-extra-positional', 'Finances' );
	}

	public function testRendersErrorOnConflictingSubject(): void {
		$result = $this->callView( self::EXPLICIT_SUBJECT_ID, 'subject=' . self::OTHER_SUBJECT_ID );

		$this->assertRendersError( $result, 'neowiki-view-error-conflicting-subject' );
	}

	public function testRendersErrorOnUnknownNamedArg(): void {
		$result = $this->callView( 'layuot=Finances' );

		$this->assertRendersError( $result, 'neowiki-view-error-unknown-arg', 'layuot' );
	}

	public function testRendersErrorOnArgWithEmptyName(): void {
		$result = $this->callView( '=Finances' );

		$this->assertRendersError( $result, 'neowiki-view-error-unknown-arg', '=Finances' );
	}

	private function callView( string ...$args ): string|array {
		return $this->newParserFunction( self::MAIN_SUBJECT_ID )
			->handle( $this->createMockParser(), ...$args );
	}

	private function newParserFunction( string $mainSubjectId ): ViewParserFunction {
		return new ViewParserFunction( $this->createRepositoryWithMainSubjectId( $mainSubjectId ) );
	}

	private function createMockParser(): Parser {
		$title = $this->createStub( Title::class );

		$parser = $this->createStub( Parser::class );
		$parser->method( 'getTitle' )->willReturn( $title );
		$parser->method( 'msg' )->willReturnCallback(
			static fn ( string $key, ...$params ) => new RawMessage( $key . ': $1', $params )
		);

		return $parser;
	}

	private function createRepositoryWithMainSubjectId( string $subjectId ): SubjectContentRepository {
		$subject = $this->createStub( Subject::class );
		$subject->method( 'getId' )->willReturn( new SubjectId( $subjectId ) );

		$pageSubjects = $this->createStub( PageSubjects::class );
		$pageSubjects->method( 'getMainSubject' )->willReturn( $subject );

		$subjectContent = $this->createStub( SubjectContent::class );
		$subjectContent->method( 'getPageSubjects' )->willReturn( $pageSubjects );

		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturn( $subjectContent );

		return $repo;
	}

	private function createRepositoryWithNoContent(): SubjectContentRepository {
		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturn( null );

		return $repo;
	}

	private function createRepositoryWithNoMainSubject(): SubjectContentRepository {
		$pageSubjects = $this->createStub( PageSubjects::class );
		$pageSubjects->method( 'getMainSubject' )->willReturn( null );

		$subjectContent = $this->createStub( SubjectContent::class );
		$subjectContent->method( 'getPageSubjects' )->willReturn( $pageSubjects );

		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturn( $subjectContent );

		return $repo;
	}

	/**
	 * @param string|array{0: string, noparse: true, isHTML: true} $result
	 */
	private function assertRendersSubject( string|array $result, string $subjectId, ?string $layoutName = null ): void {
		$this->assertIsArray( $result, 'Expected a placeholder array; got an error string or empty string.' );
		$this->assertTrue( $result['isHTML'] );
		$this->assertTrue( $result['noparse'] );

		$html = $result[0];
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="' . $subjectId . '"', $html );

		if ( $layoutName === null ) {
			$this->assertStringNotContainsString( 'data-mw-neowiki-layout-name', $html );
		} else {
			$this->assertStringContainsString( 'data-mw-neowiki-layout-name="' . $layoutName . '"', $html );
		}
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
