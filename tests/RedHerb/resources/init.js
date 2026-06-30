( function () {
	'use strict';

	const nw = require( 'ext.neowiki' );
	const icons = require( './icons.json' );
	const ColorDisplay = require( './ColorDisplay.vue' );
	const ColorInput = require( './ColorInput.vue' );
	const ColorAttributesEditor = require( './ColorAttributesEditor.vue' );
	const RedHerbCard = require( './RedHerbCard.vue' );

	const COLOR_TYPE_NAME = 'color';

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
