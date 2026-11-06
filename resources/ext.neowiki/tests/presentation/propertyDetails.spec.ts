import { beforeEach, describe, expect, it } from 'vitest';
import { requiredExplanation, typeDetails } from '@/presentation/propertyDetails';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition';
import { setupMwMock } from '../VueTestHelpers.ts';

function detailsOf( json: Record<string, unknown>, validationEnforced = false ): string | null {
	return typeDetails( createPropertyDefinitionFromJson( 'Property', json ), validationEnforced );
}

function explanationOf( json: Record<string, unknown>, validationEnforced = false ): string | null {
	return requiredExplanation( createPropertyDefinitionFromJson( 'Property', json ), validationEnforced );
}

describe( 'propertyDetails', () => {

	beforeEach( () => {
		setupMwMock( {
			messages: {
				'neowiki-schema-display-constraint-multiple': 'Multiple values',
				'neowiki-schema-display-constraint-unique-items': 'Values must be unique',
				'neowiki-schema-display-constraint-one-per-language': 'One value per language',
				'neowiki-schema-display-constraint-multiple-per-language': 'Multiple values per language',
				'neowiki-schema-display-constraint-minimum': ( bound: string ) => `Minimum: ${ bound }`,
				'neowiki-schema-display-constraint-maximum': ( bound: string ) => `Maximum: ${ bound }`,
				'neowiki-schema-display-constraint-min-length': ( length: string ) => `At least ${ length } characters`,
				'neowiki-schema-display-constraint-max-length': ( length: string ) => `At most ${ length } characters`,
				'neowiki-schema-display-constraint-options': ( options: string ) => `Options: ${ options }`,
				'neowiki-schema-display-detail-targets': ( targets: string ) => `Target schemas: ${ targets }`,
				'neowiki-schema-display-detail-initial-value': ( value: string ) => `Initial value: ${ value }`,
				'neowiki-schema-display-detail-blocks-saving': ( detail: string ) => `${ detail } (blocks saving)`,
				'neowiki-schema-display-detail-error': ( detail: string ) => `${ detail } (error)`,
				'neowiki-schema-display-required-warning': 'Saving without a value shows a warning',
				'neowiki-schema-display-required-error': 'Saving without a value shows an error',
				'neowiki-schema-display-required-blocks-saving': 'Saving without a value is blocked',
			},
		} );
	} );

	describe( 'typeDetails', () => {

		it( 'is null for a property with nothing to add', () => {
			expect( detailsOf( { type: 'text' } ) ).toBeNull();
		} );

		it( 'leaves out Required, which has its own marker', () => {
			expect( detailsOf( { type: 'text', required: true } ) ).toBeNull();
		} );

		it( 'gives the target Schemas of a relation property', () => {
			expect( detailsOf( { type: 'relation', targetSchemas: [ 'Product', 'Service' ] } ) )
				.toBe( 'Target schemas: Product, Service' );
		} );

		it( 'gives a target Schema of another Source with its source key', () => {
			expect( detailsOf( { type: 'relation', targetSchemas: [ { source: 'farm', name: 'Product' } ] } ) )
				.toBe( 'Target schemas: farm:Product' );
		} );

		it( 'says that a property takes multiple values', () => {
			expect( detailsOf( { type: 'url', multiple: true } ) ).toBe( 'Multiple values' );
		} );

		it( 'says that multiple values must be unique', () => {
			expect( detailsOf( { type: 'text', multiple: true, uniqueItems: true } ) )
				.toBe( 'Multiple values · Values must be unique' );
		} );

		it( 'says nothing of uniqueness for a property that takes one value', () => {
			expect( detailsOf( { type: 'text', uniqueItems: true } ) ).toBeNull();
		} );

		it( 'says one value per language for text with language', () => {
			expect( detailsOf( { type: 'monolingualText', multiple: true, uniqueLanguages: true } ) ).toBe( 'One value per language' );
		} );

		it( 'says multiple values per language for text with language', () => {
			expect( detailsOf( { type: 'monolingualText', multiple: true } ) ).toBe( 'Multiple values per language' );
		} );

		it( 'gives multiple values no severity, whatever the switched-off multiple Constraint keeps', () => {
			expect( detailsOf( { type: 'monolingualText', multiple: { value: true, severity: 'error' } }, true ) )
				.toBe( 'Multiple values per language' );
		} );

		it( 'gives a range\'s bounds the way the property shows its values', () => {
			expect( detailsOf( { type: 'number', precision: 1, minimum: 0, maximum: 100 } ) )
				.toBe( 'Minimum: 0.0 · Maximum: 100.0' );
		} );

		it( 'gives a date bound as a date', () => {
			const details = detailsOf( { type: 'date', minimum: '2020-01-01' } );

			expect( details ).toContain( '2020' );
			expect( details ).not.toContain( '2020-01-01' );
		} );

		it( 'gives character lengths', () => {
			expect( detailsOf( { type: 'text', minLength: 3, maxLength: 200 } ) )
				.toBe( 'At least 3 characters · At most 200 characters' );
		} );

		it( 'lists the options of a select property', () => {
			expect( detailsOf( {
				type: 'select',
				options: [
					{ id: 'o1', label: 'Active' },
					{ id: 'o2', label: 'Inactive' },
					{ id: 'o3', label: 'Pending' },
				],
			} ) ).toBe( 'Options: Active, Inactive, Pending' );
		} );

		it( 'gives the initial value the way the property shows its values', () => {
			expect( detailsOf( { type: 'number', precision: 1, default: 5 } ) ).toBe( 'Initial value: 5.0' );
		} );

		it( 'leaves a warning Constraint unmarked', () => {
			expect( detailsOf( { type: 'number', maximum: 100 }, true ) ).toBe( 'Maximum: 100' );
		} );

		it( 'says an error Constraint blocks saving where the wiki enforces validation', () => {
			expect( detailsOf( { type: 'number', maximum: { value: 100, severity: 'error' } }, true ) )
				.toBe( 'Maximum: 100 (blocks saving)' );
		} );

		it( 'calls an error Constraint an error where the wiki does not enforce validation', () => {
			expect( detailsOf( { type: 'number', maximum: { value: 100, severity: 'error' } }, false ) )
				.toBe( 'Maximum: 100 (error)' );
		} );

	} );

	describe( 'requiredExplanation', () => {

		it( 'is null for an optional property', () => {
			expect( explanationOf( { type: 'text' } ) ).toBeNull();
		} );

		it( 'says a missing value shows a warning', () => {
			expect( explanationOf( { type: 'text', required: true }, true ) ).toBe( 'Saving without a value shows a warning' );
		} );

		it( 'says a missing value blocks saving where the wiki enforces validation', () => {
			expect( explanationOf( { type: 'text', required: { severity: 'error' } }, true ) )
				.toBe( 'Saving without a value is blocked' );
		} );

		it( 'says a missing value shows an error where the wiki does not enforce validation', () => {
			expect( explanationOf( { type: 'text', required: { severity: 'error' } }, false ) )
				.toBe( 'Saving without a value shows an error' );
		} );

	} );

} );
