<template>
	<div>
		{{ formattedValue }}
	</div>
</template>

<script>
var vue = require( 'vue' );
var nw = require( 'ext.neowiki' );

module.exports = exports = {
	props: {
		value: { type: Object, required: true },
		property: { type: Object, required: true }
	},
	setup: function ( props ) {
		var formattedValue = vue.computed( function () {
			if ( props.value.type !== nw.ValueType.String ) {
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

		return {
			formattedValue: formattedValue
		};
	}
};
</script>
