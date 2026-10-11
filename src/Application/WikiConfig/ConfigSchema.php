<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

/**
 * The allowlist of settings the on-wiki configuration page (MediaWiki:NeoWiki) may set. The allowlist is
 * the security boundary: only these settings are readable from and writable on the page. Everything else
 * stays exclusive to LocalSettings.php — infrastructure and secrets (NeoWikiSparqlStores), settings too
 * consequential for a wiki page (NeoWikiRdfBaseUri re-mints every IRI), and dev-only toggles
 * (NeoWikiEnableDevelopmentUI). New settings are vetted and added one by one.
 */
class ConfigSchema {

	/**
	 * @var ConfigSetting[]
	 */
	private array $settings;

	public function __construct() {
		$this->settings = [
			new ConfigSetting(
				pageKey: 'subjectFirst',
				settingName: 'NeoWikiSubjectFirst',
				valueType: ConfigValueType::Boolean,
				labelMessage: 'neowiki-config-setting-subject-first',
				helpMessage: null,
				choices: [
					new ConfigChoice(
						value: false,
						labelMessage: 'neowiki-config-setting-subject-first-page-first',
						helpMessage: 'neowiki-config-setting-subject-first-page-first-help',
					),
					new ConfigChoice(
						value: true,
						labelMessage: 'neowiki-config-setting-subject-first-subject-first',
						helpMessage: 'neowiki-config-setting-subject-first-subject-first-help',
					),
				],
			),
			new ConfigSetting(
				pageKey: 'dataLanguages',
				settingName: 'NeoWikiDataLanguages',
				valueType: ConfigValueType::LanguageList,
				labelMessage: 'neowiki-config-setting-data-languages',
				helpMessage: 'neowiki-config-setting-data-languages-help',
			),
			new ConfigSetting(
				pageKey: 'autoRenderMainSubject',
				settingName: 'NeoWikiAutoRenderMainSubject',
				valueType: ConfigValueType::Boolean,
				labelMessage: 'neowiki-config-setting-auto-render-main-subject',
				helpMessage: null,
			),
		];
	}

	/**
	 * @return ConfigSetting[]
	 */
	public function getSettings(): array {
		return $this->settings;
	}

	public function getSetting( string $pageKey ): ?ConfigSetting {
		foreach ( $this->settings as $setting ) {
			if ( $setting->pageKey === $pageKey ) {
				return $setting;
			}
		}

		return null;
	}

}
