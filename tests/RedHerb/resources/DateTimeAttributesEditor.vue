<template>
	<div class="datetime-attributes cdx-field">
		<neo-nested-field :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<cdx-field>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<!-- eslint-disable-next-line vue/html-self-closing -->
				<input
					type="datetime-local"
					class="cdx-text-input__input"
					:value="toLocalInputValue( property.minimum )"
					@input="updateMinimum"
				>
			</cdx-field>

			<cdx-field>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<!-- eslint-disable-next-line vue/html-self-closing -->
				<input
					type="datetime-local"
					class="cdx-text-input__input"
					:value="toLocalInputValue( property.maximum )"
					@input="updateMaximum"
				>
			</cdx-field>
		</neo-nested-field>
	</div>
</template>

<script>
var codex = require( './codex.js' );
var nw = require( 'ext.neowiki' );

function toLocalInputValue( isoString ) {
	if ( !isoString ) {
		return '';
	}
	return isoString.replace( /Z$/, '' ).slice( 0, 16 );
}

function fromLocalInputValue( localValue ) {
	return localValue ? localValue + ':00Z' : undefined;
}

module.exports = exports = {
	components: {
		CdxField: codex.CdxField,
		NeoNestedField: nw.NeoNestedField
	},
	props: {
		property: { type: Object, required: true }
	},
	emits: [ 'update:property' ],
	setup: function ( props, ctx ) {
		function updateMinimum( event ) {
			ctx.emit( 'update:property', { minimum: fromLocalInputValue( event.target.value ) } );
		}

		function updateMaximum( event ) {
			ctx.emit( 'update:property', { maximum: fromLocalInputValue( event.target.value ) } );
		}

		return {
			toLocalInputValue: toLocalInputValue,
			updateMinimum: updateMinimum,
			updateMaximum: updateMaximum
		};
	}
};
</script>
