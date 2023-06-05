import { createPropertyDefinitionFromJson, ValueFormat, ValueType } from '@/editor/domain/Schema';
import { describe, expect, it } from 'vitest';

describe( 'createPropertyDefinitionFromJson', () => {

	it( 'creates a property definition with defaults omitted', () => {
		const json = {
			type: 'boolean',
			format: 'checkbox'
		};

		const property = createPropertyDefinitionFromJson( json );

		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
	} );

	it( 'creates a property definition with defaults specified', () => {
		const json = {
			type: 'boolean',
			format: 'checkbox',
			description: 'Foo',
			required: true
		};

		const property = createPropertyDefinitionFromJson( json );

		expect( property.description ).toBe( 'Foo' );
		expect( property.required ).toBe( true );
	} );

	it( 'creates a string property definition', () => {
		const json = {
			type: 'string',
			format: 'text',
			multiple: true,
			uniqueItems: false
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.type === ValueType.String ) {
			expect( property.multiple ).toBe( true );
			expect( property.uniqueItems ).toBe( false );
		}

		expect( property.type ).toBe( ValueType.String );
		expect( property.format ).toBe( ValueFormat.Text );
	} );

	it( 'creates a number property definition with defaults', () => {
		const json = {
			type: 'number',
			format: 'number'
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.format === ValueFormat.Number ) {
			expect( property.minimum ).toBe( undefined );
			expect( property.maximum ).toBe( undefined );
			expect( property.precision ).toBe( undefined );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Number );
	} );

	it( 'creates a number property definition with all fields', () => {
		const json = {
			type: 'number',
			format: 'number',
			minimum: 42,
			maximum: 1337,
			precision: 2
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.format === ValueFormat.Number ) {
			expect( property.minimum ).toBe( 42 );
			expect( property.maximum ).toBe( 1337 );
			expect( property.precision ).toBe( 2 );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Number );
	} );

	it( 'creates a currency property definition with defaults', () => {
		const json = {
			type: 'number',
			format: 'currency',
			currencyCode: 'EUR',
			precision: 2
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.format === ValueFormat.Currency ) {
			expect( property.currencyCode ).toBe( 'EUR' );
			expect( property.precision ).toBe( 2 );
			expect( property.minimum ).toBe( undefined );
			expect( property.maximum ).toBe( undefined );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Currency );
	} );

	it( 'creates a currency property definition with all fields', () => {
		const json = {
			type: 'number',
			format: 'currency',
			currencyCode: 'EUR',
			precision: 2,
			minimum: 42,
			maximum: 1337
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.format === ValueFormat.Currency ) {
			expect( property.currencyCode ).toBe( 'EUR' );
			expect( property.precision ).toBe( 2 );
			expect( property.minimum ).toBe( 42 );
			expect( property.maximum ).toBe( 1337 );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Currency );
	} );

	it( 'creates a currency progress property definition', () => {
		const json = {
			type: 'number',
			format: 'progress',
			minimum: 42,
			maximum: 1337,
			step: 23
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.format === ValueFormat.Progress ) {
			expect( property.minimum ).toBe( 42 );
			expect( property.maximum ).toBe( 1337 );
			expect( property.step ).toBe( 23 );
		}

		expect( property.type ).toBe( ValueType.Number );
		expect( property.format ).toBe( ValueFormat.Progress );
	} );

	it( 'creates a boolean property definition', () => {
		const json = {
			type: 'boolean',
			format: 'checkbox'
		};

		const property = createPropertyDefinitionFromJson( json );

		expect( property.type ).toBe( ValueType.Boolean );
		expect( property.format ).toBe( ValueFormat.Checkbox );
	} );

	it( 'creates a relation property definition with defaults', () => {
		const json = {
			type: 'relation',
			format: 'relation',
			relation: 'Employer',
			targetSchema: 'Company'
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.type === ValueType.Relation ) {
			expect( property.relation ).toBe( 'Employer' );
			expect( property.targetSchema ).toBe( 'Company' );
			expect( property.multiple ).toBe( false );
			expect( property.uniqueItems ).toBe( true );
		}

		expect( property.type ).toBe( ValueType.Relation );
		expect( property.format ).toBe( ValueFormat.Relation );
	} );

	it( 'creates a relation property definition with all fields', () => {
		const json = {
			type: 'relation',
			format: 'relation',
			relation: 'Employer',
			targetSchema: 'Company',
			multiple: true,
			uniqueItems: false
		};

		const property = createPropertyDefinitionFromJson( json );

		if ( property.type === ValueType.Relation ) {
			expect( property.multiple ).toBe( true );
			expect( property.uniqueItems ).toBe( false );
		}

		expect( property.type ).toBe( ValueType.Relation );
	} );

	it( 'throws an error for an unsupported type', () => {
		const json = {
			type: 'unsupported',
			format: 'text'
		};

		expect( () => createPropertyDefinitionFromJson( json ) ).toThrow( 'Unsupported type: unsupported' );
	} );

} );
