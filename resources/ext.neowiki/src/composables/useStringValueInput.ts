import { ref, watch, computed, ComputedRef, Ref } from 'vue';
import { Icon } from '@wikimedia/codex-icons';
import { ValidationMessages } from '@wikimedia/codex';
import { Value, StringValue } from '@neo/domain/Value.ts';
import { newStringValue, ValueType } from '@neo/domain/Value.ts';
import { MultiStringProperty } from '@neo/domain/PropertyDefinition.ts';
import { PropertyType } from '@neo/domain/PropertyType.ts';
import { ValueInputProps } from '@/components/Value/ValueInputContract';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { validateValue } from '@/composables/useValueValidation.ts';

interface UseStringValueInputReturn {
	displayValues: ComputedRef<string[]>;
	fieldMessages: Ref<ValidationMessages>;
	inputMessages: Ref<ValidationMessages[]>;
	onInput: ( newValue: string | string[] ) => void;
	getCurrentValue: () => Value | undefined;
	startIcon: ComputedRef<Icon | undefined>;
}

export function useStringValueInput<P extends MultiStringProperty>(
	props: ValueInputProps<P>,
	emit: ( e: 'update:modelValue', value: Value | undefined ) => void,
	propertyTypeName: string
): UseStringValueInputReturn {
	const internalValue: Ref<StringValue | undefined> = ref( undefined );
	const fieldMessages: Ref<ValidationMessages> = ref( {} );
	const inputMessages: Ref<ValidationMessages[]> = ref( [] );

	const propertyType: ComputedRef<PropertyType> = computed( () =>
		NeoWikiServices.getPropertyTypeRegistry().getType( propertyTypeName )
	);

	const startIcon: ComputedRef<Icon> = computed( () =>
		NeoWikiServices.getComponentRegistry().getIcon( propertyTypeName )
	);

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

	initializeInternalValue( props.modelValue );

	const displayValues: ComputedRef<string[]> = computed<string[]>( () =>
		internalValue.value ? internalValue.value.strings : []
	);

	function doValidateInputs( valuesToValidate: string[] ): { errors: ValidationMessages[]; validStrings: string[] } {
		const perInputErrors: ValidationMessages[] = Array( valuesToValidate.length ).fill( {} );
		const currentValidStrings: string[] = [];

		if ( !propertyType.value ) {
			valuesToValidate.forEach( ( _, index ) => {
				perInputErrors[ index ] = { error: 'Property type configuration error.' };
			} );
			return { errors: perInputErrors, validStrings: [] };
		}

		valuesToValidate.forEach( ( inputValue, index ) => {
			if ( props.property.uniqueItems && inputValue !== '' && valuesToValidate.slice( 0, index ).includes( inputValue ) ) {
				perInputErrors[ index ] = { error: mw.message( 'neowiki-field-unique' ).text() };
			} else {
				perInputErrors[ index ] = validateValue( newStringValue( inputValue ), propertyType.value, props.property );
			}

			if ( Object.keys( perInputErrors[ index ] ).length === 0 ) {
				currentValidStrings.push( inputValue );
			}
		} );

		return {
			errors: perInputErrors,
			validStrings: currentValidStrings
		};
	}

	function getFieldMessagesForDisplay( errors: ValidationMessages[] ): ValidationMessages {
		if ( !props.property.multiple ) {
			const firstError = errors.find( ( error ) => error && Object.keys( error ).length > 0 );
			return firstError || {};
		}
		// TODO: Do we have a use case for a summary message for multiple inputs?
		return {};
	}

	function onInput( newValue: string | string[] ): void {
		const currentInputValues = typeof newValue === 'string' ? [ newValue ] : newValue;
		const { errors, validStrings } = doValidateInputs( currentInputValues );

		inputMessages.value = errors;
		fieldMessages.value = getFieldMessagesForDisplay( errors );

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

	watch( () => props.modelValue, ( newModelValue ) => {
		initializeInternalValue( newModelValue );
		const validationResult = doValidateInputs( displayValues.value );
		inputMessages.value = validationResult.errors;
		fieldMessages.value = getFieldMessagesForDisplay( validationResult.errors );
	} );

	// Watch for changes in property configuration (e.g., required, uniqueItems)
	// TODO: Do we need to monitor property configuration changes at all?
	watch( () => props.property, () => {
		const validationResult = doValidateInputs( displayValues.value ); // Re-validate with current values
		inputMessages.value = validationResult.errors;
		fieldMessages.value = getFieldMessagesForDisplay( validationResult.errors );
	}, { deep: true } );

	const initialValidation = doValidateInputs( displayValues.value );
	inputMessages.value = initialValidation.errors;
	fieldMessages.value = getFieldMessagesForDisplay( initialValidation.errors );

	const getCurrentValue = (): Value | undefined =>
		internalValue.value;
	return {
		displayValues: displayValues,
		fieldMessages: fieldMessages,
		inputMessages: inputMessages,
		onInput: onInput,
		getCurrentValue: getCurrentValue,
		startIcon: startIcon
	};
}
