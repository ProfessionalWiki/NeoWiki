<template>
	<div class="datetime-attributes cdx-field">
		<neo-nested-field :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<cdx-field
				class="datetime-attributes__minimum"
				:status="minimumError === null ? 'default' : 'error'"
				:messages="minimumError === null ? {} : { error: minimumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<cdx-text-input
					input-type="datetime-local"
					:model-value="minimumInput"
					@update:model-value="updateMinimum"
				/>
			</cdx-field>

			<cdx-field
				class="datetime-attributes__maximum"
				:status="maximumError === null ? 'default' : 'error'"
				:messages="maximumError === null ? {} : { error: maximumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<cdx-text-input
					input-type="datetime-local"
					:model-value="maximumInput"
					@update:model-value="updateMaximum"
				/>
			</cdx-field>
		</neo-nested-field>
	</div>
</template>

<script>
var vue = require( 'vue' );
var codex = require( './codex.js' );
var dateTimeConversion = require( './dateTimeConversion.js' );
var nw = require( 'ext.neowiki' );

var toLocalInputValue = dateTimeConversion.toLocalInputValue;
var fromLocalInputValue = dateTimeConversion.fromLocalInputValue;

// datetime-local wire values are always `YYYY-MM-DDTHH:mm` in a single
// timezone (host local), so lexicographic ordering matches chronological.
// The regex guards against malformed values bypassing the ordering check.
var DATETIME_LOCAL_WIRE_FORMAT = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;

function minExceedsMax( min, max ) {
	return DATETIME_LOCAL_WIRE_FORMAT.test( min ) &&
		DATETIME_LOCAL_WIRE_FORMAT.test( max ) &&
		min > max;
}

module.exports = exports = {
	components: {
		CdxField: codex.CdxField,
		CdxTextInput: codex.CdxTextInput,
		NeoNestedField: nw.NeoNestedField
	},
	props: {
		property: { type: Object, required: true }
	},
	emits: [ 'update:property' ],
	setup: function ( props, ctx ) {
		var minimumInput = vue.ref( toLocalInputValue( props.property.minimum ) );
		var maximumInput = vue.ref( toLocalInputValue( props.property.maximum ) );
		var minimumError = vue.ref( null );
		var maximumError = vue.ref( null );

		vue.watch(
			function () { return props.property.minimum; },
			function ( newVal ) {
				minimumInput.value = toLocalInputValue( newVal );
			}
		);

		vue.watch(
			function () { return props.property.maximum; },
			function ( newVal ) {
				maximumInput.value = toLocalInputValue( newVal );
			}
		);

		function updateMinimum( value ) {
			minimumInput.value = value;

			if ( minExceedsMax( value, maximumInput.value ) ) {
				minimumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
				return;
			}

			minimumError.value = null;
			maximumError.value = null;
			ctx.emit( 'update:property', { minimum: fromLocalInputValue( value ) } );
		}

		function updateMaximum( value ) {
			maximumInput.value = value;

			if ( minExceedsMax( minimumInput.value, value ) ) {
				maximumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
				return;
			}

			maximumError.value = null;
			minimumError.value = null;
			ctx.emit( 'update:property', { maximum: fromLocalInputValue( value ) } );
		}

		return {
			minimumInput: minimumInput,
			maximumInput: maximumInput,
			minimumError: minimumError,
			maximumError: maximumError,
			updateMinimum: updateMinimum,
			updateMaximum: updateMaximum
		};
	}
};
</script>
