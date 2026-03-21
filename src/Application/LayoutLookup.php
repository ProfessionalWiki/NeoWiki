<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;

interface LayoutLookup {

	public function getLayout( LayoutName $layoutName ): ?Layout;

}
