import { describe, expect, it } from 'vitest';
import { Neo } from '@/Neo';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';

describe( 'Neo registry caching', () => {
	it( 'returns the same PropertyTypeRegistry instance on repeated calls', () => {
		const neo = Neo.getInstance();
		expect( neo.getPropertyTypeRegistry() )
			.toBe( neo.getPropertyTypeRegistry() );
	} );

	it( 'registers the monolingual text property type', () => {
		expect( Neo.getInstance().getPropertyTypeRegistry().getType( MonolingualTextType.typeName ) )
			.toBeInstanceOf( MonolingualTextType );
	} );
} );
