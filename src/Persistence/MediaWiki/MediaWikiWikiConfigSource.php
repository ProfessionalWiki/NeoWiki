<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Content\JsonContent;
use MediaWiki\Permissions\Authority;
use Psr\Log\LoggerInterface;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigSource;
use Throwable;

/**
 * Reads the raw configuration from the on-wiki configuration page in the MediaWiki namespace, memoizing
 * it for the request so the page is read at most once.
 *
 * Returns the decoded JSON object, or null when the page is missing, is not JSON, or the database is
 * unavailable (e.g. during installation) — all fail safely to null so the caller falls back to the PHP
 * configuration. A page that exists but does not hold a JSON object is logged once, since that is a
 * mistake an administrator would want to see rather than a plain absence.
 */
class MediaWikiWikiConfigSource implements WikiConfigSource {

	private bool $read = false;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $config = null;

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly string $pageTitle,
		private readonly LoggerInterface $logger,
	) {
	}

	public function readConfig(): ?array {
		if ( !$this->read ) {
			$this->read = true;
			$this->config = $this->doReadConfig();
		}

		return $this->config;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function doReadConfig(): ?array {
		try {
			$content = $this->pageContentFetcher->getPageContent( $this->pageTitle, $this->authority, NS_MEDIAWIKI );
		} catch ( Throwable ) {
			return null;
		}

		if ( !$content instanceof JsonContent ) {
			return null;
		}

		$decoded = json_decode( $content->getText(), true );

		if ( is_array( $decoded ) && ( $decoded === [] || !array_is_list( $decoded ) ) ) {
			return $decoded;
		}

		$this->logger->warning( 'The MediaWiki:NeoWiki configuration page is not a JSON object; ignoring it.' );

		return null;
	}

}
