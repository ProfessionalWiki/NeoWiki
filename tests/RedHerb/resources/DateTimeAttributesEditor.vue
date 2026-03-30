<template>
	<div class="datetime-attributes cdx-field">
		<label>{{ mw.message( 'neowiki-property-editor-range' ).text() }}</label>

		<cdx-field>
			<template #label>
				{{ mw.message( 'neowiki-property-editor-minimum' ).text() }}
			</template>
			<input
				type="datetime-local"
				class="cdx-text-input__input"
				:value="toLocalInputValue( property.minimum )"
				@input="updateMinimum"
			>
		</cdx-field>

		<cdx-field>
			<template #label>
				{{ mw.message( 'neowiki-property-editor-maximum' ).text() }}
			</template>
			<input
				type="datetime-local"
				class="cdx-text-input__input"
				:value="toLocalInputValue( property.maximum )"
				@input="updateMaximum"
			>
		</cdx-field>
	</div>
</template>

<script>
module.exports = exports = {
	name: 'DateTimeAttributesEditor',
	props: {
		property: { type: Object, required: true }
	},
	emits: [ 'update:property' ],
	setup: function ( props, ctx ) {
		function toLocalInputValue( isoString ) {
			if ( !isoString ) {
				return '';
			}
			return isoString.replace( /Z$/, '' ).slice( 0, 16 );
		}

		function fromLocalInputValue( localValue ) {
			return localValue ? localValue + ':00Z' : undefined;
		}

		function updateMinimum( event ) {
			ctx.emit( 'update:property', { minimum: fromLocalInputValue( event.target.value ) } );
		}

		function updateMaximum( event ) {
			ctx.emit( 'update:property', { maximum: fromLocalInputValue( event.target.value ) } );
		}

		return {
			mw: mw,
			toLocalInputValue: toLocalInputValue,
			updateMinimum: updateMinimum,
			updateMaximum: updateMaximum
		};
	}
};
</script>
