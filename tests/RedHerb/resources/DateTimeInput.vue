<template>
	<cdx-field
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
		<!-- eslint-disable-next-line vue/html-self-closing -->
		<input
			type="datetime-local"
			class="cdx-text-input__input"
			:value="internalInputValue"
			:min="toLocalInputValue( property.minimum )"
			:max="toLocalInputValue( property.maximum )"
			@input="onInput"
		>
	</cdx-field>
</template>

<script>
var vue = require( 'vue' );
var codex = require( './codex.js' );
var icons = require( './icons.json' );
var nw = require( 'ext.neowiki' );

var DATETIME_TYPE_NAME = 'dateTime';

function toLocalInputValue( isoString ) {
	if ( !isoString ) {
		return '';
	}
	return isoString.replace( /Z$/, '' ).slice( 0, 16 );
}

function fromLocalInputValue( localValue ) {
	if ( !localValue ) {
		return '';
	}
	return localValue + ':00Z';
}

module.exports = exports = {
	components: {
		CdxField: codex.CdxField,
		CdxIcon: codex.CdxIcon
	},
	props: {
		property: { type: Object, required: true },
		modelValue: { type: Object, default: undefined },
		label: { type: String, default: '' }
	},
	emits: [ 'update:modelValue' ],
	setup: function ( props, ctx ) {
		var validationError = vue.ref( null );
		var internalInputValue = vue.ref( '' );

		function initializeInputValue( value ) {
			if ( value && value.type === nw.ValueType.String ) {
				var str = value.parts[ 0 ];
				internalInputValue.value = str ? toLocalInputValue( str ) : '';
			} else {
				internalInputValue.value = '';
			}
		}

		initializeInputValue( props.modelValue );

		var propertyType = nw.NeoWikiServices.getPropertyTypeRegistry().getType( DATETIME_TYPE_NAME );

		function validate( value ) {
			var errors = propertyType.validate( value, props.property );
			if ( errors.length === 0 ) {
				validationError.value = null;
				return;
			}
			var error = errors[ 0 ];
			var args = error.args || [];
			validationError.value = mw.message.apply(
				null,
				[ 'neowiki-field-' + error.code ].concat( args )
			).text();
		}

		function onInput( event ) {
			internalInputValue.value = event.target.value;
			var isoValue = fromLocalInputValue( event.target.value );
			var value = isoValue ? nw.newStringValue( isoValue ) : undefined;
			ctx.emit( 'update:modelValue', value );
			validate( value );
		}

		vue.watch(
			function () { return props.modelValue; },
			function ( newValue ) {
				initializeInputValue( newValue );
				validate( newValue && newValue.type === nw.ValueType.String ? newValue : undefined );
			}
		);

		vue.watch(
			function () { return props.property; },
			function () {
				validate( props.modelValue && props.modelValue.type === nw.ValueType.String ? props.modelValue : undefined );
			}
		);

		validate( props.modelValue && props.modelValue.type === nw.ValueType.String ? props.modelValue : undefined );

		ctx.expose( {
			getCurrentValue: function () {
				var isoValue = fromLocalInputValue( internalInputValue.value );
				return isoValue ? nw.newStringValue( isoValue ) : undefined;
			}
		} );

		return {
			validationError: validationError,
			internalInputValue: internalInputValue,
			infoIcon: icons.cdxIconInfo,
			toLocalInputValue: toLocalInputValue,
			onInput: onInput
		};
	}
};
</script>
