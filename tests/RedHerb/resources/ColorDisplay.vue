<template>
	<span
		v-if="parsedHex !== null"
		class="ext-redherb-color-display"
	>
		<span
			class="ext-redherb-color-display__swatch"
			:style="{ backgroundColor: parsedHex }"
			aria-hidden="true"
		></span>
		<code class="ext-redherb-color-display__hex">{{ parsedHex }}</code>
	</span>
	<i18n-slot
		v-else
		:message-key="'redherb-color-invalid-fallback'"
	>
		<code>{{ rawValue }}</code>
	</i18n-slot>
</template>

<script>
var vue = require( 'vue' );
var nw = require( 'ext.neowiki' );

// Display intentionally checks format only and ignores input-time
// constraints like allowedColors: a stored value that was valid when
// it was saved should keep rendering as a swatch even if the schema's
// palette has since narrowed.
var HEX_REGEX = require( './hexRegex.js' );

module.exports = exports = {
	components: {
		I18nSlot: nw.I18nSlot
	},
	props: {
		value: { type: Object, required: true },
		property: { type: Object, required: true }
	},
	setup: function ( props ) {
		var rawValue = vue.computed( function () {
			if ( props.value.type !== nw.ValueType.String ) {
				return '';
			}
			return props.value.parts[ 0 ] || '';
		} );

		var parsedHex = vue.computed( function () {
			var raw = rawValue.value;
			return HEX_REGEX.test( raw ) ? raw : null;
		} );

		return {
			rawValue: rawValue,
			parsedHex: parsedHex
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-redherb-color-display {
	display: inline-flex;
	align-items: center;
	gap: @spacing-25;

	&__swatch {
		display: inline-block;
		width: @size-100;
		height: @size-100;
		border: @border-base;
		border-radius: @border-radius-base;
	}

	&__hex {
		font-family: @font-family-monospace;
	}
}
</style>
