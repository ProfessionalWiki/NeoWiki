import { markRaw, ref, Ref } from 'vue';
import { describe, it, expect, vi, beforeEach, type MockedFunction } from 'vitest';
import { nextTick } from 'vue';

vi.mock( '@/composables/useValueValidation.ts', () => ( {
	liveValidationMessages: vi.fn(),
} ) );

vi.mock( '@/NeoWikiServices.ts', () => ( {
	NeoWikiServices: {
		getComponentRegistry: vi.fn().mockReturnValue( {
			getIcon: vi.fn().mockReturnValue( undefined ),
		} ),
	},
} ) );

import { liveValidationMessages } from '@/composables/useValueValidation.ts';
import { useStringValueInput } from '@/composables/useStringValueInput.ts';
import { Value, newStringValue, ValueType } from '@/domain/Value.ts';
import { MultiStringProperty, PropertyName, PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { PropertyType, ValueValidationError } from '@/domain/PropertyType.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const mockedLiveValidationMessages = liveValidationMessages as MockedFunction<typeof liveValidationMessages>;

vi.stubGlobal( 'mw', {
	message: vi.fn( ( key: string ) => ( {
		text: () => key,
		parse: () => key,
	} ) ),
} );

const createMockPropertyDefinition = ( options: Partial<MultiStringProperty> = {} ): MultiStringProperty => {
	const defaults: Omit<MultiStringProperty, 'name'> = {
		description: '',
		type: 'TestStringProperty',
		required: false,
		multiple: false,
		uniqueItems: false,
	};

	const finalName = options.name instanceof PropertyName ?
		options.name :
		new PropertyName( ( options.name as any )?.toString() ?? 'testProp' );

	return {
		...defaults,
		...options,
		name: finalName,
	} as MultiStringProperty;
};

const createMockPropertyType = ( typeName: string, options: Partial<PropertyType> = {} ): PropertyType => ( {
	getTypeName: vi.fn().mockReturnValue( typeName ),
	getValueType: vi.fn().mockReturnValue( ValueType.String ),
	createPropertyDefinitionFromJson: vi.fn(),
	getExampleValue: vi.fn(),
	validate: vi.fn( ( _value?: Value, _property?: PropertyDefinition ): ValueValidationError[] => [] ),
	...( options as any ),
} );

describe( 'useStringValueInput', () => {
	let mockModelValue: Ref<Value | undefined>;
	let mockProperty: Ref<MultiStringProperty>;
	let mockEmit: ReturnType<typeof vi.fn>;
	const mockPropertyTypeNameFromComposable = 'TestStringProperty';
	let mockPropertyType: PropertyType;

	beforeEach( () => {
		vi.clearAllMocks();
		mockedLiveValidationMessages.mockReturnValue( {} );

		mockPropertyType = createMockPropertyType( mockPropertyTypeNameFromComposable );

		( NeoWikiServices.getComponentRegistry().getIcon as ReturnType<typeof vi.fn> ).mockReturnValue( 'testIcon' );

		mockModelValue = ref( undefined );
		mockProperty = ref( createMockPropertyDefinition( {} ) ) as Ref<MultiStringProperty>;
		mockEmit = vi.fn();
	} );

	const createComposable = ( options: { modelValue?: Value | undefined; property?: Partial<MultiStringProperty> } = {}, emit = mockEmit ): ReturnType<typeof useStringValueInput> => {
		const modelValueRef = ref( options.modelValue ?? mockModelValue.value );
		const propertyRef = ref( createMockPropertyDefinition( options.property ?? mockProperty.value ) ) as Ref<MultiStringProperty>;

		return useStringValueInput(
			modelValueRef,
			propertyRef,
			emit,
			mockPropertyType,
		);
	};

	describe( 'Initialization', () => {
		it( 'initializes fieldMessages and inputMessages based on initial validation of undefined modelValue', () => {
			const testProperty = createMockPropertyDefinition( {} );

			const { fieldMessages, inputMessages } = createComposable( { modelValue: undefined, property: testProperty } );

			expect( mockedLiveValidationMessages ).not.toHaveBeenCalled();
			expect( fieldMessages.value ).toEqual( {} );
			expect( inputMessages.value ).toEqual( [] );
		} );

		it( 'initializes internalValue to undefined if modelValue is undefined', () => {
			const { getCurrentValue } = createComposable( { modelValue: undefined } );

			expect( getCurrentValue() ).toBeUndefined();
		} );

		it( 'initializes internalValue with a StringValue-like object if modelValue is a valid StringValue', () => {
			const initialValue = newStringValue( 'hello' );
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { getCurrentValue } = createComposable( { modelValue: initialValue } );
			const currentValue = getCurrentValue();

			expect( currentValue ).toEqual( newStringValue( 'hello' ) );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( initialValue, mockPropertyType, expect.any( Object ) );
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
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { displayValues } = createComposable( { modelValue: newStringValue( 'test1', 'test2' ) } );

			expect( displayValues.value ).toEqual( [ 'test1', 'test2' ] );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledTimes( 2 );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'test1' ), mockPropertyType, expect.any( Object ) );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'test2' ), mockPropertyType, expect.any( Object ) );
		} );

		it( 'initializes displayValues to an empty array if modelValue is undefined', () => {
			const { displayValues } = createComposable( { modelValue: undefined } );

			expect( displayValues.value ).toEqual( [] );
			expect( mockedLiveValidationMessages ).not.toHaveBeenCalled();
		} );

		it( 'fetches startIcon using ComponentRegistry and provides it via computed ref', () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { startIcon } = createComposable();

			expect( startIcon ).toBe( 'testIcon' );
			expect( NeoWikiServices.getComponentRegistry().getIcon ).toHaveBeenCalledWith( mockPropertyTypeNameFromComposable );
		} );
	} );

	describe( 'onInput', () => {
		it( 'updates internalValue and emits update:modelValue for a single valid input', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: false } );
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput, getCurrentValue, inputMessages, fieldMessages } = createComposable( {
				property: currentProperty,
			} );

			onInput( 'new value' );

			const currentValue = getCurrentValue();
			expect( currentValue ).toEqual( newStringValue( 'new value' ) );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', currentValue );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'new value' ), mockPropertyType, currentProperty );
			expect( inputMessages.value ).toEqual( [ {} ] );
			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'updates internalValue and emits for multiple valid inputs', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: true } );
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput, getCurrentValue, inputMessages, fieldMessages } = createComposable( {
				property: currentProperty,
			} );

			onInput( [ 'val1', 'val2' ] );

			const currentValue = getCurrentValue();
			expect( currentValue ).toEqual( newStringValue( 'val1', 'val2' ) );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', currentValue );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'val1' ), mockPropertyType, currentProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'val2' ), mockPropertyType, currentProperty );
			expect( inputMessages.value ).toEqual( [ {}, {} ] );
			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'sets internalValue to undefined if all inputs become empty', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: false } );
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput, getCurrentValue, inputMessages, fieldMessages } = createComposable( {
				modelValue: newStringValue( 'initial' ),
				property: currentProperty,
			} );
			mockedLiveValidationMessages.mockClear();

			onInput( '' );

			expect( getCurrentValue() ).toBeUndefined();
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', undefined );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( '' ), mockPropertyType, currentProperty );
			expect( inputMessages.value ).toEqual( [ {} ] );
			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'filters out empty strings from multiple inputs before creating StringValue for internal state, but validates all', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: true } );
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput, getCurrentValue, inputMessages, fieldMessages } = createComposable( {
				property: currentProperty,
			} );

			onInput( [ 'val1', '', 'val3', '' ] );

			const currentValue = getCurrentValue();
			expect( currentValue ).toEqual( newStringValue( 'val1', 'val3' ) );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', currentValue );

			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'val1' ), mockPropertyType, currentProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( '' ), mockPropertyType, currentProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'val3' ), mockPropertyType, currentProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledTimes( 4 );

			expect( inputMessages.value ).toEqual( [ {}, {}, {}, {} ] );
			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'sets internalValue to undefined if only empty strings are provided in multiple inputs', () => {
			const currentProperty = createMockPropertyDefinition( { multiple: true } );
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput, getCurrentValue, inputMessages, fieldMessages } = createComposable( {
				modelValue: newStringValue( 'initial' ),
				property: currentProperty,
			} );
			mockedLiveValidationMessages.mockClear();

			onInput( [ '', '' ] );

			expect( getCurrentValue() ).toBeUndefined();
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', undefined );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( '' ), mockPropertyType, currentProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledTimes( 2 );
			expect( inputMessages.value ).toEqual( [ {}, {} ] );
			expect( fieldMessages.value ).toEqual( {} );
		} );
	} );

	describe( 'Validation (doValidateInputs and its effects)', () => {
		it( 'emits invalid input through to the backend (backend is the authoritative validator)', () => {
			const validationError = { error: 'Invalid URL' };
			mockedLiveValidationMessages.mockReturnValue( validationError );
			const testProperty = createMockPropertyDefinition( { multiple: false } );
			const { onInput, displayValues, getCurrentValue, fieldMessages } = createComposable( {
				property: testProperty,
			} );

			onInput( 'h' );

			expect( displayValues.value ).toEqual( [ 'h' ] );
			expect( getCurrentValue() ).toEqual( newStringValue( 'h' ) );
			expect( fieldMessages.value ).toEqual( validationError );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', newStringValue( 'h' ) );
		} );

		it( 'emits invalid replacement input so the backend sees it (no silent drop)', () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const testProperty = createMockPropertyDefinition( { multiple: false } );
			const { onInput, displayValues, getCurrentValue, fieldMessages } = createComposable( {
				modelValue: newStringValue( 'https://example.com' ),
				property: testProperty,
			} );

			const validationError = { error: 'Invalid URL' };
			mockedLiveValidationMessages.mockReturnValue( validationError );

			onInput( 'h' );

			expect( displayValues.value ).toEqual( [ 'h' ] );
			expect( getCurrentValue() ).toEqual( newStringValue( 'h' ) );
			expect( fieldMessages.value ).toEqual( validationError );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', newStringValue( 'h' ) );
		} );

		it( 'populates inputMessages and fieldMessages with errors from liveValidationMessages', () => {
			const validationError = { error: 'Invalid from liveValidationMessages' };
			mockedLiveValidationMessages.mockReturnValue( validationError );
			const testProperty = createMockPropertyDefinition( { multiple: false } );
			const { onInput, inputMessages, fieldMessages } = createComposable( {
				property: testProperty,
			} );
			mockedLiveValidationMessages.mockClear();

			onInput( 'trigger validation' );

			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'trigger validation' ), mockPropertyType, testProperty );
			expect( inputMessages.value ).toEqual( [ validationError ] );
			expect( fieldMessages.value ).toEqual( validationError );
		} );

		it( 'populates inputMessages but not fieldMessages for multiple inputs with errors', () => {
			const validationError1 = { error: 'Error 1' };
			const validationError3 = { warning: 'Warning 3' };
			mockedLiveValidationMessages
				.mockReturnValueOnce( validationError1 )
				.mockReturnValueOnce( {} )
				.mockReturnValueOnce( validationError3 );
			const testProperty = createMockPropertyDefinition( { multiple: true } );
			const { onInput, inputMessages, fieldMessages } = createComposable( {
				property: testProperty,
			} );
			mockedLiveValidationMessages.mockClear();

			onInput( [ 'input1', 'input2', 'input3' ] );

			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'input1' ), mockPropertyType, testProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'input2' ), mockPropertyType, testProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'input3' ), mockPropertyType, testProperty );
			expect( inputMessages.value ).toEqual( [ validationError1, {}, validationError3 ] );
			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'handles uniqueItems validation for multiple inputs', () => {
			const testProperty = createMockPropertyDefinition( { multiple: true, uniqueItems: true } );
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput, inputMessages, fieldMessages } = createComposable( {
				property: testProperty,
			} );
			mockedLiveValidationMessages.mockClear();

			onInput( [ 'duplicate', 'unique', 'duplicate' ] );

			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'duplicate' ), mockPropertyType, testProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newStringValue( 'unique' ), mockPropertyType, testProperty );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledTimes( 2 );

			expect( inputMessages.value[ 0 ] ).toEqual( {} );
			expect( inputMessages.value[ 1 ] ).toEqual( {} );
			expect( inputMessages.value[ 2 ] ).toEqual( { error: 'neowiki-field-unique' } );
			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'initial validation is run on setup with modelValue, calling liveValidationMessages', () => {
			const initialValue = newStringValue( 'initial value' );
			const validationError = { error: 'Initial validation error' };
			mockedLiveValidationMessages.mockReturnValue( validationError );
			const currentProperty = createMockPropertyDefinition( {} );

			const { inputMessages, fieldMessages } = createComposable( { modelValue: initialValue, property: currentProperty } );

			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( initialValue, mockPropertyType, currentProperty );
			expect( inputMessages.value ).toEqual( [ validationError ] );
			expect( fieldMessages.value ).toEqual( validationError );
		} );
	} );

	describe( 'Watchers', () => {
		it( 'reacts to props.modelValue changes, re-initializes and re-validates', async () => {
			const initialModelValue = newStringValue( 'initial' );
			const modelValueRef = ref<Value | undefined>( initialModelValue );
			const propertyRef = ref( createMockPropertyDefinition( { multiple: false } ) ) as Ref<MultiStringProperty>;

			mockedLiveValidationMessages.mockReturnValueOnce( {} );
			const composableInstance = useStringValueInput( modelValueRef, propertyRef, mockEmit, mockPropertyType );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( initialModelValue, mockPropertyType, propertyRef.value );
			expect( composableInstance.inputMessages.value ).toEqual( [ {} ] );
			expect( composableInstance.fieldMessages.value ).toEqual( {} );

			const newModelValue = newStringValue( 'changed' );
			const validationError = { error: 'Error on changed' };
			mockedLiveValidationMessages.mockReset().mockReturnValue( validationError );

			modelValueRef.value = newModelValue;
			await nextTick();

			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( newModelValue, mockPropertyType, propertyRef.value );
			expect( composableInstance.getCurrentValue() ).toEqual( newStringValue( 'changed' ) );
			expect( composableInstance.inputMessages.value ).toEqual( [ validationError ] );
			expect( composableInstance.fieldMessages.value ).toEqual( validationError );
		} );

		it( 'reacts to props.property changes and re-validates', async () => {
			const initialModelValue = newStringValue( 'test' );
			const modelValueRef = ref<Value | undefined>( initialModelValue );
			const propertyRef = ref( createMockPropertyDefinition( { required: false, multiple: false } ) ) as Ref<MultiStringProperty>;

			mockedLiveValidationMessages.mockReturnValueOnce( {} );
			const { inputMessages, fieldMessages: composableFieldMessages, displayValues } = useStringValueInput( modelValueRef, propertyRef, mockEmit, mockPropertyType );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( initialModelValue, mockPropertyType, propertyRef.value );
			expect( inputMessages.value ).toEqual( [ {} ] );
			expect( composableFieldMessages.value ).toEqual( {} );

			let newTestProperty = createMockPropertyDefinition( { required: true, multiple: false } );
			newTestProperty = markRaw( newTestProperty );
			const validationErrorOnPropChange = { error: 'Error on prop change' };
			mockedLiveValidationMessages.mockReset().mockReturnValue( validationErrorOnPropChange );

			propertyRef.value = newTestProperty;
			await nextTick();

			const currentValueForValidation = displayValues.value.length > 0 ? newStringValue( ...displayValues.value ) : newStringValue( '' );
			expect( mockedLiveValidationMessages ).toHaveBeenCalledWith( currentValueForValidation, mockPropertyType, newTestProperty );
			expect( inputMessages.value ).toEqual( [ validationErrorOnPropChange ] );
			expect( composableFieldMessages.value ).toEqual( validationErrorOnPropChange );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns the current internalValue', () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { getCurrentValue, onInput } = createComposable();

			expect( getCurrentValue() ).toBeUndefined();

			onInput( 'new value' );

			expect( getCurrentValue() ).toEqual( newStringValue( 'new value' ) );

			onInput( '' );

			expect( getCurrentValue() ).toBeUndefined();
		} );
	} );

	describe( 'serverViolations', () => {
		const createComposableWithViolations = (
			violations: any[],
			property: Partial<MultiStringProperty> = {},
			emit = mockEmit,
		): ReturnType<typeof useStringValueInput> & {
			modelValueRef: Ref<Value | undefined>;
			propertyRef: Ref<MultiStringProperty>;
			serverViolationsRef: Ref<any[]>;
		} => {
			const modelValueRef = ref<Value | undefined>( undefined );
			const propertyRef = ref( createMockPropertyDefinition( property ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref<any[]>( violations );
			mockedLiveValidationMessages.mockReturnValue( {} );

			const result = useStringValueInput(
				modelValueRef,
				propertyRef,
				emit,
				mockPropertyType,
				serverViolationsRef,
			);
			return { ...result, modelValueRef, propertyRef, serverViolationsRef };
		};

		it( 'shows server violation in fieldMessages for a single-value property when no live error exists', async () => {
			const { fieldMessages } = createComposableWithViolations(
				[ { propertyName: 'testProp', code: 'required', args: [], valuePartIndex: null } ],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			// Trigger initial watch (immediate)
			await nextTick();

			expect( fieldMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );

		it( 'does not show server violation for a different property name', async () => {
			const { fieldMessages } = createComposableWithViolations(
				[ { propertyName: 'OtherProp', code: 'required', args: [], valuePartIndex: null } ],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			await nextTick();

			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'live error takes precedence over server violation for single-value property', async () => {
			const liveError = { error: 'live-validation-error' };
			mockedLiveValidationMessages.mockReturnValue( liveError );
			const modelValueRef = ref<Value | undefined>( newStringValue( 'bad' ) );
			const propertyRef = ref( createMockPropertyDefinition( {
				name: new PropertyName( 'testProp' ),
				multiple: false,
			} ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref( [
				{ propertyName: 'testProp', code: 'required', args: [], valuePartIndex: null },
			] );

			const { fieldMessages } = useStringValueInput(
				modelValueRef,
				propertyRef,
				mockEmit,
				mockPropertyType,
				serverViolationsRef,
			);
			await nextTick();

			expect( fieldMessages.value ).toEqual( liveError );
		} );

		it( 'shows server violation in inputMessages for a multi-value property at the matching index', async () => {
			const { inputMessages, onInput } = createComposableWithViolations(
				[ { propertyName: 'testProp', code: 'invalid-url', args: [], valuePartIndex: 1 } ],
				{ name: new PropertyName( 'testProp' ), multiple: true },
			);

			onInput( [ 'https://ok.example', 'bad' ] );
			await nextTick();

			expect( inputMessages.value[ 0 ] ).toEqual( {} );
			expect( inputMessages.value[ 1 ] ).toEqual( { error: 'neowiki-field-invalid-url' } );
		} );

		it( 'emits clear-server-violation when user edits a single-value field that had a server violation', async () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput } = createComposableWithViolations(
				[ { propertyName: 'testProp', code: 'required', args: [], valuePartIndex: null } ],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			onInput( 'some text' );

			expect( mockEmit ).toHaveBeenCalledWith(
				'clear-server-violation',
				{ propertyName: 'testProp', valuePartIndex: null },
			);
		} );

		it( 'does not emit clear-server-violation when no server violation exists for this property', async () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const { onInput } = createComposableWithViolations(
				[],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			onInput( 'some text' );

			expect( mockEmit ).not.toHaveBeenCalledWith( 'clear-server-violation', expect.anything() );
		} );

		it( 'emits clear-server-violation only for the changed index in a multi-value field', async () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const modelValueRef = ref<Value | undefined>( newStringValue( 'https://ok.example', 'bad' ) );
			const propertyRef = ref( createMockPropertyDefinition( {
				name: new PropertyName( 'testProp' ),
				multiple: true,
			} ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref( [
				{ propertyName: 'testProp', code: 'invalid-url', args: [], valuePartIndex: 1 },
			] );

			const { onInput } = useStringValueInput(
				modelValueRef,
				propertyRef,
				mockEmit,
				mockPropertyType,
				serverViolationsRef,
			);
			await nextTick();
			mockEmit.mockClear();

			// Edit index 1 (the one with a violation)
			onInput( [ 'https://ok.example', 'fixed' ] );

			expect( mockEmit ).toHaveBeenCalledWith(
				'clear-server-violation',
				{ propertyName: 'testProp', valuePartIndex: 1 },
			);
		} );

		it( 'does not emit clear-server-violation when editing index 0 and violation is only at index 1', async () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const modelValueRef = ref<Value | undefined>( newStringValue( 'first', 'bad' ) );
			const propertyRef = ref( createMockPropertyDefinition( {
				name: new PropertyName( 'testProp' ),
				multiple: true,
			} ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref( [
				{ propertyName: 'testProp', code: 'invalid-url', args: [], valuePartIndex: 1 },
			] );

			const { onInput } = useStringValueInput(
				modelValueRef,
				propertyRef,
				mockEmit,
				mockPropertyType,
				serverViolationsRef,
			);
			await nextTick();
			mockEmit.mockClear();

			// Edit index 0 (no violation there)
			onInput( [ 'changed', 'bad' ] );

			expect( mockEmit ).not.toHaveBeenCalledWith( 'clear-server-violation', expect.anything() );
		} );

		it( 'recomputes messages when serverViolations ref changes', async () => {
			mockedLiveValidationMessages.mockReturnValue( {} );
			const modelValueRef = ref<Value | undefined>( undefined );
			const propertyRef = ref( createMockPropertyDefinition( {
				name: new PropertyName( 'testProp' ),
				multiple: false,
			} ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref<any[]>( [] );

			const { fieldMessages } = useStringValueInput(
				modelValueRef,
				propertyRef,
				mockEmit,
				mockPropertyType,
				serverViolationsRef,
			);
			await nextTick();
			expect( fieldMessages.value ).toEqual( {} );

			serverViolationsRef.value = [
				{ propertyName: 'testProp', code: 'required', args: [], valuePartIndex: null },
			];
			await nextTick();

			expect( fieldMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );
	} );
} );
