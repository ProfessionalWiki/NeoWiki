import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { markRaw } from 'vue';
import { FrontendRegistrar } from '@/presentation/FrontendRegistrar';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { ViewTypeRegistry } from '@/ViewTypeRegistry';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import { ValueType, newStringValue } from '@/domain/Value';

// Minimal mw.hook stub matching MediaWiki's replay-on-late-subscribe behavior.
// https://doc.wikimedia.org/mediawiki-core/master/js/mw.hook.html — .add() after .fire()
// invokes the handler immediately with the previously-fired arguments.
function setupMwHook(): void {
	const hooks: Record<string, { args: unknown[] | null; handlers: ( ( ...a: unknown[] ) => void )[] }> = {};
	( globalThis as any ).mw = {
		hook: ( name: string ) => {
			hooks[ name ] ??= { args: null, handlers: [] };
			const h = hooks[ name ];
			return {
				add: ( fn: ( ...a: unknown[] ) => void ): void => {
					h.handlers.push( fn );
					if ( h.args !== null ) {
						fn( ...h.args );
					}
				},
				fire: ( ...args: unknown[] ): void => {
					h.args = args;
					h.handlers.forEach( ( fn ) => fn( ...args ) );
				},
			};
		},
	};
}

function fakeRegistration( name: string ): PropertyTypeRegistration {
	const stub = markRaw( { render: (): null => null } );
	return {
		typeName: name,
		valueType: ValueType.String,
		displayAttributeNames: [],
		createPropertyDefinitionFromJson: ( base ) => base,
		getExampleValue: () => newStringValue( 'x' ),
		displayComponent: stub,
		inputComponent: stub,
		attributesEditor: stub,
		label: `label-${ name }`,
		icon: 'icon' as any,
	};
}

describe( 'neowiki.registration hook end-to-end', () => {
	beforeEach( () => setupMwHook() );

	it( 'lets a subscriber register a type visible via the component registry', () => {
		const componentRegistry = new TypeSpecificComponentRegistry();
		const typeRegistry = new PropertyTypeRegistry();
		const registrar = new FrontendRegistrar( componentRegistry, typeRegistry, new ViewTypeRegistry() );

		( globalThis as any ).mw.hook( 'neowiki.registration' ).add( ( r: FrontendRegistrar ) => {
			r.registerPropertyType( fakeRegistration( 'fake' ) );
		} );

		( globalThis as any ).mw.hook( 'neowiki.registration' ).fire( registrar );

		expect( typeRegistry.getTypeNames() ).toContain( 'fake' );
		expect( componentRegistry.getPropertyTypes() ).toContain( 'fake' );
	} );

	it( 'invokes handlers that subscribe AFTER fire via replay', () => {
		const componentRegistry = new TypeSpecificComponentRegistry();
		const typeRegistry = new PropertyTypeRegistry();
		const registrar = new FrontendRegistrar( componentRegistry, typeRegistry, new ViewTypeRegistry() );

		( globalThis as any ).mw.hook( 'neowiki.registration' ).fire( registrar );
		( globalThis as any ).mw.hook( 'neowiki.registration' ).add( ( r: FrontendRegistrar ) => {
			r.registerPropertyType( fakeRegistration( 'late' ) );
		} );

		expect( typeRegistry.getTypeNames() ).toContain( 'late' );
	} );
} );

describe( 'ext.neowiki module load', () => {
	beforeEach( () => setupMwHook() );

	afterEach( () => {
		( window as unknown as { neoWikiTestMode?: boolean } ).neoWikiTestMode = true;
	} );

	it( 'fires neowiki.registration with a registrar wired to the live extension registries', async () => {
		( window as unknown as { neoWikiTestMode?: boolean } ).neoWikiTestMode = false;

		vi.resetModules();
		await import( '@/neowiki' );
		const { NeoWikiExtension } = await import( '@/NeoWikiExtension' );

		let receivedRegistrar: FrontendRegistrar | null = null;
		( globalThis as any ).mw.hook( 'neowiki.registration' ).add( ( r: FrontendRegistrar ) => {
			receivedRegistrar = r;
		} );

		receivedRegistrar!.registerPropertyType( fakeRegistration( 'boot-fake' ) );
		receivedRegistrar!.registerViewType( {
			typeName: 'boot-fake-view',
			component: markRaw( { render: (): null => null } ),
		} );

		const ext = NeoWikiExtension.getInstance();
		expect( ext.getPropertyTypeRegistry().getTypeNames() ).toContain( 'boot-fake' );
		expect( ext.getTypeSpecificComponentRegistry().getPropertyTypes() ).toContain( 'boot-fake' );
		expect( ext.getViewTypeRegistry().hasType( 'boot-fake-view' ) ).toBe( true );
	} );
} );
