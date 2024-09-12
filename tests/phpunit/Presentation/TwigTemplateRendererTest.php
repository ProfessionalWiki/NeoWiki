<?php

declare( strict_types = 1 );

namespace Presentation;

use ErrorException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\TwigEnvironmentFactory;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\TwigTemplateRenderer;
use Psr\Log\LogLevel;
use Twig\Environment;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Presentation\TwigTemplateRendererTest
 */
class TwigTemplateRendererTest extends TestCase {
	private const SUBJECT_COUNT = 100500;
	private const JSON_URL = 'https://test.url';

	public function testViewModelToString(): void {
		$twigRenderer = new TwigTemplateRenderer(
			TwigEnvironmentFactory::create( __DIR__ . '/../Data/templates' ),
			new LegacyLoggerSpy()
		);

		$view = $twigRenderer->viewModelToString(
			'FactBox.html.twig',
			[
				'subjectCount' => self::SUBJECT_COUNT,
				'neoJsonUrl' => self::JSON_URL
			]
		);

		$this->assertStringContainsString( (string)self::SUBJECT_COUNT, $view );
		$this->assertStringContainsString( self::JSON_URL, $view );
	}

	public function testErrorViewModelToString(): void {
		$logger = new LegacyLoggerSpy();

		$twigEnvironmentMock = $this->newThrowingTwigEnvironment();
		$newThrowingTwigEnvironment = new TwigTemplateRenderer( $twigEnvironmentMock, $logger );

		$this->assertStringContainsString(
			$newThrowingTwigEnvironment::ERROR_MSG,
			$newThrowingTwigEnvironment->viewModelToString( 'fake', [] )
		);

		$logCall = $logger->getLogCalls()->getLastCall();
		$this->assertSame(
			LogLevel::CRITICAL,
			$logCall->getLevel()
		);
	}

	private function newThrowingTwigEnvironment(): Environment|MockObject {
		$twigEnvironment = $this->createMock( Environment::class );
		$twigEnvironment->expects( $this->once() )
			->method( 'render' )
			->willThrowException( new ErrorException( 'test' ) );
		return $twigEnvironment;
	}
}
