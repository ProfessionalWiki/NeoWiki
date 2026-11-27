import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineStore } from 'pinia';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import MonolingualTextDisplay from '@/components/Value/MonolingualTextDisplay.vue';
import MonolingualTextInput from '@/components/Value/MonolingualTextInput.vue';
import MonolingualTextAttributesEditor from '@/components/SchemaEditor/Property/MonolingualTextAttributesEditor.vue';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { setupMwMock } from './VueTestHelpers.ts';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient.ts';
import type { SchemaRepository } from '@/application/SchemaRepository.ts';
import type { LayoutRepository } from '@/application/LayoutRepository.ts';

describe( 'NeoWikiExtension registry caching', () => {
	it( 'returns the same TypeSpecificComponentRegistry instance on repeated calls', () => {
		const ext = NeoWikiExtension.getInstance();
		expect( ext.getTypeSpecificComponentRegistry() )
			.toBe( ext.getTypeSpecificComponentRegistry() );
	} );

	it( 'offers the monolingual text type with its own display, editor, attributes editor and label', () => {
		const registry = NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry();

		expect( registry.getPropertyTypes() ).toContain( MonolingualTextType.typeName );
		expect( registry.getValueDisplayComponent( MonolingualTextType.typeName ) ).toBe( MonolingualTextDisplay );
		expect( registry.getValueEditingComponent( MonolingualTextType.typeName ) ).toBe( MonolingualTextInput );
		expect( registry.getAttributesEditor( MonolingualTextType.typeName ) ).toBe( MonolingualTextAttributesEditor );
		expect( registry.getLabel( MonolingualTextType.typeName ) ).toBe( 'neowiki-property-type-monolingualtext' );
	} );

	it( 'returns the same ViewTypeRegistry instance on repeated calls so extension registrations persist', () => {
		const ext = NeoWikiExtension.getInstance();
		expect( ext.getViewTypeRegistry() )
			.toBe( ext.getViewTypeRegistry() );
	} );
} );

describe( 'NeoWikiExtension.getPinia', () => {
	it( 'returns the same Pinia instance on every call', () => {
		const ext = NeoWikiExtension.getInstance();
		const a = ext.getPinia();
		const b = ext.getPinia();
		expect( a ).toBe( b );
	} );

	it( 'state mutations through one store consumer are visible to another using the same Pinia', () => {
		const pinia = NeoWikiExtension.getInstance().getPinia();
		const useTestStore = defineStore( 'test-shared-state', {
			state: () => ( { count: 0 } ),
		} );

		useTestStore( pinia ).count = 7;

		expect( useTestStore( pinia ).count ).toBe( 7 );
	} );
} );

describe( 'NeoWikiExtension.newSubjectPermissionHints', () => {
	it( 'answers for the page being viewed with the decision the wiki sent', async () => {
		setupMwMock( { config: { wgArticleId: 42, wgNeoWikiCanEditPageSubjects: true } } );

		const hints = NeoWikiExtension.getInstance().newSubjectPermissionHints();

		expect( await hints.canEditSubject( 42 ) ).toBe( true );
	} );

	it( 'does not read the absence of a page as a page, as on a special page', async () => {
		setupMwMock( { config: { wgArticleId: 0, wgNeoWikiCanEditPageSubjects: true } } );

		const hints = NeoWikiExtension.getInstance().newSubjectPermissionHints();

		expect( await hints.canEditSubject( 0 ) ).toBe( false );
	} );
} );

describe( 'NeoWikiExtension.isValidationEnforced', () => {
	it( 'is true only when the wiki says so', () => {
		setupMwMock( { config: { wgNeoWikiEnforceValidation: true } } );
		expect( NeoWikiExtension.getInstance().isValidationEnforced() ).toBe( true );
	} );

	it( 'is false when the wiki does not enforce validation', () => {
		setupMwMock( { config: { wgNeoWikiEnforceValidation: false } } );
		expect( NeoWikiExtension.getInstance().isValidationEnforced() ).toBe( false );
	} );

	it( 'is false when the wiki did not say', () => {
		setupMwMock();
		expect( NeoWikiExtension.getInstance().isValidationEnforced() ).toBe( false );
	} );
} );

describe( 'NeoWikiExtension.getInitialConstraintSeverity', () => {
	it( 'is warning when the wiki did not say', () => {
		setupMwMock();
		expect( NeoWikiExtension.getInstance().getInitialConstraintSeverity() ).toBe( 'warning' );
	} );
} );

describe( 'NeoWikiExtension.getLatestStoreStateLoader', () => {
	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'reads a Subject as it is now while the page shows an older revision', async () => {
		setupMwMock( { functions: [ 'util' ], config: { wgRevisionId: 123, wgCurRevisionId: 130 } } );
		const requested: string[] = [];
		const ext = NeoWikiExtension.getInstance();
		// The Subject is what this is about; the page savers behind the Schema and Layout repositories need the
		// MediaWiki API.
		vi.spyOn( ext, 'getSchemaRepository' ).mockReturnValue( {} as unknown as SchemaRepository );
		vi.spyOn( ext, 'getLayoutRepository' ).mockReturnValue( {} as unknown as LayoutRepository );
		vi.spyOn( ext, 'newHttpClient' ).mockReturnValue( {
			get: async ( url: string ) => {
				requested.push( url );
				return new Response( null, { status: 404 } );
			},
		} as unknown as HttpClient );

		await ext.getLatestStoreStateLoader().loadSubjectsAndSchemas( new Set( [ 's1demo1aaaaaaa1' ] ), new Set() );

		expect( requested ).toHaveLength( 1 );
		expect( requested[ 0 ] ).not.toContain( 'revisionId' );
	} );
} );
