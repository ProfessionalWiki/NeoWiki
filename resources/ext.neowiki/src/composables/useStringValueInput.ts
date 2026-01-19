import { ref, watch, computed, ComputedRef, Ref } from 'vue';
import { Icon } from '@wikimedia/codex-icons';
import { ValidationMessages } from '@wikimedia/codex';
import { Value, StringValue } from '@/domain/Value.ts';
import { newStringValue, ValueType } from '@/domain/Value.ts';
import { MultiStringProperty } from '@/domain/PropertyDefinition.ts';
import { PropertyType } from '@/domain/PropertyType.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { validateValue } from '@/composables/useValueValidation.ts';

interface UseStringValueInputReturn {
	displayValues: ComputedRef<string[]>;
	fieldMessages: Ref<ValidationMessages>;
	inputMessages: Ref<ValidationMessages[]>;
	onInput: ( newValue: string | string[] ) => void;
	getCurrentValue: () => Value | undefined;
	startIcon: Icon | undefined;
}

export function useStringValueInput<P extends MultiStringProperty>(
	modelValue: Ref<Value | undefined>,
	property: Ref<P>,
	emit: ( e: 'update:modelValue', value: Value | undefined ) => void,
	propertyType: PropertyType,
): UseStringValueInputReturn {
	const internalValue: Ref<StringValue | undefined> = ref( undefined );
	const fieldMessages: Ref<ValidationMessages> = ref( {} );
	const inputMessages: Ref<ValidationMessages[]> = ref( [] );

	const startIcon: Icon | undefined = propertyType ?
		NeoWikiServices.getComponentRegistry().getIcon( propertyType.getTypeName() ) :
		undefined;

	function initializeInternalValue( value: Value | undefined ): void {
		if ( value && value.type === ValueType.String ) {
			const stringVal = value as StringValue;
			if ( stringVal.strings.length > 0 && stringVal.strings.some( ( s ) => s !== '' ) ) {
				internalValue.value = stringVal;
			} else {
				internalValue.value = undefined;
			}
		} else {
			internalValue.value = undefined;
		}
	}

	initializeInternalValue( modelValue.value );

	const displayValues: ComputedRef<string[]> = computed<string[]>( () =>
		internalValue.value ? internalValue.value.strings : [],
	);

	function doValidateInputs( valuesToValidate: string[] ): { errors: ValidationMessages[]; validStrings: string[] } {
		const perInputErrors: ValidationMessages[] = Array( valuesToValidate.length ).fill( {} );
		const currentValidStrings: string[] = [];

		valuesToValidate.forEach( ( inputValue, index ) => {
			if ( property.value.uniqueItems && inputValue !== '' && valuesToValidate.slice( 0, index ).includes( inputValue ) ) {
				perInputErrors[ index ] = { error: mw.message( 'neowiki-field-unique' ).text() };
			} else {
				perInputErrors[ index ] = validateValue( newStringValue( inputValue ), propertyType, property.value );
			}

			if ( Object.keys( perInputErrors[ index ] ).length === 0 ) {
				currentValidStrings.push( inputValue );
			}
		} );

		return {
			errors: perInputErrors,
			validStrings: currentValidStrings,
		};
	}

	function getFieldMessagesForDisplay( errors: ValidationMessages[] ): ValidationMessages {
		if ( property.value.multiple ) {
			// We don't show a summary message for multiple inputs.
			return {};
		}

		return errors.find( ( error ) => error && Object.keys( error ).length > 0 ) || {};
	}

	function updateValidationMessages( errors: ValidationMessages[] ): void {
		inputMessages.value = errors;
		fieldMessages.value = getFieldMessagesForDisplay( errors );
	}

	function onInput( newValue: string | string[] ): void {
		const currentInputValues = typeof newValue === 'string' ? [ newValue ] : newValue;
		const { errors, validStrings } = doValidateInputs( currentInputValues );

		updateValidationMessages( errors );

		let newStringValueInstance: StringValue | undefined;

		if ( validStrings.length > 0 ) {
			const tempValue = newStringValue( ...validStrings );
			if ( tempValue.strings.length > 0 && tempValue.strings.some( ( s ) => s !== '' ) ) {
				newStringValueInstance = tempValue;
			} else {
				newStringValueInstance = undefined;
			}
		} else {
			newStringValueInstance = undefined;
		}

		// TODO: Maybe we should have an unified way to handle deep comparison of values
		// https://github.com/ProfessionalWiki/NeoWiki/pull/382#discussion_r2075610162
		if ( JSON.stringify( internalValue.value ) !== JSON.stringify( newStringValueInstance ) ) {
			internalValue.value = newStringValueInstance;
			emit( 'update:modelValue', internalValue.value );
		}
	}

	watch( modelValue, ( newModelValue ) => {
		initializeInternalValue( newModelValue );
		updateValidationMessages( doValidateInputs( displayValues.value ).errors );
	}, { immediate: true } );

	// Watch for changes in property configuration (e.g., required, uniqueItems)
	watch( property, () => {
		updateValidationMessages( doValidateInputs( displayValues.value ).errors );
	}, { deep: true } );

	const getCurrentValue = (): Value | undefined =>
		internalValue.value;
	return {
		displayValues: displayValues,
		fieldMessages: fieldMessages,
		inputMessages: inputMessages,
		onInput: onInput,
		getCurrentValue: getCurrentValue,
		startIcon: startIcon,
	};
}
