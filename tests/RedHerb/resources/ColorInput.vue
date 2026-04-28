<template>
	<cdx-field
		class="ext-redherb-color-input"
		:status="fieldMessages.error ? 'error' : 'default'"
		:messages="fieldMessages"
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
				:model-value="displayValues[ 0 ] || ''"
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

		var stringInput = nw.useStringValueInput(
			vue.toRef( props, 'modelValue' ),
			vue.toRef( props, 'property' ),
			ctx.emit,
			propertyType
		);

		var previewHex = vue.computed( function () {
			var raw = stringInput.displayValues.value[ 0 ] || '';
			return HEX_PREVIEW_REGEX.test( raw ) ? raw : '';
		} );

		ctx.expose( {
			getCurrentValue: stringInput.getCurrentValue
		} );

		return {
			displayValues: stringInput.displayValues,
			fieldMessages: stringInput.fieldMessages,
			startIcon: stringInput.startIcon,
			onInput: stringInput.onInput,
			previewHex: previewHex,
			infoIcon: icons.cdxIconInfo
		};
	}
};
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
