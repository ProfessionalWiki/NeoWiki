<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigChoice;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSetting;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigLookup;

/**
 * What the form on the on-wiki configuration page is generated from: every exposed setting with its
 * value type, label, help text and named values, its LocalSettings.php value as NeoWiki reads it, and the
 * value the page sets, which is null for a setting the page leaves to LocalSettings.php.
 */
class ConfigEditorJsConfigBuilder {

	/**
	 * @param WikiConfigLookup $lookup Reading the configuration page as the form edits it: its latest
	 *     revision.
	 */
	public function __construct(
		private readonly ConfigSchema $schema,
		private readonly WikiConfigLookup $lookup,
		private readonly MessageLocalizer $messageLocalizer,
	) {
	}

	/**
	 * @param int|null $revisionId The revision the form saves over, or null when the page does not exist.
	 */
	public function build( ?int $revisionId ): array {
		return [
			'revisionId' => $revisionId,
			'settings' => array_map(
				fn ( ConfigSetting $setting ): array => $this->buildSetting( $setting ),
				$this->schema->getSettings()
			),
		];
	}

	private function buildSetting( ConfigSetting $setting ): array {
		return [
			'key' => $setting->pageKey,
			'type' => $setting->valueType->value,
			'label' => $this->messageLocalizer->msg( $setting->labelMessage )->text(),
			'help' => $setting->helpMessage === null ? null : $this->messageLocalizer->msg( $setting->helpMessage )->text(),
			'choices' => array_map(
				fn ( ConfigChoice $choice ): array => [
					'value' => $choice->value,
					'label' => $this->messageLocalizer->msg( $choice->labelMessage )->text(),
					'help' => $this->messageLocalizer->msg( $choice->helpMessage )->text(),
				],
				$setting->choices
			),
			'localSettingsValue' => $this->lookup->getPhpValue( $setting->pageKey ),
			'pageValue' => $this->lookup->getPageValue( $setting->pageKey ),
		];
	}

}
