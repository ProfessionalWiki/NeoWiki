( function () {
	'use strict';

	var nw = require( 'ext.neowiki' );
	var icons = require( './icons.json' );
	var DateTimeDisplay = require( './DateTimeDisplay.vue' );
	var DateTimeInput = require( './DateTimeInput.vue' );
	var DateTimeAttributesEditor = require( './DateTimeAttributesEditor.vue' );

	var DATETIME_TYPE_NAME = 'dateTime';

	// Strict ISO 8601 regex — lifted from NeoWiki core's prior DateTime.ts.
	// The only quantifier `\d{1,9}` is bounded and followed by a distinct
	// character class, so this is not subject to catastrophic backtracking.
	// eslint-disable-next-line security/detect-unsafe-regex
	var ISO_DATE_TIME_REGEX = /^(-?\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,9})?(?<offset>Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/;

	function isoOffsetToMinutes( offset ) {
		var sign = offset.charAt( 0 ) === '-' ? -1 : 1;
		var parts = offset.slice( 1 ).split( ':' ).map( Number );
		return sign * ( parts[ 0 ] * 60 + parts[ 1 ] );
	}

	function parseStrictDateTime( value ) {
		var match = ISO_DATE_TIME_REGEX.exec( value );
		if ( match === null || match.groups === undefined ) {
			return null;
		}

		var timestamp = Date.parse( value );
		if ( isNaN( timestamp ) ) {
			return null;
		}

		var offsetSegment = match.groups.offset;
		var offsetMinutes = offsetSegment === 'Z' ? 0 : isoOffsetToMinutes( offsetSegment );
		var local = new Date( timestamp + offsetMinutes * 60000 );

		if (
			local.getUTCFullYear() !== Number( match[ 1 ] ) ||
			local.getUTCMonth() + 1 !== Number( match[ 2 ] ) ||
			local.getUTCDate() !== Number( match[ 3 ] )
		) {
			return null;
		}

		return timestamp;
	}

	function validate( value, property ) {
		var errors = [];

		if ( property.required && value === undefined ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		if ( value !== undefined && value.parts.length > 0 ) {
			var timestamp = parseStrictDateTime( value.parts[ 0 ] );

			if ( timestamp === null ) {
				errors.push( { code: 'invalid-datetime' } );
				return errors;
			}

			var minimum = property.minimum;
			if ( minimum !== undefined ) {
				var minimumTimestamp = parseStrictDateTime( minimum );
				if ( minimumTimestamp !== null && timestamp < minimumTimestamp ) {
					errors.push( { code: 'min-value', args: [ minimum ] } );
				}
			}

			var maximum = property.maximum;
			if ( maximum !== undefined ) {
				var maximumTimestamp = parseStrictDateTime( maximum );
				if ( maximumTimestamp !== null && timestamp > maximumTimestamp ) {
					errors.push( { code: 'max-value', args: [ maximum ] } );
				}
			}
		}

		return errors;
	}

	mw.hook( 'neowiki.registration' ).add( function ( registrar ) {
		registrar.registerPropertyType( {
			typeName: DATETIME_TYPE_NAME,
			valueType: nw.ValueType.String,
			displayAttributeNames: [],
			createPropertyDefinitionFromJson: function ( base, json ) {
				return Object.assign( {}, base, {
					minimum: json.minimum,
					maximum: json.maximum
				} );
			},
			getExampleValue: function () {
				return nw.newStringValue( '2026-01-01T12:00:00Z' );
			},
			validate: validate,
			displayComponent: DateTimeDisplay,
			inputComponent: DateTimeInput,
			attributesEditor: DateTimeAttributesEditor,
			label: 'redherb-property-type-datetime',
			icon: icons.cdxIconClock
		} );
	} );
}() );
