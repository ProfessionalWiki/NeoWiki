<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\View\View;
use ProfessionalWiki\NeoWiki\Domain\View\ViewName;

interface ViewLookup {

	public function getView( ViewName $viewName ): ?View;

}
