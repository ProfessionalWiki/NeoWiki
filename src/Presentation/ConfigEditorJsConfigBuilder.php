<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSetting;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigLookup;

/**
 * What the form on the on-wiki configuration page starts from, by the page key of every exposed setting:
 * the value the page sets, which is null for a setting the page leaves to LocalSettings.php, and the
 * LocalSettings.php value as NeoWiki reads it.
 */
class ConfigEditorJsConfigBuilder {

	/**
	 * @param WikiConfigLookup $lookup Reading the configuration page as the form edits it: its latest
	 *     revision.
	 */
	public function __construct(
		private readonly ConfigSchema $schema,
		private readonly WikiConfigLookup $lookup,
	) {
	}

	/**
	 * @param int|null $revisionId The revision the form saves over, or null when the page does not exist.
	 */
	public function build( ?int $revisionId ): array {
		$pageKeys = array_map( fn ( ConfigSetting $setting ): string => $setting->pageKey, $this->schema->getSettings() );

		return [
			'revisionId' => $revisionId,
			'pageValues' => array_combine( $pageKeys, array_map( $this->lookup->getPageValue( ... ), $pageKeys ) ),
			'localSettingsValues' => array_combine( $pageKeys, array_map( $this->lookup->getPhpValue( ... ), $pageKeys ) ),
		];
	}

}
