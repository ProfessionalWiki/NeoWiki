<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Presentation;

use Html;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Environment;

class TwigTemplateRenderer implements TemplateRenderer {

	public const string ERROR_MSG = 'Template Render Error';

	public function __construct(
		private Environment $twig,
		private LoggerInterface $logger
	) {
	}

	/**
	 * @param string $template
	 * @param array<string, mixed> $parameters
	 */
	public function viewModelToString( string $template, array $parameters ): string {
		try {
			$html = $this->twig->render( $template, $parameters );
		} catch ( Throwable $e ) {
			$html = Html::errorBox( self::ERROR_MSG );
			$this->logger->critical( $e->getMessage(), $e->getTrace() );
		}

		return $html;
	}
}
