<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

use InvalidArgumentException;
use MediaWiki\Config\Config;
use Psr\Log\LoggerInterface;

/**
 * Resolves the effective value of an exposed setting by combining the on-wiki configuration page with the
 * PHP/LocalSettings configuration, per setting, with the wiki page winning when it sets a valid value.
 *
 * A missing page, an absent key, or a value of the wrong shape falls back to the PHP configuration rather
 * than throwing: a config typo on the page must not take down the wiki (the NeoWikiConfigFactory stance).
 * An invalid page value is logged once so the fallback is not silent; unknown page keys are tolerated for
 * forward compatibility. The page is read at most once (memoized), and only when in-wiki config is enabled.
 */
class WikiConfigLookup {

	private bool $pageRead = false;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $pageData = null;

	public function __construct(
		private ConfigSchema $schema,
		private WikiConfigSource $source,
		private Config $phpConfig,
		private bool $enabled,
		private LoggerInterface $logger,
	) {
	}

	public function getEffectiveValue( string $pageKey ): mixed {
		$setting = $this->schema->getSetting( $pageKey );

		if ( $setting === null ) {
			throw new InvalidArgumentException( "Not an on-wiki-configurable setting: $pageKey" );
		}

		$phpValue = $this->phpConfig->get( $setting->settingName );

		$pageData = $this->pageData();

		if ( $pageData === null || !array_key_exists( $pageKey, $pageData ) ) {
			return $phpValue;
		}

		$pageValue = $pageData[$pageKey];

		if ( $setting->isValidValue( $pageValue ) ) {
			return $pageValue;
		}

		$this->logger->warning(
			'Ignoring invalid value for "{key}" on the MediaWiki:NeoWiki configuration page; using the PHP configuration instead.',
			[ 'key' => $pageKey ]
		);

		return $phpValue;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function pageData(): ?array {
		if ( !$this->enabled ) {
			return null;
		}

		if ( !$this->pageRead ) {
			$this->pageRead = true;
			$this->pageData = $this->source->readConfig();
		}

		return $this->pageData;
	}

}
