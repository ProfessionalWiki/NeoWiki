import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useNeoWikiStore } from '@/stores/Store';

describe( 'NeoWiki Store', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'should have the correct initial state', () => {
		const store = useNeoWikiStore();
		expect( store.extensionName ).toBe( 'NeoWiki Schema Selector' );
		expect( store.selectedSchemaType ).toBe( '' );
		expect( store.schemaTypes ).toHaveLength( 5 );
		expect( store.schemaProperties ).toHaveProperty( 'Person' );
	} );

	it( 'should update the schema type', () => {
		const store = useNeoWikiStore();
		store.updateSchemaType( 'Person' );
		expect( store.selectedSchemaType ).toBe( 'Person' );
	} );
} );
