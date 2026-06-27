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
			></cdx-icon>
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
			></cdx-text-input>
		</div>
	</cdx-field>
</template>

<script>
const vue = require( 'vue' );
const codex = require( './codex.js' );
const icons = require( './icons.json' );
const nw = require( 'ext.neowiki' );

const COLOR_TYPE_NAME = 'color';
const HEX_REGEX = require( './hexRegex.js' );

// @vue/component
module.exports = exports = {
	components: {
		CdxField: codex.CdxField,
		CdxIcon: codex.CdxIcon,
		CdxTextInput: codex.CdxTextInput
	},
	props: {
		property: { type: Object, required: true },
		// eslint-disable-next-line vue/no-unused-properties -- consumed via vue.toRef( props, 'modelValue' ), which the rule cannot detect
		modelValue: { type: Object, default: undefined },
		label: { type: String, default: '' }
	},
	emits: [ 'update:modelValue' ],
	setup: function ( props, ctx ) {
		const propertyType = nw.NeoWikiServices.getPropertyTypeRegistry().getType( COLOR_TYPE_NAME );

		const stringInput = nw.useStringValueInput(
			vue.toRef( props, 'modelValue' ),
			vue.toRef( props, 'property' ),
			ctx.emit,
			propertyType
		);

		const previewHex = vue.computed( () => {
			const raw = stringInput.displayValues.value[ 0 ] || '';
			return HEX_REGEX.test( raw ) ? raw : '';
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
			background: repeating-linear-gradient( 45deg, @background-color-neutral-subtle, @background-color-neutral-subtle 4px, @background-color-neutral 4px, @background-color-neutral 8px );
		}
	}

	&__text {
		flex: 1;
	}
}
</style>
