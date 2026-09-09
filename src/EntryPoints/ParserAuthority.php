<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Infrastructure\UserAccessClass;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * The authority every parse-time read runs against: the user the page is being parsed for. That is
 * the viewer on a page-view cache miss, the previewing editor on a classic preview, and the anonymous
 * canonical user on save, in jobs, and in every Parsoid render (VisualEditor included). The request
 * context's user is the wrong choice here: it is the saver during the canonical parse of an edit, and
 * whoever runs the job queue otherwise, neither of which matches the identity the parser cache files
 * the output under.
 *
 * Obtaining the authority is also what makes the parse's output depend on who that user is, so
 * {@see self::of()} records the access-class parser option as used: the parser cache then files the
 * output under the user's access class ({@see UserAccessClass}) instead of sharing it across users.
 * Pages that never obtain a parsing authority keep one cache entry for everyone.
 *
 * The option itself carries no value. Its class enters the cache key through the PageRenderingHash
 * hook, and only for a page that recorded the option: a lazily valued option would be loaded for
 * every logged-in edit of every page by core's cache-key comparison, sending all of them down the
 * deferred parser-cache path.
 */
class ParserAuthority {

	public const string ACCESS_CLASS_OPTION = 'neowikiAccessClass';

	public static function of( Parser $parser ): Authority {
		$parser->getOptions()->getOption( self::ACCESS_CLASS_OPTION );

		return MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $parser->getUserIdentity() );
	}

	/**
	 * The ParserOptionsRegister hook body.
	 *
	 * @param array<string, mixed> $defaults
	 * @param array<string, bool> $inCacheKey
	 */
	public static function registerAccessClassOption( array &$defaults, array &$inCacheKey ): void {
		$defaults[self::ACCESS_CLASS_OPTION] = null;
		$inCacheKey[self::ACCESS_CLASS_OPTION] = true;
	}

	/**
	 * The PageRenderingHash hook body: the parsing user's access class, for a page that recorded the
	 * option. Every reader gets one, so a page that reads Subjects also stops reusing whatever was
	 * cached for it before NeoWiki began keying by class.
	 *
	 * @param string[] $usedOptions
	 */
	public static function appendAccessClassToRenderingHash( string &$hash, UserIdentity $user, array $usedOptions ): void {
		if ( !in_array( self::ACCESS_CLASS_OPTION, $usedOptions, true ) ) {
			return;
		}

		$hash .= '!' . self::ACCESS_CLASS_OPTION . '='
			. NeoWikiExtension::getInstance()->newUserAccessClass()->of( $user );
	}

}
