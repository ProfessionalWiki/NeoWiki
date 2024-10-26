import { describe, it, expect, vi, beforeEach } from 'vitest';
import { nextTick } from 'vue';
import { useMultiStringInput, type MultiStringInputReturn } from '@/composables/useMultiStringInput';
import { ValueType, newStringValue, type StringValue } from '@neo/domain/Value';
import { type ValidationResult } from '@/composables/useMultiStringInput';

interface MockProps {
	modelValue: StringValue;
	property: {
		name: string;
		required: boolean;
	};
}

type EmitType = {
	( event: 'update:modelValue', payload: StringValue ): void;
	( event: 'validation', payload: boolean ): void;
};

describe( 'useMultiStringInput', () => {
	const mockEmit = vi.fn() as EmitType;
	let mockProps: MockProps;

	const createValidationMock = ( isValid = true ): ValidationResult => ( {
		isValid,
		statuses: [],
		messages: []
	} );

	const createComposable = ( modelValue?: string[] ): MultiStringInputReturn => {
		if ( modelValue ) {
			mockProps.modelValue = newStringValue( ...modelValue );
		}
		return useMultiStringInput( mockProps, mockEmit );
	};

	const expectModelValueEmit = ( strings: string[], callNumber = 1 ): void => {
		expect( mockEmit ).toHaveBeenNthCalledWith( callNumber, 'update:modelValue', {
			type: ValueType.String,
			strings
		} );
	};

	beforeEach( (): void => {
		vi.clearAllMocks();
		mockProps = {
			modelValue: newStringValue( 'initial value' ),
			property: {
				name: 'testField',
				required: false
			}
		};
	} );

	describe( 'initialization', () => {
		it( 'should initialize with empty array if no strings provided', () => {
			const { inputValues } = createComposable( [] );
			expect( inputValues.value ).toEqual( [ '' ] );
		} );

		it( 'should initialize with provided non-empty strings', () => {
			const { inputValues } = createComposable( [ 'test1', 'test2' ] );
			expect( inputValues.value ).toEqual( [ 'test1', 'test2' ] );
		} );

		it( 'should filter out empty strings from initial value', () => {
			const { inputValues } = createComposable( [ 'test1', '', '  ', 'test2' ] );
			expect( inputValues.value ).toEqual( [ 'test1', 'test2' ] );
		} );
	} );

	describe( 'computed properties', () => {
		it( 'should disable add button when any input is empty', () => {
			const { isAddButtonDisabled, inputValues } = createComposable();
			inputValues.value.push( '' );
			expect( isAddButtonDisabled.value ).toBe( true );
		} );

		it( 'should disable add button when validation is invalid', () => {
			const { isAddButtonDisabled, validationState } = createComposable();
			validationState.value.isValid = false;
			expect( isAddButtonDisabled.value ).toBe( true );
		} );

		describe( 'required field validation', () => {
			beforeEach( () => {
				mockProps.property.required = true;
			} );

			it( 'should be valid with non-empty values', () => {
				const { isRequiredFieldInValid } = createComposable( [ 'valid value' ] );
				expect( isRequiredFieldInValid.value ).toBe( false );
			} );

			it( 'should be invalid with empty values', () => {
				const { isRequiredFieldInValid } = createComposable( [] );
				expect( isRequiredFieldInValid.value ).toBe( true );
			} );
		} );
	} );

	describe( 'input handlers', () => {
		const mockValidateFn = vi.fn().mockImplementation( createValidationMock );

		describe( 'handleInput', () => {
			it( 'should update input value and emit updates with trimmed values', () => {
				const { handleInput, inputValues } = createComposable();
				const mockValidateFn = vi.fn().mockReturnValue( {
					isValid: true,
					statuses: [],
					messages: []
				} );

				handleInput( '  newValue  ', 0, mockValidateFn );

				expect( inputValues.value[ 0 ] ).toBe( '  newValue  ' );
				expect( mockEmit ).toHaveBeenCalledWith( 'validation', true );
				expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', {
					type: ValueType.String,
					strings: [ 'newValue' ]
				} );
			} );

			it( 'should handle empty input values', () => {
				const { handleInput } = createComposable();
				const mockValidateFn = vi.fn().mockReturnValue( {
					isValid: true,
					statuses: [],
					messages: []
				} );

				handleInput( '', 0, mockValidateFn );

				expect( mockEmit ).toHaveBeenCalledWith( 'update:modelValue', {
					type: ValueType.String,
					strings: []
				} );
			} );
		} );

		describe( 'handleAdd', () => {
			it( 'should add new empty input and emit updates', async () => {
				const { handleAdd, inputValues } = createComposable();
				await handleAdd( 'text' );

				expect( inputValues.value ).toEqual( [ 'initial value', '' ] );
				expectModelValueEmit( [ 'initial value' ] );
				expect( mockEmit ).toHaveBeenCalledWith( 'validation', false );
			} );

			it( 'should focus new input after adding', async () => {
				const mockFocus = vi.fn();
				vi.spyOn( document, 'querySelector' ).mockReturnValue(
					{ focus: mockFocus } as unknown as HTMLInputElement
				);

				const { handleAdd } = createComposable();
				await handleAdd( 'text' );
				await nextTick();

				expect( mockFocus ).toHaveBeenCalled();
			} );
		} );

		describe( 'handleRemove', () => {
			it( 'should remove input at specified index', () => {
				const { handleRemove, inputValues } = createComposable( [ 'test1', 'test2' ] );
				handleRemove( 0, mockValidateFn );

				expect( inputValues.value ).toEqual( [ 'test2' ] );
				expectModelValueEmit( [ 'test2' ] );
				expect( mockValidateFn ).toHaveBeenCalledWith( [ 'test2' ] );
			} );

			it( 'should handle removing last value', () => {
				const { handleRemove } = createComposable( [ 'test1' ] );
				handleRemove( 0, mockValidateFn );
				expectModelValueEmit( [] );
			} );
		} );
	} );
} );
