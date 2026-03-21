<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

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

	public function testEmitsPlaceholderWithExplicitSubjectIdAndLayoutName(): void {
		$parserFunction = new ViewParserFunction(
			$this->createRepositoryWithMainSubjectId( 's11111111111111' )
		);

		$result = $parserFunction->handle( $this->createMockParser(), 's22222222222222', 'Finances' );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['isHTML'] );
		$this->assertTrue( $result['noparse'] );

		$html = $result[0];
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-layout-name="Finances"', $html );
	}

	public function testEmitsPlaceholderWithExplicitSubjectIdAndNoLayoutName(): void {
		$parserFunction = new ViewParserFunction(
			$this->createRepositoryWithMainSubjectId( 's11111111111111' )
		);

		$result = $parserFunction->handle( $this->createMockParser(), 's22222222222222' );

		$html = $result[0];
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s22222222222222"', $html );
		$this->assertStringNotContainsString( 'data-mw-neowiki-layout-name', $html );
	}

	public function testFallsBackToMainSubjectWhenSubjectIdIsEmpty(): void {
		$parserFunction = new ViewParserFunction(
			$this->createRepositoryWithMainSubjectId( 's11111111111111' )
		);

		$result = $parserFunction->handle( $this->createMockParser(), '', 'Finances' );

		$html = $result[0];
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s11111111111111"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-layout-name="Finances"', $html );
	}

	public function testReturnsEmptyStringWhenNoSubjectAvailable(): void {
		$parserFunction = new ViewParserFunction(
			$this->createRepositoryWithNoContent()
		);

		$result = $parserFunction->handle( $this->createMockParser() );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringWhenPageHasNoMainSubject(): void {
		$parserFunction = new ViewParserFunction(
			$this->createRepositoryWithNoMainSubject()
		);

		$result = $parserFunction->handle( $this->createMockParser() );

		$this->assertSame( '', $result );
	}

}
