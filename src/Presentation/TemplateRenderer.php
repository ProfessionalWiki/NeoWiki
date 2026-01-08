<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

interface TemplateRenderer {

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function viewModelToString( string $template, array $parameters ): string;

}
