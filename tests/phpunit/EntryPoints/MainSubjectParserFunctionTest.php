<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use InvalidArgumentException;
use MediaWiki\Language\RawMessage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\MainSubjectParserFunction;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use Throwable;
use TypeError;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\MainSubjectParserFunction
 */
class MainSubjectParserFunctionTest extends TestCase {

	private const int PAGE_ID = 7;

	private ParserOutput $parserOutput;

	/**
	 * @dataProvider argumentsProvider
	 */
	public function testAnArgumentGivesAnErrorNamingIt( string ...$args ): void {
		$result = $this->newParserFunction( new PageSubjects( TestSubject::build(), new SubjectMap() ) )
			->handle( $this->newParser(), ...$args );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringContainsString( 'neowiki-main-subject-error-argument', $result );
		$this->assertStringContainsString( 'layout=Finances', $result );
	}

	public static function argumentsProvider(): iterable {
		yield 'an argument' => [ 'layout=Finances' ];
		yield 'an argument after a stray pipe' => [ '', 'layout=Finances' ];
	}

	public function testRendersNothingOnAPageWithoutAMainSubject(): void {
		$result = $this->newParserFunction( new PageSubjects( null, TestSubject::newMap() ) )
			->handle( $this->newParser(), '' );

		$this->assertSame( '', $result );
	}

	/**
	 * @dataProvider deserializationFailureProvider
	 */
	public function testRendersNothingWhenThePageSubjectsDoNotDeserialize( Throwable $failure ): void {
		$repository = $this->createStub( SubjectRepository::class );
		$repository->method( 'getSubjectsByPageId' )->willThrowException( $failure );

		$result = ( new MainSubjectParserFunction( new PageSubjectsLookup( $repository ) ) )->handle( $this->newParser(), '' );

		$this->assertSame( '', $result );
	}

	public function testAFailedReadIsNotCached(): void {
		$repository = $this->createStub( SubjectRepository::class );
		$repository->method( 'getSubjectsByPageId' )->willThrowException( new InvalidArgumentException( 'Invalid schema' ) );

		( new MainSubjectParserFunction( new PageSubjectsLookup( $repository ) ) )->handle( $this->newParser(), '' );

		$this->assertSame( 0, $this->parserOutput->getCacheExpiry() );
	}

	public static function deserializationFailureProvider(): iterable {
		yield 'a Subject that fails validation' => [ new InvalidArgumentException( 'Invalid schema' ) ];
		yield 'a Subject that is not an object' => [ new TypeError( 'Not an array' ) ];
	}

	private function newParserFunction( PageSubjects $pageSubjects ): MainSubjectParserFunction {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects( $pageSubjects, new PageId( self::PAGE_ID ) );

		return new MainSubjectParserFunction( new PageSubjectsLookup( $repository ) );
	}

	private function newParser(): Parser {
		$title = $this->createStub( Title::class );
		$title->method( 'getArticleID' )->willReturn( self::PAGE_ID );

		$this->parserOutput = new ParserOutput();

		$parser = $this->createStub( Parser::class );
		$parser->method( 'getTitle' )->willReturn( $title );
		$parser->method( 'getOutput' )->willReturn( $this->parserOutput );
		$parser->method( 'msg' )->willReturnCallback(
			static fn ( string $key, ...$params ) => new RawMessage( $key . ': $1', $params )
		);

		return $parser;
	}

}
