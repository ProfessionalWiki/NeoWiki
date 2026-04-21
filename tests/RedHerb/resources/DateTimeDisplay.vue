<template>
	<time
		v-if="parsedIso !== null"
		:datetime="parsedIso"
	>
		{{ formattedValue }}
	</time>
	<span v-else>{{ fallbackText }}</span>
</template>

<script>
var vue = require( 'vue' );
var dateTimeConversion = require( './dateTimeConversion.js' );
var nw = require( 'ext.neowiki' );

var DATETIME_TYPE_NAME = 'dateTime';
var formatDateTimeForDisplay = dateTimeConversion.formatDateTimeForDisplay;

module.exports = exports = {
	props: {
		value: { type: Object, required: true },
		property: { type: Object, required: true }
	},
	setup: function ( props ) {
		var propertyType = nw.NeoWikiServices.getPropertyTypeRegistry().getType( DATETIME_TYPE_NAME );

		var rawValue = vue.computed( function () {
			if ( props.value.type !== nw.ValueType.String ) {
				return '';
			}
			return props.value.parts[ 0 ] || '';
		} );

		var parsedIso = vue.computed( function () {
			var raw = rawValue.value;
			if ( raw === '' ) {
				return null;
			}
			// Use the registered type's own validate() to decide whether the
			// stored value is well-formed. No errors = renderable as <time>.
			var errors = propertyType.validate( nw.newStringValue( raw ), {} );
			return errors.length === 0 ? raw : null;
		} );

		var formattedValue = vue.computed( function () {
			var iso = parsedIso.value;
			return iso === null ? '' : formatDateTimeForDisplay( iso );
		} );

		var fallbackText = vue.computed( function () {
			return parsedIso.value === null ? rawValue.value : '';
		} );

		return {
			parsedIso: parsedIso,
			formattedValue: formattedValue,
			fallbackText: fallbackText
		};
	}
};
</script>
