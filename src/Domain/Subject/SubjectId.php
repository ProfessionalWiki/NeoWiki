<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use Stringable;

/**
 * A Subject's identity: the Source that produced it paired with that Source's own id for it (ADR 23).
 *
 * A local Subject serializes to its bare local id, exactly as every local Subject has always been
 * stored, so `s` plus 14 nanoid characters keeps meaning what it did. Everything else serializes as
 * `sourceKey:localId`, split at the first colon. The two forms cannot be confused: the local grammar
 * holds no colon.
 *
 * This value object is context-free: it cannot tell that a qualified id names the local wiki, because
 * it does not know which Source key is local. Ids arriving from outside are parsed by
 * {@see SubjectIdParser}, which does, and therefore canonicalizes such an id to its bare form.
 */
readonly class SubjectId implements Stringable {

	/**
	 * The local Source's id grammar (ADR 14): `s` plus 14 characters of a 58-character alphabet that
	 * omits the visually ambiguous 0, O, I and l.
	 */
	public const string LOCAL_ID_PATTERN = '/^s[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}\z/';

	/**
	 * A Source key is an identifier, not a path: a letter followed by up to 63 letters, digits,
	 * underscores or hyphens. The local key is the MediaWiki Wiki ID (ADR 22).
	 */
	public const string SOURCE_KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]{0,63}\z/';

	/**
	 * A non-local localId is opaque to NeoWiki, so only its serialization is constrained: RFC 3986
	 * pchar, minus percent-encoding and minus the two sub-delimiters an HTML attribute would have to
	 * escape (`&` and `'`). That keeps a serialized id usable verbatim as a REST path segment, a JSON
	 * object key, a Neo4j string property, a `data-` attribute value and a Lua table value. It admits
	 * further colons, so a Source may key by a colon-separated id of its own. A Source's own grammar
	 * may be narrower than this, never wider.
	 */
	public const string FOREIGN_LOCAL_ID_PATTERN = '/^[A-Za-z0-9._~:@!$()*+,;=-]{1,256}\z/';

	public string $text;

	/**
	 * The key of the Source that produced this Subject, or null for the local one. Null rather than the
	 * local wiki's key, because being local is a property of who is reading: the same stored id means
	 * "mine" on whichever wiki reads it.
	 */
	public ?string $source;

	public string $localId;

	public function __construct( string $text ) {
		$parts = self::split( $text );

		if ( $parts === null ) {
			throw new InvalidArgumentException( "Subject ID has the wrong format: '$text'" );
		}

		$this->text = $text;
		[ $this->source, $this->localId ] = $parts;
	}

	/**
	 * @return array{0: ?string, 1: string}|null The Source key (null when local) and the localId, or
	 *   null when $text is not a well-formed Subject id.
	 */
	private static function split( string $text ): ?array {
		$colonPosition = strpos( $text, ':' );

		if ( $colonPosition === false ) {
			return self::isValidLocalId( $text ) ? [ null, $text ] : null;
		}

		$source = substr( $text, 0, $colonPosition );
		$localId = substr( $text, $colonPosition + 1 );

		if ( !self::isValidSourceKey( $source ) || !self::isValidForeignLocalId( $localId ) ) {
			return null;
		}

		return [ $source, $localId ];
	}

	public function isLocal(): bool {
		return $this->source === null;
	}

	public function equals( self $other ): bool {
		return $this->text === $other->text;
	}

	/**
	 * Mints an id for a new Subject, which is always local: no path creates a Subject in another Source.
	 */
	public static function createNew( IdGenerator $idGenerator ): self {
		return new self( 's' . $idGenerator->generate() );
	}

	/**
	 * Whether $text is a well-formed Subject id in either form. Syntax only: whether the Source exists,
	 * and whether it recognizes the localId, is answered at resolution time by the Source registry.
	 */
	public static function isValid( string $text ): bool {
		return self::split( $text ) !== null;
	}

	/**
	 * The local Source's own grammar, which {@see \ProfessionalWiki\NeoWiki\Application\Source\LocalSource}
	 * exposes as its localId rule.
	 */
	public static function isValidLocalId( string $text ): bool {
		return preg_match( self::LOCAL_ID_PATTERN, $text ) === 1;
	}

	public static function isValidSourceKey( string $text ): bool {
		return preg_match( self::SOURCE_KEY_PATTERN, $text ) === 1;
	}

	private static function isValidForeignLocalId( string $text ): bool {
		return preg_match( self::FOREIGN_LOCAL_ID_PATTERN, $text ) === 1;
	}

	public function __toString(): string {
		return $this->text;
	}

}
