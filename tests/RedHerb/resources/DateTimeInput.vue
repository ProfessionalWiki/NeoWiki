<template>
	<cdx-field
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:optional="property.required === false"
	>
		<template #label>
			{{ label }}
		</template>
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
var ref = require( 'vue' ).ref;
var watch = require( 'vue' ).watch;

module.exports = exports = {
	name: 'DateTimeInput',
	props: {
		modelValue: { type: Object, default: undefined },
		label: { type: String, default: '' },
		property: { type: Object, required: true }
	},
	emits: [ 'update:modelValue' ],
	setup: function ( props, ctx ) {
		var validationError = ref( null );
		var internalInputValue = ref( '' );

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

		function toStringValue( str ) {
			if ( !str ) {
				return undefined;
			}
			var trimmed = str.trim();
			return trimmed ? { type: 'string', parts: [ trimmed ] } : undefined;
		}

		function initializeInputValue( value ) {
			if ( value && value.type === 'string' ) {
				var str = value.parts[ 0 ];
				internalInputValue.value = str ? toLocalInputValue( str ) : '';
			} else {
				internalInputValue.value = '';
			}
		}

		function validate( value ) {
			var errors = [];
			if ( value !== undefined && value.parts.length > 0 ) {
				var timestamp = Date.parse( value.parts[ 0 ] );
				if ( isNaN( timestamp ) ) {
					errors.push( { code: 'invalid-datetime' } );
				} else {
					if ( props.property.minimum !== undefined && timestamp < Date.parse( props.property.minimum ) ) {
						errors.push( { code: 'min-value', args: [ props.property.minimum ] } );
					}
					if ( props.property.maximum !== undefined && timestamp > Date.parse( props.property.maximum ) ) {
						errors.push( { code: 'max-value', args: [ props.property.maximum ] } );
					}
				}
			}
			validationError.value = errors.length === 0 ? null :
				mw.message( 'neowiki-field-' + errors[ 0 ].code, ( errors[ 0 ].args || [] )[ 0 ] ).text();
		}

		initializeInputValue( props.modelValue );

		watch( function () { return props.modelValue; }, function ( newValue ) {
			initializeInputValue( newValue );
			validate( newValue && newValue.type === 'string' ? newValue : undefined );
		} );

		watch( function () { return props.property; }, function () {
			validate( props.modelValue && props.modelValue.type === 'string' ? props.modelValue : undefined );
		} );

		function onInput( event ) {
			var target = event.target;
			internalInputValue.value = target.value;
			var isoValue = fromLocalInputValue( target.value );
			var value = toStringValue( isoValue );
			ctx.emit( 'update:modelValue', value );
			validate( value );
		}

		validate( props.modelValue && props.modelValue.type === 'string' ? props.modelValue : undefined );

		return {
			validationError: validationError,
			internalInputValue: internalInputValue,
			toLocalInputValue: toLocalInputValue,
			onInput: onInput,
			getCurrentValue: function () {
				var isoValue = fromLocalInputValue( internalInputValue.value );
				return toStringValue( isoValue );
			}
		};
	}
};
</script>
