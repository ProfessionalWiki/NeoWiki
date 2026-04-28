<template>
	<cdx-field
		class="ext-redherb-color-input"
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:optional="property.required === false"
	>
		<template #label>
			{{ label }}
			<cdx-icon
				v-if="property.description"
				v-tooltip="property.description"
				:icon="infoIcon"
				size="small"
			/>
		</template>
		<div class="ext-redherb-color-input__row">
			<span
				class="ext-redherb-color-input__swatch"
				:class="{ 'ext-redherb-color-input__swatch--empty': !previewHex }"
				:style="previewHex ? { backgroundColor: previewHex } : {}"
				aria-hidden="true"
			></span>
			<cdx-text-input
				class="ext-redherb-color-input__text"
				placeholder="#ff5733"
				:start-icon="startIcon"
				:model-value="displayValue"
				@update:model-value="onInput"
			/>
		</div>
	</cdx-field>
</template>

<script>
var vue = require( 'vue' );
var codex = require( './codex.js' );
var icons = require( './icons.json' );
var nw = require( 'ext.neowiki' );

var COLOR_TYPE_NAME = 'color';
var HEX_PREVIEW_REGEX = require( './hexRegex.js' );

module.exports = exports = {
	components: {
		CdxField: codex.CdxField,
		CdxIcon: codex.CdxIcon,
		CdxTextInput: codex.CdxTextInput
	},
	props: {
		property: { type: Object, required: true },
		modelValue: { type: Object, default: undefined },
		label: { type: String, default: '' }
	},
	emits: [ 'update:modelValue' ],
	setup: function ( props, ctx ) {
		var propertyType = nw.NeoWikiServices.getPropertyTypeRegistry().getType( COLOR_TYPE_NAME );
		var startIcon = nw.NeoWikiServices.getComponentRegistry().getIcon( COLOR_TYPE_NAME );

		// internalValue holds only well-formed values that have passed
		// validation; it is what `getCurrentValue` exposes to the parent at
		// save time. userInputValue holds the raw string the user is typing,
		// so mid-edit invalid states stay visible without leaking out.
		var internalValue = vue.ref( validInitialValue( props.modelValue ) );
		var userInputValue = vue.ref( null );
		var validationError = vue.ref( null );

		var displayValue = vue.computed( function () {
			if ( userInputValue.value !== null ) {
				return userInputValue.value;
			}
			return internalValue.value === undefined ? '' : internalValue.value.parts[ 0 ];
		} );

		var previewHex = vue.computed( function () {
			return HEX_PREVIEW_REGEX.test( displayValue.value ) ? displayValue.value : '';
		} );

		function validate() {
			var raw = displayValue.value;
			var value = raw === '' ? undefined : nw.newStringValue( raw );
			var messages = nw.validateValue( value, propertyType, props.property );
			validationError.value = messages.error === undefined ? null : messages.error;
		}

		function computeInternalFor( raw ) {
			if ( raw === '' || !HEX_PREVIEW_REGEX.test( raw ) ) {
				return undefined;
			}
			return nw.newStringValue( raw );
		}

		function onInput( newValue ) {
			userInputValue.value = newValue;
			var nextInternal = computeInternalFor( newValue );
			if ( !sameValue( internalValue.value, nextInternal ) ) {
				internalValue.value = nextInternal;
				ctx.emit( 'update:modelValue', nextInternal );
			}
			validate();
		}

		vue.watch(
			function () { return props.modelValue; },
			function ( newValue ) {
				var previous = internalValue.value;
				internalValue.value = validInitialValue( newValue );
				if ( !sameValue( previous, internalValue.value ) ) {
					userInputValue.value = null;
				}
				validate();
			}
		);

		vue.watch(
			function () { return props.property; },
			function () { validate(); }
		);

		validate();

		ctx.expose( {
			getCurrentValue: function () { return internalValue.value; }
		} );

		return {
			displayValue: displayValue,
			previewHex: previewHex,
			validationError: validationError,
			infoIcon: icons.cdxIconInfo,
			startIcon: startIcon,
			onInput: onInput
		};
	}
};

function validInitialValue( modelValue ) {
	if (
		modelValue !== undefined &&
		modelValue.type === nw.ValueType.String &&
		modelValue.parts.length > 0 &&
		HEX_PREVIEW_REGEX.test( modelValue.parts[ 0 ] )
	) {
		return modelValue;
	}
	return undefined;
}

function sameValue( a, b ) {
	if ( a === b ) {
		return true;
	}
	if ( a === undefined || b === undefined ) {
		return false;
	}
	return a.parts.length === b.parts.length && a.parts[ 0 ] === b.parts[ 0 ];
}
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-redherb-color-input {
	&__row {
		display: flex;
		align-items: center;
		gap: @spacing-50;
	}

	&__swatch {
		display: inline-block;
		width: @size-150;
		height: @size-150;
		border: @border-base;
		border-radius: @border-radius-base;
		flex-shrink: 0;

		&--empty {
			background:
				repeating-linear-gradient(
					45deg,
					@background-color-neutral-subtle,
					@background-color-neutral-subtle 4px,
					@background-color-neutral 4px,
					@background-color-neutral 8px
				);
		}
	}

	&__text {
		flex: 1;
	}
}
</style>
