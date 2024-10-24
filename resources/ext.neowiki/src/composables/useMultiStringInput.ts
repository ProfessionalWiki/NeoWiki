import { ref, computed, nextTick } from 'vue';
import type { Ref, ComputedRef } from 'vue';
import type { ValidationStatusType, ValidationMessages } from '@wikimedia/codex';
import type { StringValue, Value } from '@neo/domain/Value';
import { ValueType } from '@neo/domain/Value';
import { newStringValue } from '@neo/domain/Value';

export type ValidationResult = {
	isValid: boolean;
	statuses: ValidationStatusType[];
	messages: ValidationMessages[];
};

export interface MultiStringInputReturn {
	inputValues: Ref<string[]>;
	validationState: Ref<ValidationResult>;
	isAddButtonDisabled: ComputedRef<boolean>;
	isRequiredFieldInValid: ComputedRef<boolean>;
	handleInput: ( value: string, index: number, validateFn: ( values: string[] ) => ValidationResult ) => void;
	handleAdd: ( fieldType: string ) => Promise<void>;
	handleRemove: ( index: number, validateFn: ( values: string[] ) => ValidationResult ) => void;
}

// TODO: use prop and emit types e.g. AttributesEditorContract
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const useMultiStringInput = ( props: any, emit: any ): MultiStringInputReturn => {
	const buildInitialInputValues = ( value: Value ): string[] => {
		if ( value.type === ValueType.String ) {
			const strings = ( value as StringValue ).strings;
			return strings.length > 0 ? strings : [ '' ];
		}
		return [ '' ];
	};

	const inputValues = ref<string[]>( buildInitialInputValues( props.modelValue ) );
	const validationState = ref<ValidationResult>( {
		isValid: true,
		statuses: [],
		messages: []
	} );

	const isAddButtonDisabled = computed( (): boolean => inputValues.value.some( ( value ) => value.trim() === '' || !validationState.value.isValid )
	);

	const isRequiredFieldInValid = computed( (): boolean => {
		const areAllFieldsEmpty = inputValues.value.every( ( value ) => value.trim() === '' );
		return areAllFieldsEmpty && props.property.required;
	} );

	const handleInput = ( newValue: string, index: number, validateFn: ( values: string[] ) => ValidationResult ): void => {
		inputValues.value[ index ] = newValue;
		const validation = validateFn( inputValues.value );
		validationState.value = validation;
		emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
		emit( 'validation', validation.isValid );
	};

	const handleAdd = async ( fieldType: string ): Promise<void> => {
		inputValues.value.push( '' );
		emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
		emit( 'validation', false );
		await nextTick();

		const inputRef = `${ inputValues.value.length - 1 }-${ props.property.name }-${ fieldType }-input`;
		focusInput( inputRef );
	};

	const focusInput = ( inputRef: string ): void => {
		const input = document.querySelector( `[input-ref="${ inputRef }"]` ) as HTMLInputElement | null;
		input?.focus();
	};

	const handleRemove = ( index: number, validateFn: ( values: string[] ) => ValidationResult ): void => {
		inputValues.value.splice( index, 1 );
		const validation = validateFn( inputValues.value );
		validationState.value = validation;
		emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
		emit( 'validation', validation.isValid );
	};

	return {
		inputValues,
		validationState,
		isAddButtonDisabled,
		isRequiredFieldInValid,
		handleInput,
		handleAdd,
		handleRemove
	};
};
