<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

/**
 * When the definition of an RDF projection last changed, which is what tells a rebuilt store from a
 * store rebuilt against rules that have since moved on.
 */
interface ProjectionChangeTimeLookup {

	/**
	 * The MediaWiki timestamp of the last change to $projection's definition, or null when it has no
	 * definition that can change — the native projection, or a projection named in configuration that no
	 * Mapping page defines.
	 */
	public function getLastChangeTime( string $projection ): ?string;

}
