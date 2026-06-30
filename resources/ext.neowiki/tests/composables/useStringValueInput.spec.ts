import { ref, Ref, nextTick } from 'vue';
import { describe, it, expect, vi, beforeEach, type Mock } from 'vitest';

vi.mock( '@/NeoWikiServices.ts', () => ( {
	NeoWikiServices: {
		getComponentRegistry: vi.fn().mockReturnValue( {
			getIcon: vi.fn().mockReturnValue( undefined ),
		} ),
	},
} ) );

import { useStringValueInput } from '@/composables/useStringValueInput.ts';
import { Value, newStringValue, ValueType } from '@/domain/Value.ts';
import { MultiStringProperty, PropertyName } from '@/domain/PropertyDefinition.ts';
import { PropertyType } from '@/domain/PropertyType.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

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

const createMockPropertyType = ( typeName: string ): PropertyType => ( {
	getTypeName: vi.fn().mockReturnValue( typeName ),
	getValueType: vi.fn().mockReturnValue( ValueType.String ),
	createPropertyDefinitionFromJson: vi.fn(),
	getExampleValue: vi.fn(),
} ) as unknown as PropertyType;

type EmitMock = Parameters<typeof useStringValueInput>[ 2 ] & Mock;

const PROPERTY_TYPE_NAME = 'TestStringProperty';

describe( 'useStringValueInput', () => {
	let mockEmit: EmitMock;
	let mockPropertyType: PropertyType;

	beforeEach( () => {
		vi.clearAllMocks();
		mockPropertyType = createMockPropertyType( PROPERTY_TYPE_NAME );
		( NeoWikiServices.getComponentRegistry().getIcon as ReturnType<typeof vi.fn> ).mockReturnValue( 'testIcon' );
		mockEmit = vi.fn() as unknown as EmitMock;
	} );

	const createComposable = (
		options: { modelValue?: Value | undefined; property?: Partial<MultiStringProperty> } = {},
		emit = mockEmit,
	): ReturnType<typeof useStringValueInput> => {
		const modelValueRef = ref( options.modelValue );
		const propertyRef = ref( createMockPropertyDefinition( options.property ) ) as Ref<MultiStringProperty>;

		return useStringValueInput( modelValueRef, propertyRef, emit, mockPropertyType );
	};

	describe( 'Initialization', () => {
		it( 'initializes empty messages for an undefined modelValue', () => {
			const { fieldMessages, inputMessages } = createComposable( { modelValue: undefined } );

			expect( fieldMessages.value ).toEqual( {} );
			expect( inputMessages.value ).toEqual( [] );
		} );

		it( 'initializes internalValue to undefined if modelValue is undefined', () => {
			const { getCurrentValue } = createComposable( { modelValue: undefined } );

			expect( getCurrentValue() ).toBeUndefined();
		} );

		it( 'initializes internalValue with the StringValue if modelValue is a valid StringValue', () => {
			const { getCurrentValue } = createComposable( { modelValue: newStringValue( 'hello' ) } );

			expect( getCurrentValue() ).toEqual( newStringValue( 'hello' ) );
		} );

		it( 'initializes internalValue to undefined if modelValue is a StringValue with only empty strings', () => {
			const { getCurrentValue } = createComposable( { modelValue: newStringValue( '', '' ) } );

			expect( getCurrentValue() ).toBeUndefined();
		} );

		it( 'initializes internalValue to undefined if modelValue is not a StringValue', () => {
			const notAString = { type: 'NotAStringValue', someOtherProp: 'test' } as unknown as Value;
			const { getCurrentValue } = createComposable( { modelValue: notAString } );

			expect( getCurrentValue() ).toBeUndefined();
		} );

		it( 'initializes displayValues from the modelValue parts', () => {
			const { displayValues } = createComposable( { modelValue: newStringValue( 'test1', 'test2' ) } );

			expect( displayValues.value ).toEqual( [ 'test1', 'test2' ] );
		} );

		it( 'initializes displayValues to an empty array if modelValue is undefined', () => {
			const { displayValues } = createComposable( { modelValue: undefined } );

			expect( displayValues.value ).toEqual( [] );
		} );

		it( 'fetches startIcon from the ComponentRegistry by type name', () => {
			const { startIcon } = createComposable();

			expect( startIcon ).toBe( 'testIcon' );
			expect( NeoWikiServices.getComponentRegistry().getIcon ).toHaveBeenCalledWith( PROPERTY_TYPE_NAME );
		} );
	} );

	describe( 'onInput', () => {
		it( 'updates internalValue and emits update:modelValue for a single input', () => {
			const { onInput, getCurrentValue, inputMessages, fieldMessages } = createComposable( {
				property: { multiple: false },
			} );

			onInput( 'new value' );

			expect( getCurrentValue() ).toEqual( newStringValue( 'new value' ) );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', newStringValue( 'new value' ) );
			expect( inputMessages.value ).toEqual( [ {} ] );
			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'updates internalValue and emits for multiple inputs', () => {
			const { onInput, getCurrentValue, inputMessages } = createComposable( { property: { multiple: true } } );

			onInput( [ 'val1', 'val2' ] );

			expect( getCurrentValue() ).toEqual( newStringValue( 'val1', 'val2' ) );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', newStringValue( 'val1', 'val2' ) );
			expect( inputMessages.value ).toEqual( [ {}, {} ] );
		} );

		it( 'sets internalValue to undefined when all inputs become empty', () => {
			const { onInput, getCurrentValue } = createComposable( {
				modelValue: newStringValue( 'initial' ),
				property: { multiple: false },
			} );

			onInput( '' );

			expect( getCurrentValue() ).toBeUndefined();
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', undefined );
		} );

		it( 'filters out empty strings from multiple inputs when building the StringValue', () => {
			const { onInput, getCurrentValue } = createComposable( { property: { multiple: true } } );

			onInput( [ 'val1', '', 'val3', '' ] );

			expect( getCurrentValue() ).toEqual( newStringValue( 'val1', 'val3' ) );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', newStringValue( 'val1', 'val3' ) );
		} );

		it( 'sets internalValue to undefined if only empty strings are provided', () => {
			const { onInput, getCurrentValue } = createComposable( {
				modelValue: newStringValue( 'initial' ),
				property: { multiple: true },
			} );

			onInput( [ '', '' ] );

			expect( getCurrentValue() ).toBeUndefined();
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', undefined );
		} );

		it( 'emits a value the backend would reject, since the backend is the authoritative validator', () => {
			const { onInput, displayValues, getCurrentValue, fieldMessages } = createComposable( {
				property: { multiple: false },
			} );

			onInput( 'h' );

			expect( displayValues.value ).toEqual( [ 'h' ] );
			expect( getCurrentValue() ).toEqual( newStringValue( 'h' ) );
			expect( fieldMessages.value ).toEqual( {} );
			expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', newStringValue( 'h' ) );
		} );
	} );

	describe( 'Watchers', () => {
		it( 're-initializes the value when props.modelValue changes', async () => {
			const modelValueRef = ref<Value | undefined>( newStringValue( 'initial' ) );
			const propertyRef = ref( createMockPropertyDefinition( { multiple: false } ) ) as Ref<MultiStringProperty>;
			const composable = useStringValueInput( modelValueRef, propertyRef, mockEmit, mockPropertyType );

			modelValueRef.value = newStringValue( 'changed' );
			await nextTick();

			expect( composable.getCurrentValue() ).toEqual( newStringValue( 'changed' ) );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns the current internalValue', () => {
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
			violations: SubjectViolation[],
			property: Partial<MultiStringProperty> = {},
			emit = mockEmit,
		): ReturnType<typeof useStringValueInput> & { serverViolationsRef: Ref<SubjectViolation[]> } => {
			const modelValueRef = ref<Value | undefined>( undefined );
			const propertyRef = ref( createMockPropertyDefinition( property ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref<SubjectViolation[]>( violations );

			const result = useStringValueInput( modelValueRef, propertyRef, emit, mockPropertyType, serverViolationsRef );
			return { ...result, serverViolationsRef };
		};

		function violation( overrides: Partial<SubjectViolation> = {} ): SubjectViolation {
			return { propertyName: 'testProp', code: 'required', args: [], valuePartIndex: null, ...overrides };
		}

		it( 'shows a field-level server violation in fieldMessages for a single-value property', async () => {
			const { fieldMessages } = createComposableWithViolations(
				[ violation() ],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			await nextTick();

			expect( fieldMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );

		it( 'surfaces a per-index (index 0) server violation in fieldMessages for a single-value property', async () => {
			const modelValueRef = ref<Value | undefined>( newStringValue( 'short' ) );
			const propertyRef = ref( createMockPropertyDefinition( {
				name: new PropertyName( 'testProp' ),
				multiple: false,
			} ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref<SubjectViolation[]>( [
				violation( { code: 'min-length', args: [ '10' ], valuePartIndex: 0 } ),
			] );

			const { fieldMessages } = useStringValueInput(
				modelValueRef, propertyRef, mockEmit, mockPropertyType, serverViolationsRef,
			);
			await nextTick();

			expect( fieldMessages.value ).toEqual( { error: 'neowiki-field-min-length' } );
		} );

		it( 'does not show a server violation belonging to a different property', async () => {
			const { fieldMessages } = createComposableWithViolations(
				[ violation( { propertyName: 'OtherProp' } ) ],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			await nextTick();

			expect( fieldMessages.value ).toEqual( {} );
		} );

		it( 'shows a per-index server violation in inputMessages at the matching index', async () => {
			const { inputMessages, onInput } = createComposableWithViolations(
				[ violation( { code: 'invalid-url', valuePartIndex: 1 } ) ],
				{ name: new PropertyName( 'testProp' ), multiple: true },
			);

			onInput( [ 'https://ok.example', 'bad' ] );
			await nextTick();

			expect( inputMessages.value[ 0 ] ).toEqual( {} );
			expect( inputMessages.value[ 1 ] ).toEqual( { error: 'neowiki-field-invalid-url' } );
		} );

		it( 'emits clear-server-violation when the user edits a single-value field that had a violation', () => {
			const { onInput } = createComposableWithViolations(
				[ violation() ],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			onInput( 'some text' );

			expect( mockEmit ).toHaveBeenCalledWith(
				'clear-server-violation',
				{ propertyName: 'testProp', valuePartIndex: null },
			);
		} );

		it( 'does not emit clear-server-violation when no violation exists for the property', () => {
			const { onInput } = createComposableWithViolations(
				[],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);

			onInput( 'some text' );

			expect( mockEmit ).not.toHaveBeenCalledWith( 'clear-server-violation', expect.anything() );
		} );

		it( 'emits clear-server-violation only for the changed index in a multi-value field', async () => {
			const modelValueRef = ref<Value | undefined>( newStringValue( 'https://ok.example', 'bad' ) );
			const propertyRef = ref( createMockPropertyDefinition( {
				name: new PropertyName( 'testProp' ),
				multiple: true,
			} ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref<SubjectViolation[]>( [ violation( { code: 'invalid-url', valuePartIndex: 1 } ) ] );

			const { onInput } = useStringValueInput( modelValueRef, propertyRef, mockEmit, mockPropertyType, serverViolationsRef );
			await nextTick();
			mockEmit.mockClear();

			onInput( [ 'https://ok.example', 'fixed' ] );

			expect( mockEmit ).toHaveBeenCalledWith(
				'clear-server-violation',
				{ propertyName: 'testProp', valuePartIndex: 1 },
			);
		} );

		it( 'does not emit clear-server-violation when editing an index without a violation', async () => {
			const modelValueRef = ref<Value | undefined>( newStringValue( 'first', 'bad' ) );
			const propertyRef = ref( createMockPropertyDefinition( {
				name: new PropertyName( 'testProp' ),
				multiple: true,
			} ) ) as Ref<MultiStringProperty>;
			const serverViolationsRef = ref<SubjectViolation[]>( [ violation( { code: 'invalid-url', valuePartIndex: 1 } ) ] );

			const { onInput } = useStringValueInput( modelValueRef, propertyRef, mockEmit, mockPropertyType, serverViolationsRef );
			await nextTick();
			mockEmit.mockClear();

			onInput( [ 'changed', 'bad' ] );

			expect( mockEmit ).not.toHaveBeenCalledWith( 'clear-server-violation', expect.anything() );
		} );

		it( 'recomputes messages when the serverViolations ref changes', async () => {
			const { fieldMessages, serverViolationsRef } = createComposableWithViolations(
				[],
				{ name: new PropertyName( 'testProp' ), multiple: false },
			);
			await nextTick();
			expect( fieldMessages.value ).toEqual( {} );

			serverViolationsRef.value = [ violation() ];
			await nextTick();

			expect( fieldMessages.value ).toEqual( { error: 'neowiki-field-required' } );
		} );
	} );
} );
