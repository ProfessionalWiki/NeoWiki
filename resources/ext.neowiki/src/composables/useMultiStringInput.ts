import { ref, computed, nextTick } from 'vue';
import type { Ref, ComputedRef } from 'vue';
import type { StringValue, Value } from '@neo/domain/Value';
import { ValueType } from '@neo/domain/Value';
import { newStringValue } from '@neo/domain/Value';
import type { TextProperty } from '@neo/domain/valueFormats/Text';
import { ValueInputProps, ValueInputEmitFunction, ValidationState, ValidationMessages } from '@/components/Value/ValueInputContract';
import { PropertyName } from '@neo/domain/PropertyDefinition.ts';
import { ValidationStatusType } from '@wikimedia/codex';

interface InputArrayReturn {
	inputValues: Ref<string[]>;
	updateValue: ( value: string, index: number ) => void;
	addValue: () => void;
	removeValue: ( index: number ) => void;
}

const useInputArray = (
	initialValue: Value,
	emit: ValueInputEmitFunction
): InputArrayReturn => {
	const buildInitialInputValues = ( value: Value ): string[] => {
		if ( value.type === ValueType.String ) {
			const strings = ( value as StringValue ).strings;
			return strings.length > 0 ? strings : [ '' ];
		}
		return [ '' ];
	};

	const inputValues = ref<string[]>( buildInitialInputValues( initialValue ) );

	const emitUpdatedValue = ( values: string[] ): void => {
		emit( 'update:modelValue', newStringValue( ...values ) );
	};

	const updateValue = ( value: string, index: number ): void => {
		inputValues.value[ index ] = value;
		emitUpdatedValue( inputValues.value );
	};

	const addValue = (): void => {
		inputValues.value.push( '' );
		emitUpdatedValue( inputValues.value );
	};

	const removeValue = ( index: number ): void => {
		inputValues.value.splice( index, 1 );
		emitUpdatedValue( inputValues.value );
	};

	return {
		inputValues,
		updateValue,
		addValue,
		removeValue
	};
};

interface ValidationReturn {
	validationState: Ref<ValidationState>;
	validateValues: ( values: string[], isRequired: boolean ) => void;
	isValid: ComputedRef<boolean>;
}

const useValidation = ( emit: ValueInputEmitFunction ): ValidationReturn => {
	const validationState = ref<ValidationState>( {
		isValid: true,
		statuses: [],
		messages: []
	} );

	const isValid = computed( () => validationState.value.isValid );

	const validateValues = ( values: string[], isRequired: boolean ): void => {
		const newState = {
			isValid: true,
			statuses: [] as ValidationStatusType[],
			messages: [] as ValidationMessages[]
		};

		values.forEach( ( value, index ) => {
			const isEmpty = value.trim() === '';
			const isFirstField = index === 0;

			if ( isRequired && isEmpty && isFirstField ) {
				newState.isValid = false;
				newState.statuses.push( 'error' );
				newState.messages.push( { error: isEmpty ? 'neowiki-field-required' : 'neowiki-field-invalid' } );
			} else {
				newState.statuses.push( 'success' );
				newState.messages.push( {} );
			}
		} );

		validationState.value = newState;
		emit( 'validation', newState.isValid );
	};

	return {
		validationState,
		validateValues,
		isValid
	};
};

interface FocusReturn {
	focusInput: ( fieldType: string, index: number, propertyName: PropertyName ) => Promise<void>;
}

const useFocus = (): FocusReturn => {
	const focusInput = async ( fieldType: string, index: number, propertyName: PropertyName ): Promise<void> => {
		await nextTick();
		const inputRef = `${ index }-${ propertyName }-${ fieldType }-input`;
		const input = document.querySelector( `[input-ref="${ inputRef }"]` ) as HTMLInputElement | null;
		input?.focus();
	};

	return { focusInput };
};

export interface MultiStringInputReturn {
	inputValues: Ref<string[]>;
	validationState: Ref<ValidationState>;
	isAddButtonDisabled: ComputedRef<boolean>;
	isRequiredFieldInValid: ComputedRef<boolean>;
	handleInput: ( value: string, index: number, validateFn: ( values: string[] ) => ValidationState ) => void;
	handleAdd: ( fieldType: string ) => Promise<void>;
	handleRemove: ( index: number, validateFn: ( values: string[] ) => ValidationState ) => void;
}

export const useMultiStringInput = (
	props: ValueInputProps<TextProperty>,
	emit: ValueInputEmitFunction
): MultiStringInputReturn => {
	const { inputValues, updateValue, addValue, removeValue } = useInputArray( props.modelValue, emit );
	const { validationState } = useValidation( emit );
	const { focusInput } = useFocus();

	const isAddButtonDisabled = computed( (): boolean => inputValues.value.some( ( value ) => value.trim() === '' || !validationState.value.isValid ) );

	const isRequiredFieldInValid = computed( (): boolean => {
		const areAllFieldsEmpty = inputValues.value.every( ( value ) => value.trim() === '' );
		return areAllFieldsEmpty && props.property.required;
	} );

	const handleInput = ( value: string, index: number, validateFn: ( values: string[] ) => ValidationState ): void => {
		updateValue( value, index );
		const validation = validateFn( inputValues.value );
		validationState.value = validation;
		emit( 'validation', validation.isValid );
	};

	const handleAdd = async ( fieldType: string ): Promise<void> => {
		addValue();
		emit( 'validation', false );
		await focusInput( fieldType, inputValues.value.length - 1, props.property.name );
	};

	const handleRemove = ( index: number, validateFn: ( values: string[] ) => ValidationState ): void => {
		removeValue( index );
		const validation = validateFn( inputValues.value );
		validationState.value = validation;
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
