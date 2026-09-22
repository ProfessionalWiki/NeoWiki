import { describe, expect, it } from 'vitest';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { PropertyName } from '@/domain/PropertyDefinition';

describe( 'createPropertyDefinitionFromJson', () => {
	const type = new MonolingualTextType();
	const base = { name: new PropertyName( 'Title' ), type: 'monolingualText', description: '', required: false };

	it( 'reads a single-valued property that allows repeated parts when the JSON sets neither', () => {
		const property = type.createPropertyDefinitionFromJson( base, { type: 'monolingualText' } );

		expect( property.multiple ).toBe( false );
		expect( property.uniqueItems ).toBe( false );
	} );

	it( 'reads the attributes the JSON sets', () => {
		const property = type.createPropertyDefinitionFromJson(
			base,
			{ type: 'monolingualText', multiple: true, uniqueItems: true, minLength: 2, maxLength: 50 },
		);

		expect( property ).toMatchObject( { multiple: true, uniqueItems: true, minLength: 2, maxLength: 50 } );
	} );
} );
