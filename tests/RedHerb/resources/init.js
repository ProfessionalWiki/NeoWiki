( function () {
	'use strict';

	var nw = require( 'ext.neowiki' );
	var icons = require( './icons.json' );
	var ColorDisplay = require( './ColorDisplay.vue' );
	var ColorInput = require( './ColorInput.vue' );
	var ColorAttributesEditor = require( './ColorAttributesEditor.vue' );

	var COLOR_TYPE_NAME = 'color';

	// eslint-disable-next-line security/detect-unsafe-regex -- bounded quantifiers, no backtracking
	var HEX_REGEX = /^#[0-9a-fA-F]{6}$/;

	function validate( value, property ) {
		var errors = [];

		if ( property.required && ( value === undefined || value.parts.length === 0 ) ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		if ( value === undefined || value.parts.length === 0 ) {
			return errors;
		}

		var raw = value.parts[ 0 ];

		if ( !HEX_REGEX.test( raw ) ) {
			errors.push( { code: 'invalid-hex' } );
			return errors;
		}

		var allowed = property.allowedColors;
		if ( Array.isArray( allowed ) && allowed.length > 0 && allowed.indexOf( raw ) === -1 ) {
			errors.push( { code: 'not-in-palette' } );
		}

		return errors;
	}

	mw.hook( 'neowiki.registration' ).add( function ( registrar ) {
		registrar.registerPropertyType( {
			typeName: COLOR_TYPE_NAME,
			valueType: nw.ValueType.String,
			displayAttributeNames: [],
			createPropertyDefinitionFromJson: function ( base, json ) {
				return Object.assign( {}, base, {
					allowedColors: Array.isArray( json.allowedColors ) ? json.allowedColors : []
				} );
			},
			getExampleValue: function () {
				return nw.newStringValue( '#ff5733' );
			},
			validate: validate,
			displayComponent: ColorDisplay,
			inputComponent: ColorInput,
			attributesEditor: ColorAttributesEditor,
			label: 'redherb-property-type-color',
			icon: icons.cdxIconHighlight
		} );
	} );
}() );
