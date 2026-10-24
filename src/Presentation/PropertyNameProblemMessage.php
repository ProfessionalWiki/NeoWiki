<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameProblem;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameRule;
use Wikimedia\Message\ListParam;
use Wikimedia\Message\ListType;
use Wikimedia\Message\MessageValue;
use Wikimedia\Message\ParamType;
use Wikimedia\Message\ScalarParam;

/**
 * The Schema editor words the same problems with the same messages, in propertyNameProblemMessage.ts.
 */
class PropertyNameProblemMessage {

	public static function from( PropertyNameProblem $problem ): MessageValue {
		$name = $problem->name;
		$details = $problem->details;

		return match ( $problem->rule ) {
			PropertyNameRule::CanonicalForm => self::canonicalFormMessage( $name, $details ),
			PropertyNameRule::Empty => self::message( 'neowiki-property-name-empty' ),
			PropertyNameRule::Characters => self::message( 'neowiki-property-name-characters', $name )
				->params( self::plaintextList( $details ) ),
			PropertyNameRule::Integer => self::message( 'neowiki-property-name-integer', $name ),
			PropertyNameRule::DotSegment => self::message( 'neowiki-property-name-dot-segment' ),
			PropertyNameRule::Length => self::message( 'neowiki-property-name-too-long', $name ),
			PropertyNameRule::Reserved => self::message( 'neowiki-property-name-reserved', $name ),
			PropertyNameRule::NearDuplicate => self::message( 'neowiki-property-name-near-duplicate', $name, $details[0] ),
		};
	}

	/**
	 * @param string[] $details
	 */
	private static function canonicalFormMessage( string $name, array $details ): MessageValue {
		$canonicalForm = $details[0];
		$hiddenCharacters = array_slice( $details, 1 );

		if ( $hiddenCharacters === [] ) {
			return self::message( 'neowiki-property-name-not-canonical', $name, $canonicalForm );
		}

		return self::message( 'neowiki-property-name-not-canonical-invisible', $name, $canonicalForm )
			->params( self::plaintextList( $hiddenCharacters ) );
	}

	private static function message( string $key, string ...$params ): MessageValue {
		return MessageValue::new( $key )->plaintextParams( ...$params );
	}

	/**
	 * @param string[] $items
	 */
	private static function plaintextList( array $items ): ListParam {
		return new ListParam(
			ListType::AND,
			array_map( static fn ( string $item ) => new ScalarParam( ParamType::PLAINTEXT, $item ), $items )
		);
	}

}
