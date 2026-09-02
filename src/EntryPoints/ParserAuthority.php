<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;

/**
 * The authority every parse-time read runs against: the user the page is being parsed for. That is
 * the viewer on a page-view cache miss, the previewing editor on a classic preview, and the anonymous
 * canonical user on save, in jobs, and in every Parsoid render (VisualEditor included). The request
 * context's user is the wrong choice here: it is the saver during the canonical parse of an edit, and
 * whoever runs the job queue otherwise, neither of which matches the identity the parser cache files
 * the output under.
 */
class ParserAuthority {

	public static function of( Parser $parser ): Authority {
		return MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $parser->getUserIdentity() );
	}

}
