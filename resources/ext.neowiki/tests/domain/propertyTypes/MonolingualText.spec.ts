import { describe, expect, it } from 'vitest';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { PropertyName } from '@/domain/PropertyDefinition';

describe( 'MonolingualTextType', () => {
	const type = new MonolingualTextType();
	const base = { name: new PropertyName( 'Title' ), type: 'monolingualText', description: '', required: false };

	it( 'reads a single-valued property without uniqueness Constraints when the JSON sets none', () => {
		const property = type.createPropertyDefinitionFromJson( base, { type: 'monolingualText' } );

		expect( property.multiple ).toBe( false );
		expect( property.uniqueLanguages ).toBe( false );
	} );

	it( 'reads the attributes the JSON sets, keeping the ones every property type shares', () => {
		const property = type.createPropertyDefinitionFromJson(
			{ ...base, description: 'The title it was released under', required: true },
			{ type: 'monolingualText', multiple: true, uniqueLanguages: true, minLength: 2, maxLength: 50 },
		);

		expect( property ).toMatchObject( {
			name: new PropertyName( 'Title' ),
			type: 'monolingualText',
			description: 'The title it was released under',
			required: true,
			multiple: true,
			uniqueLanguages: true,
			minLength: 2,
			maxLength: 50,
		} );
	} );
} );
