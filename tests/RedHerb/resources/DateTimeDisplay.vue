<template>
	<div>{{ formattedValue }}</div>
</template>

<script>
var computed = require( 'vue' ).computed;

module.exports = exports = {
	name: 'DateTimeDisplay',
	props: {
		value: { type: Object, required: true },
		property: { type: Object, required: true }
	},
	setup: function ( props ) {
		var formattedValue = computed( function () {
			if ( props.value.type !== 'string' ) {
				return '';
			}
			var dateString = props.value.parts[ 0 ];
			if ( !dateString ) {
				return '';
			}
			var date = new Date( dateString );
			if ( isNaN( date.getTime() ) ) {
				return dateString;
			}
			return date.toLocaleString( undefined, { timeZone: 'UTC' } );
		} );
		return { formattedValue: formattedValue };
	}
};
</script>
