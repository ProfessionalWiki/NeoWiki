<?php

namespace ProfessionalWiki\NeoWiki\Presentation;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigEnvironmentFactory {

	public static function create( string $templateDirectory ): Environment {
		return new Environment(
			new FilesystemLoader( [ $templateDirectory ] )
		);
	}

}
