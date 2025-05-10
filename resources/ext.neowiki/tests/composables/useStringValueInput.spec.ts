import { reactive, markRaw } from 'vue';
import { describe, it, expect, vi, beforeEach, type MockedFunction } from 'vitest';
import { nextTick } from 'vue';

vi.mock( '@/composables/useValueValidation.ts', () => ( {
	validateValue: vi.fn()
} ) );

vi.mock( '@/NeoWikiServices.ts', () => ( {
	NeoWikiServices: {
		getPropertyTypeRegistry: vi.fn().mockReturnValue( {
			getType: vi.fn() // getType is a new vi.fn() created by the factory
		} ),
		getComponentRegistry: vi.fn().mockReturnValue( {
			getIcon: vi.fn().mockReturnValue( undefined )
		} )
	}
} ) );

import { validateValue } from '@/composables/useValueValidation.ts';
import { useStringValueInput } from '@/composables/useStringValueInput.ts';
import { Value, newStringValue, ValueType } from '@neo/domain/Value.ts';
import { MultiStringProperty, PropertyName, PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { PropertyType, ValueValidationError } from '@neo/domain/PropertyType.ts';
import { ValueInputProps } from '@/components/Value/ValueInputContract';
import { NeoWikiServices } from '@/NeoWikiServices.ts'; // This will be the mocked version

const mockedValidateValue = validateValue as MockedFunction<typeof validateValue>;

vi.stubGlobal( 'mw', {
	message: vi.fn( ( key: string ) => ( {
		text: () => key,
		parse: () => key
	} ) )
} );

const createMockPropertyDefinition = ( options: Partial<MultiStringProperty> = {} ): MultiStringProperty => {
	const defaults = {
		name: new PropertyName( 'testProp' ),
		description: '',
		type: 'TestStringProperty',
		required: false,
		multiple: false,
		uniqueItems: false
	};

	return {
		...defaults,
		...options
	} as MultiStringProperty;
};

const createMockPropertyType = ( typeName: string, options: Partial<PropertyType> = {} ): PropertyType => ( {
	getTypeName: vi.fn().mockReturnValue( typeName ),
	getValueType: vi.fn().mockReturnValue( ValueType.String ),
	createPropertyDefinitionFromJson: vi.fn( ( base: PropertyDefinition, json: any ): MultiStringProperty => ( {
		...base,
		...json,
		name: base.name,
		multiple: json.multiple ?? false,
		uniqueItems: json.uniqueItems ?? false
	} ) ),
	getExampleValue: vi.fn( () => newStringValue( 'example' ) ),
	validate: vi.fn( ( _value?: Value, _property?: PropertyDefinition ): ValueValidationError[] => [] ),
	...( options as any )
} );

describe( 'useStringValueInput', () => {
	let mockGlobalProps: ValueInputProps<MultiStringProperty>;
	let mockEmit: ReturnType<typeof vi.fn>;
	const mockPropertyTypeNameFromComposable = 'TestStringProperty';
	let mockPropertyType: PropertyType;

	beforeEach( () => {
		vi.clearAllMocks();
		mockedValidateValue.mockReturnValue( {} );

		mockPropertyType = createMockPropertyType( mockPropertyTypeNameFromComposable );

		( NeoWikiServices.getPropertyTypeRegistry().getType as MockedFunction<any> ).mockReturnValue( mockPropertyType );

		( NeoWikiServices.getComponentRegistry().getIcon as ReturnType<typeof vi.fn> ).mockReturnValue( 'testIcon' );

		mockGlobalProps = {
			modelValue: undefined as Value | undefined,
			property: createMockPropertyDefinition( {} ),
			label: 'Test Label'
		};
		mockEmit = vi.fn();
	} );

	const createComposable = ( propsToMerge: Partial<ValueInputProps<MultiStringProperty>> = {}, emit = mockEmit ): ReturnType<typeof useStringValueInput> => {
		const finalProps = { ...mockGlobalProps, ...propsToMerge };
		if ( propsToMerge.property ) {
			finalProps.property = createMockPropertyDefinition( propsToMerge.property );
		}

		return useStringValueInput(
			finalProps as ValueInputProps<MultiStringProperty>,
			emit,
			mockPropertyTypeNameFromComposable
		);
	};

	describe( 'Initialization', () => {
		it( 'initializes fieldMessages and inputMessages based on initial validation of undefined modelValue', () => {
			const testProperty = createMockPropertyDefinition( {} );
			const { fieldMessages, inputMessages } = createComposable( { modelValue: undefined, property: testProperty } );

			expect( mockedValidateValue ).not.toHaveBeenCalled();
			expect( fieldMessages.value ).toEqual( {} );
			expect( inputMessages.value ).toEqual( [] );
		} );

		it( 'initializes internalValue to undefined if modelValue is undefined', () => {
			const { getCurrentValue } = createComposable( { modelValue: undefined } );

			expect( getCurrentValue() ).toBeUndefined();
		} );

		it( 'initializes internalValue with a StringValue-like object if modelValue is a valid StringValue', () => {
			const initialValue = newStringValue( 'hello' );
			const { getCurrentValue } = createComposable( { modelValue: initialValue } );
			const currentValue = getCurrentValue();

			expect( currentValue?.type ).toBe( ValueType.String );
			expect( ( currentValue as any )?.strings ).toEqual( [ 'hello' ] );
		} );

		it( 'initializes internalValue to undefined if modelValue is StringValue with only empty strings', () => {
			const initialValue = newStringValue( '', '' );
			const { getCurrentValue } = createComposable( { modelValue: initialValue } );

			expect( getCurrentValue() ).toBeUndefined();
		} );

		it( 'initializes internalValue to undefined if modelValue is not a StringValue', () => {
			const initialValue = { type: 'NotAStringValue', someOtherProp: 'test' } as unknown as Value;
			const { getCurrentValue } = createComposable( { modelValue: initialValue } );
			expect( getCurrentValue() ).toBeUndefined();
		} );

		it( 'initializes displayValues correctly based on modelValue', () => {
			const { displayValues } = createComposable( { modelValue: newStringValue( 'test1', 'test2' ) } );

			expect( displayValues.value ).toEqual( [ 'test1', 'test2' ] );
		} );

		it( 'initializes displayValues to an empty array if modelValue is undefined', () => {
			const { displayValues } = createComposable( { modelValue: undefined } );
			expect( displayValues.value ).toEqual( [] );
		} );

		it( 'fetches startIcon using ComponentRegistry and provides it via computed ref', () => {
			const { startIcon } = createComposable();

			expect( startIcon.value ).toBe( 'testIcon' );
			expect( NeoWikiServices.getComponentRegistry().getIcon ).toHaveBeenCalledWith( mockPropertyTypeNameFromComposable );
		} );
	} );

	describe( 'onInput', () => {
		it( 'updates internalValue and emits update:modelValue for a single valid input', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: false } );
			mockedValidateValue.mockReturnValue( {} );
			const { onInput, getCurrentValue } = createComposable( {
				property: currentProperty
			} );

			onInput( 'new value' );

			const expectedValue = newStringValue( 'new value' );
			const currentValue = getCurrentValue();
			expect( currentValue?.type ).toBe( ValueType.String );
			expect( ( currentValue as any )?.strings ).toEqual( expectedValue.strings );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', currentValue );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'new value' ), mockPropertyType, currentProperty );
		} );

		it( 'updates internalValue and emits for multiple valid inputs', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: true } );
			mockedValidateValue.mockReturnValue( {} );
			const { onInput, getCurrentValue } = createComposable( {
				property: currentProperty
			} );

			onInput( [ 'val1', 'val2' ] );

			const expectedValue = newStringValue( 'val1', 'val2' );
			const currentValue = getCurrentValue();
			expect( currentValue?.type ).toBe( ValueType.String );
			expect( ( currentValue as any )?.strings ).toEqual( expectedValue.strings );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', currentValue );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'val1' ), mockPropertyType, currentProperty );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'val2' ), mockPropertyType, currentProperty );
		} );

		it( 'sets internalValue to undefined if all inputs become empty', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: false } );
			mockedValidateValue.mockReturnValue( {} );
			const { onInput, getCurrentValue } = createComposable( {
				modelValue: newStringValue( 'initial' ),
				property: currentProperty
			} );

			onInput( '' );

			expect( getCurrentValue() ).toBeUndefined();
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', undefined );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( '' ), mockPropertyType, currentProperty );
		} );

		it( 'filters out empty strings from multiple inputs before creating StringValue', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: true } );
			mockedValidateValue.mockReturnValue( {} );
			const { onInput, getCurrentValue } = createComposable( {
				property: currentProperty
			} );

			onInput( [ 'val1', '', 'val3', '' ] );

			const expectedValue = newStringValue( 'val1', 'val3' );
			const currentValue = getCurrentValue();
			expect( currentValue?.type ).toBe( ValueType.String );
			expect( ( currentValue as any )?.strings ).toEqual( expectedValue.strings );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', currentValue );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'val1' ), mockPropertyType, currentProperty );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( '' ), mockPropertyType, currentProperty );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'val3' ), mockPropertyType, currentProperty );
		} );
	} );

	describe( 'Validation (doValidateInputs and its effects)', () => {
		it( 'populates inputMessages and fieldMessages with errors from validateValue', () => {
			const validationError = { error: 'Invalid from validateValue' };
			mockedValidateValue.mockReturnValue( validationError );
			const testProperty = createMockPropertyDefinition( { multiple: false } );
			const { onInput, inputMessages, fieldMessages } = createComposable( {
				property: testProperty
			} );

			onInput( 'trigger validation' );

			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'trigger validation' ), mockPropertyType, testProperty );
			expect( inputMessages.value ).toEqual( [ validationError ] );
			expect( fieldMessages.value ).toEqual( validationError );
		} );

		it( 'handles uniqueItems validation for multiple inputs', () => {
			const testProperty = createMockPropertyDefinition( { multiple: true, uniqueItems: true } );
			mockedValidateValue.mockReturnValue( {} );
			const { onInput, inputMessages } = createComposable( {
				property: testProperty
			} );

			onInput( [ 'duplicate', 'unique', 'duplicate' ] );

			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'duplicate' ), mockPropertyType, testProperty );
			expect( mockedValidateValue ).toHaveBeenCalledWith( newStringValue( 'unique' ), mockPropertyType, testProperty );
			expect( mockedValidateValue ).toHaveBeenCalledTimes( 2 );
			expect( inputMessages.value[ 0 ] ).toEqual( {} );
			expect( inputMessages.value[ 1 ] ).toEqual( {} );
			expect( inputMessages.value[ 2 ] ).toEqual( { error: 'neowiki-field-unique' } );
		} );

		it( 'handles property type configuration error if propertyType is undefined', () => {
			( NeoWikiServices.getPropertyTypeRegistry().getType as MockedFunction<any> ).mockReturnValue( undefined );
			const { onInput, inputMessages, fieldMessages } = createComposable( {
				property: createMockPropertyDefinition( { multiple: false } )
			} );

			onInput( 'any value' );

			const expectedError = { error: 'Property type configuration error.' };
			expect( mockedValidateValue ).not.toHaveBeenCalled();
			expect( inputMessages.value ).toEqual( [ expectedError ] );
			expect( fieldMessages.value ).toEqual( expectedError );
		} );

		it( 'initial validation is run on setup with modelValue, calling validateValue', () => {
			const initialValue = newStringValue( 'initial value' );
			const validationError = { error: 'Initial validation error' };
			mockedValidateValue.mockReturnValue( validationError );
			const currentProperty = createMockPropertyDefinition( {} );

			const { inputMessages, fieldMessages } = createComposable( { modelValue: initialValue, property: currentProperty } );

			expect( mockedValidateValue ).toHaveBeenCalledWith( initialValue, mockPropertyType, currentProperty );
			expect( inputMessages.value ).toEqual( [ validationError ] );
			expect( fieldMessages.value ).toEqual( validationError );
		} );
	} );

	describe( 'Watchers', () => {
		it( 'reacts to props.modelValue changes, re-initializes and re-validates', async () => {
			const initialModelValue = newStringValue( 'initial' );
			let currentPropertyDef = createMockPropertyDefinition( { multiple: false } );
			currentPropertyDef = markRaw( currentPropertyDef );

			const reactivePropsData = reactive<ValueInputProps<MultiStringProperty>>( {
				modelValue: initialModelValue,
				label: 'Test Label',
				property: currentPropertyDef
			} );

			mockedValidateValue.mockReturnValueOnce( {} );
			const composableInstance = useStringValueInput( reactivePropsData as ValueInputProps<MultiStringProperty>, mockEmit, mockPropertyTypeNameFromComposable );
			expect( mockedValidateValue ).toHaveBeenCalledWith( initialModelValue, mockPropertyType, currentPropertyDef );

			const newModelValue = newStringValue( 'changed' );
			const validationError = { error: 'Error on changed' };
			mockedValidateValue.mockReset().mockReturnValue( validationError );

			reactivePropsData.modelValue = newModelValue;
			await nextTick();

			expect( mockedValidateValue ).toHaveBeenCalledWith( newModelValue, mockPropertyType, currentPropertyDef );
			const currentVal = composableInstance.getCurrentValue();
			expect( currentVal?.type ).toBe( ValueType.String );
			expect( ( currentVal as any )?.strings ).toEqual( [ 'changed' ] );
			expect( composableInstance.inputMessages.value ).toEqual( [ validationError ] );
			expect( composableInstance.fieldMessages.value ).toEqual( validationError );
		} );

		it( 'reacts to props.property changes and re-validates', async () => {
			const initialModelValue = newStringValue( 'test' );
			let initialPropertyDef = createMockPropertyDefinition( { required: false, multiple: false } );
			initialPropertyDef = markRaw( initialPropertyDef );

			const reactivePropsData = reactive<ValueInputProps<MultiStringProperty>>( {
				modelValue: initialModelValue,
				label: 'Test Label',
				property: initialPropertyDef
			} );

			mockedValidateValue.mockReturnValueOnce( {} );
			const { inputMessages, fieldMessages: composableFieldMessages, displayValues } = useStringValueInput( reactivePropsData as ValueInputProps<MultiStringProperty>, mockEmit, mockPropertyTypeNameFromComposable );
			expect( mockedValidateValue ).toHaveBeenCalledWith( initialModelValue, mockPropertyType, initialPropertyDef );

			let newTestProperty = createMockPropertyDefinition( { required: true, multiple: false } );
			newTestProperty = markRaw( newTestProperty );
			const validationErrorOnPropChange = { error: 'Error on prop change' };
			mockedValidateValue.mockReset().mockReturnValue( validationErrorOnPropChange );

			reactivePropsData.property = newTestProperty;
			await nextTick();

			const currentValueForValidation = displayValues.value.length > 0 ? newStringValue( ...displayValues.value ) : newStringValue( '' );
			expect( mockedValidateValue ).toHaveBeenCalledWith( currentValueForValidation, mockPropertyType, newTestProperty );
			expect( inputMessages.value ).toEqual( [ validationErrorOnPropChange ] );
			expect( composableFieldMessages.value ).toEqual( validationErrorOnPropChange );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns the current internalValue', () => {
			const { getCurrentValue, onInput } = createComposable();

			expect( getCurrentValue() ).toBeUndefined();

			onInput( 'new val' );

			const currentVal = getCurrentValue();
			expect( currentVal?.type ).toBe( ValueType.String );
			expect( ( currentVal as any )?.strings ).toEqual( [ 'new val' ] );

			onInput( '' );
			expect( getCurrentValue() ).toBeUndefined();
		} );
	} );
} );
