( function () {
	'use strict';

	const nw = require( 'ext.neowiki' );
	const icons = require( './icons.json' );
	const HEX_REGEX = require( './hexRegex.js' );
	const ColorDisplay = require( './ColorDisplay.vue' );
	const ColorInput = require( './ColorInput.vue' );
	const ColorAttributesEditor = require( './ColorAttributesEditor.vue' );
	const RedHerbCard = require( './RedHerbCard.vue' );

	const COLOR_TYPE_NAME = 'color';

	function validate( value, property ) {
		const errors = [];

		if ( property.required && ( value === undefined || value.parts.length === 0 ) ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		if ( value === undefined || value.parts.length === 0 ) {
			return errors;
		}

		const raw = value.parts[ 0 ];

		if ( !HEX_REGEX.test( raw ) ) {
			errors.push( { code: 'invalid-hex' } );
			return errors;
		}

		const allowed = property.allowedColors;
		if ( Array.isArray( allowed ) && allowed.length > 0 && !allowed.includes( raw ) ) {
			errors.push( { code: 'not-in-palette' } );
		}

		return errors;
	}

	mw.hook( 'neowiki.registration' ).add( ( registrar ) => {
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

		registrar.registerViewType( {
			typeName: 'redherb-card',
			component: RedHerbCard
		} );
	} );
}() );
