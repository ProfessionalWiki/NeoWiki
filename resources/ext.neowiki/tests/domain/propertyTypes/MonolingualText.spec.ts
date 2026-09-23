import { describe, expect, it } from 'vitest';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newMonolingualTextValue } from '@/domain/Value';

describe( 'MonolingualTextType', () => {
	const type = new MonolingualTextType();
	const base = { name: new PropertyName( 'Title' ), type: 'monolingualText', description: '', required: false };

	it( 'has no display attributes', () => {
		expect( type.getDisplayAttributeNames() ).toEqual( [] );
	} );

	it( 'provides a monolingual text example value', () => {
		expect( type.getExampleValue() )
			.toStrictEqual( newMonolingualTextValue( [ { text: 'Some Text', language: 'en' } ] ) );
	} );

	it( 'reads a single-valued property that allows repeated parts when the JSON sets neither', () => {
		const property = type.createPropertyDefinitionFromJson( base, { type: 'monolingualText' } );

		expect( property.multiple ).toBe( false );
		expect( property.uniqueItems ).toBe( false );
	} );

	it( 'reads the attributes the JSON sets, keeping the ones every property type shares', () => {
		const property = type.createPropertyDefinitionFromJson(
			{ ...base, description: 'The title it was released under', required: true },
			{ type: 'monolingualText', multiple: true, uniqueItems: true, minLength: 2, maxLength: 50 },
		);

		expect( property ).toMatchObject( {
			name: new PropertyName( 'Title' ),
			type: 'monolingualText',
			description: 'The title it was released under',
			required: true,
			multiple: true,
			uniqueItems: true,
			minLength: 2,
			maxLength: 50,
		} );
	} );
} );
