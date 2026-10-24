import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import MonolingualTextAttributesEditor from '@/components/SchemaEditor/Property/MonolingualTextAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { MonolingualTextProperty, newMonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText';
import type { Severity } from '@/domain/Severity';
import { accessibleName, createTestWrapper, setupMwMock } from '../../../VueTestHelpers.ts';

const ONE = 'neowiki-property-editor-values-one';
const ONE_PER_LANGUAGE = 'neowiki-property-editor-values-one-per-language';
const MULTIPLE_PER_LANGUAGE = 'neowiki-property-editor-values-multiple-per-language';

const OPTIONS_WITH_A_CONSTRAINT: [ string, Pick<MonolingualTextProperty, 'multiple'> & Partial<MonolingualTextProperty>, string ][] = [
	[ ONE, { multiple: false }, 'multiple' ],
	[ ONE_PER_LANGUAGE, { multiple: true, uniqueLanguages: true }, 'uniqueLanguages' ],
];

describe( 'MonolingualTextAttributesEditor', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'message' ] } );
	} );

	function newWrapper( property: MonolingualTextProperty ): VueWrapper {
		return createTestWrapper( MonolingualTextAttributesEditor, { property } );
	}

	function radioInputs( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement>[] {
		return wrapper.findAll<HTMLInputElement>( 'input[type="radio"]' );
	}

	function inputNamed( wrapper: VueWrapper, name: string ): DOMWrapper<HTMLInputElement> {
		const input = wrapper.findAll<HTMLInputElement>( 'input' )
			.find( ( candidate ) => accessibleName( candidate.element ) === name );

		expect( input, `input ${ name }` ).toBeDefined();
		return input!;
	}

	function chosenOptions( wrapper: VueWrapper ): string[] {
		return radioInputs( wrapper )
			.filter( ( radio ) => radio.element.checked )
			.map( ( radio ) => accessibleName( radio.element ) );
	}

	function optionOf( severityInput: VueWrapper ): string {
		return accessibleName(
			severityInput.element.closest( '.ext-neowiki-monolingual-text-attributes__option' )!.querySelector( 'input[type="radio"]' )!,
		);
	}

	// Each severity control by the option it sits on, the Constraint it names and the severity it shows.
	function offeredSeverities( wrapper: VueWrapper ): [ string, string, Severity | undefined ][] {
		return wrapper.findAllComponents( SeverityInput ).map( ( severityInput ) => [
			optionOf( severityInput ),
			severityInput.props( 'constraint' ),
			severityInput.props( 'modelValue' ),
		] );
	}

	function severityInputOn( wrapper: VueWrapper, option: string ): VueWrapper {
		const severityInput = wrapper.findAllComponents( SeverityInput )
			.find( ( candidate ) => optionOf( candidate ) === option );

		expect( severityInput, `severity of ${ option }` ).toBeDefined();
		return severityInput!;
	}

	it.each( [
		[ ONE, 'without multiple values', { multiple: false, uniqueLanguages: false } ],
		[ ONE, 'without multiple values, even with unique languages', { multiple: false, uniqueLanguages: true } ],
		[ ONE_PER_LANGUAGE, 'with multiple values and unique languages', { multiple: true, uniqueLanguages: true } ],
		[ MULTIPLE_PER_LANGUAGE, 'with multiple values only', { multiple: true, uniqueLanguages: false } ],
	] )( 'shows %s chosen %s', ( option, _, attributes ) => {
		const wrapper = newWrapper( newMonolingualTextProperty( attributes ) );

		expect( chosenOptions( wrapper ) ).toEqual( [ option ] );
	} );

	it.each( [
		[ ONE, { multiple: true, uniqueLanguages: true }, { multiple: false, uniqueLanguages: false } ],
		[ ONE_PER_LANGUAGE, { multiple: false, uniqueLanguages: false }, { multiple: true, uniqueLanguages: true } ],
		[ MULTIPLE_PER_LANGUAGE, { multiple: true, uniqueLanguages: true }, { multiple: true, uniqueLanguages: false } ],
	] )( 'emits the attributes of %s when it is chosen', async ( option, before, after ) => {
		const wrapper = newWrapper( newMonolingualTextProperty( before ) );

		await inputNamed( wrapper, option ).setValue( true );

		expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ after ] ] );
	} );

	it( 'makes the options one group of radios', () => {
		const [ first, ...others ] = radioInputs( newWrapper( newMonolingualTextProperty() ) )
			.map( ( radio ) => radio.element.name );

		expect( first ).not.toBe( '' );
		expect( others ).toEqual( [ first, first ] );
	} );

	it( 'names the group of options after the number of values', () => {
		const fieldset = radioInputs( newWrapper( newMonolingualTextProperty() ) )[ 0 ].element.closest( 'fieldset' );

		expect( fieldset?.querySelector( 'legend' )?.textContent?.trim() ).toBe( 'neowiki-property-editor-values' );
	} );

	describe( 'Constraint severity', () => {
		it.each( OPTIONS_WITH_A_CONSTRAINT )( 'offers the severity of %s on that option alone while it is chosen', ( option, attributes, constraint ) => {
			const wrapper = newWrapper( {
				...newMonolingualTextProperty( attributes ),
				constraintSeverities: { [ constraint ]: 'error' },
			} );

			expect( offeredSeverities( wrapper ) ).toEqual( [ [ option, option, 'error' ] ] );
		} );

		it( 'offers no severity for multiple values per language, which constrains nothing', () => {
			const wrapper = newWrapper( newMonolingualTextProperty( { multiple: true, uniqueLanguages: false } ) );

			expect( offeredSeverities( wrapper ) ).toEqual( [] );
		} );

		it.each( OPTIONS_WITH_A_CONSTRAINT )( 'emits the changed severity of %s, keeping the other annotations', async ( option, attributes, constraint ) => {
			const wrapper = newWrapper( {
				...newMonolingualTextProperty( { ...attributes, minLength: 2 } ),
				constraintSeverities: { minLength: 'error' },
			} );

			await severityInputOn( wrapper, option ).vm.$emit( 'update:modelValue', 'error' );

			expect( wrapper.emitted( 'update:property' ) ).toEqual( [
				[ { constraintSeverities: { minLength: 'error', [ constraint ]: 'error' } } ],
			] );
		} );
	} );

	it( 'replaces the checkboxes for multiple and unique values', () => {
		const wrapper = newWrapper( newMonolingualTextProperty( { multiple: true } ) );

		expect( wrapper.findAll( 'input[type="checkbox"]' ) ).toHaveLength( 0 );
	} );

	it( 'passes on a change of the character length', async () => {
		const wrapper = newWrapper( newMonolingualTextProperty() );

		await inputNamed( wrapper, 'neowiki-property-editor-maximum' ).setValue( '40' );

		expect( wrapper.emitted( 'update:property' ) ).toEqual( [ [ { maxLength: 40 } ] ] );
	} );
} );
