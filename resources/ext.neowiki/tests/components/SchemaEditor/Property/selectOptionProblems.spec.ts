import { describe, expect, it } from 'vitest';
import { invalidSelectOption, selectOptionProblems } from '@/components/SchemaEditor/Property/selectOptionProblems.ts';
import { newSelectProperty } from '@/domain/propertyTypes/Select.ts';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';

const LABEL_REQUIRED = 'neowiki-property-editor-option-label-required';
const LABEL_TAKEN = 'neowiki-property-editor-options-unique';

describe( 'selectOptionProblems', () => {
	it( 'finds nothing wrong with options that each have a label of their own', () => {
		expect( selectOptionProblems( [
			{ id: 'o1', label: 'Draft' },
			{ id: 'o2', label: 'Published' },
		] ) ).toEqual( new Map() );
	} );

	it( 'names each option without a label', () => {
		expect( selectOptionProblems( [
			{ id: 'o1', label: 'Draft' },
			{ id: 'o2', label: '' },
			{ id: 'o3', label: '' },
		] ) ).toEqual( new Map( [ [ 'o2', LABEL_REQUIRED ], [ 'o3', LABEL_REQUIRED ] ] ) );
	} );

	it( 'counts a label of only spaces as none', () => {
		expect( selectOptionProblems( [ { id: 'o1', label: '   ' } ] ) )
			.toEqual( new Map( [ [ 'o1', LABEL_REQUIRED ] ] ) );
	} );

	it( 'names both options when their labels differ only in letter case', () => {
		expect( selectOptionProblems( [
			{ id: 'o1', label: 'Draft' },
			{ id: 'o2', label: 'Published' },
			{ id: 'o3', label: 'draft' },
		] ) ).toEqual( new Map( [ [ 'o1', LABEL_TAKEN ], [ 'o3', LABEL_TAKEN ] ] ) );
	} );

	it( 'names both options when their labels differ only in surrounding spaces', () => {
		expect( selectOptionProblems( [
			{ id: 'o1', label: 'Draft' },
			{ id: 'o2', label: ' Draft ' },
		] ) ).toEqual( new Map( [ [ 'o1', LABEL_TAKEN ], [ 'o2', LABEL_TAKEN ] ] ) );
	} );
} );

describe( 'invalidSelectOption', () => {
	it( 'finds nothing wrong with a property of another type', () => {
		expect( invalidSelectOption( newNumberProperty( { name: 'Score' } ) ) ).toBeNull();
	} );

	it( 'finds nothing wrong with options that each have a label of their own', () => {
		expect( invalidSelectOption( newSelectProperty( { options: [ { id: 'o1', label: 'Draft' } ] } ) ) ).toBeNull();
	} );

	it( 'names what is wrong with the first faulty option', () => {
		expect( invalidSelectOption( newSelectProperty( { options: [
			{ id: 'o1', label: 'Draft' },
			{ id: 'o2', label: 'Published' },
			{ id: 'o3', label: 'published' },
			{ id: 'o4', label: '' },
		] } ) ) ).toBe( LABEL_TAKEN );
	} );
} );
