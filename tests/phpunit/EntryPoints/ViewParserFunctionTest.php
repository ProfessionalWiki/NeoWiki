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

	private function newParserFunction( string $mainSubjectId ): ViewParserFunction {
		return new ViewParserFunction( $this->createRepositoryWithMainSubjectId( $mainSubjectId ) );
	}

	public function testEmitsPlaceholderWithExplicitPositionalSubject(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle( $this->createMockParser(), 's22222222222222' );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['isHTML'] );
		$this->assertTrue( $result['noparse'] );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $result[0] );
		$this->assertStringNotContainsString( 'data-mw-neowiki-layout-name', $result[0] );
	}

	public function testEmitsPlaceholderWithLayoutAsNamedArg(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle( $this->createMockParser(), 'layout=Finances' );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s11111111111111"', $result[0] );
		$this->assertStringContainsString( 'data-mw-neowiki-layout-name="Finances"', $result[0] );
	}

	public function testEmitsPlaceholderWithSubjectAsNamedArg(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle( $this->createMockParser(), 'subject=s22222222222222' );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $result[0] );
		$this->assertStringNotContainsString( 'data-mw-neowiki-layout-name', $result[0] );
	}

	public function testEmitsPlaceholderWithSubjectAndLayoutNamedArgs(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			'subject=s22222222222222',
			'layout=Finances'
		);

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $result[0] );
		$this->assertStringContainsString( 'data-mw-neowiki-layout-name="Finances"', $result[0] );
	}

	public function testEmitsPlaceholderWithMixedPositionalAndNamed(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			's22222222222222',
			'layout=Finances'
		);

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $result[0] );
		$this->assertStringContainsString( 'data-mw-neowiki-layout-name="Finances"', $result[0] );
	}

	public function testEmitsPlaceholderWhenNamedArgComesBeforePositional(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			'layout=Finances',
			's22222222222222'
		);

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $result[0] );
		$this->assertStringContainsString( 'data-mw-neowiki-layout-name="Finances"', $result[0] );
	}

	public function testTreatsEmptyNamedSubjectAsFallbackToMainSubject(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			'subject=',
			'layout=Finances'
		);

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s11111111111111"', $result[0] );
		$this->assertStringContainsString( 'data-mw-neowiki-layout-name="Finances"', $result[0] );
	}

	public function testTreatsEmptyNamedLayoutAsUnset(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			's22222222222222',
			'layout='
		);

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $result[0] );
		$this->assertStringNotContainsString( 'data-mw-neowiki-layout-name', $result[0] );
	}

	public function testFallsBackToMainSubjectWhenNoArgs(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle( $this->createMockParser() );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s11111111111111"', $result[0] );
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
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			's22222222222222',
			's33333333333333'
		);

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringContainsString( 'neowiki-view-error-extra-positional', $result );
		$this->assertStringContainsString( 's33333333333333', $result );
	}

	public function testOldPositionalLayoutFormProducesExtraPositionalError(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			's22222222222222',
			'Finances'
		);

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringContainsString( 'neowiki-view-error-extra-positional', $result );
		$this->assertStringContainsString( 'Finances', $result );
	}

	public function testRendersErrorOnConflictingSubject(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle(
			$this->createMockParser(),
			's22222222222222',
			'subject=s33333333333333'
		);

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringContainsString( 'neowiki-view-error-conflicting-subject', $result );
	}

	public function testRendersErrorOnUnknownNamedArg(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle( $this->createMockParser(), 'layuot=Finances' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringContainsString( 'neowiki-view-error-unknown-arg', $result );
		$this->assertStringContainsString( 'layuot', $result );
	}

	public function testRendersErrorOnArgWithEmptyName(): void {
		$parserFunction = $this->newParserFunction( 's11111111111111' );

		$result = $parserFunction->handle( $this->createMockParser(), '=Finances' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringContainsString( 'neowiki-view-error-unknown-arg', $result );
		$this->assertStringContainsString( '=Finances', $result );
	}

}
