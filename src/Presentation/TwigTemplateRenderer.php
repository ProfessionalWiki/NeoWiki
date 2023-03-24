<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigTemplateRenderer implements TemplateRenderer {

	public function __construct(
		private readonly string $templateDirectory
	) {
	}

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function viewModelToString( string $template, array $parameters ): string {
		$twig = new Environment(
			new FilesystemLoader( [ $this->templateDirectory ] )
		);

		// TODO: catch error
		return $twig->render( $template, $parameters );
	}

}
