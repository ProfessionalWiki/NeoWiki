var DateTimeDisplay = require( './DateTimeDisplay.vue' );
var DateTimeInput = require( './DateTimeInput.vue' );
var DateTimeAttributesEditor = require( './DateTimeAttributesEditor.vue' );
var icons = require( './icons.json' );

mw.hook( 'neowiki.registration' ).add( function ( registrar ) {
	registrar.registerType( {
		typeName: 'dateTime',
		valueType: 'string',
		displayAttributeNames: [],
		createPropertyDefinition: function ( base, json ) {
			return Object.assign( {}, base, {
				minimum: json.minimum,
				maximum: json.maximum
			} );
		},
		getExampleValue: function () {
			return { type: 'string', parts: [ '2026-01-01T12:00:00Z' ] };
		},
		validate: function ( value, property ) {
			var errors = [];

			if ( property.required && value === undefined ) {
				errors.push( { code: 'required' } );
				return errors;
			}

			if ( value !== undefined && value.parts.length > 0 ) {
				var dateString = value.parts[ 0 ];
				var timestamp = Date.parse( dateString );

				if ( isNaN( timestamp ) ) {
					errors.push( { code: 'invalid-datetime' } );
					return errors;
				}

				if ( property.minimum !== undefined && timestamp < Date.parse( property.minimum ) ) {
					errors.push( { code: 'min-value', args: [ property.minimum ] } );
				}
				if ( property.maximum !== undefined && timestamp > Date.parse( property.maximum ) ) {
					errors.push( { code: 'max-value', args: [ property.maximum ] } );
				}
			}

			return errors;
		},
		displayComponent: DateTimeDisplay,
		inputComponent: DateTimeInput,
		attributesEditor: DateTimeAttributesEditor,
		label: 'neowiki-property-type-datetime',
		icon: icons.cdxIconClock
	} );
} );
