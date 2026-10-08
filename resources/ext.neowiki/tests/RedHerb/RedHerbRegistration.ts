import { FrontendRegistrar } from '@/presentation/FrontendRegistrar.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { createPropertyDefinitionFromJson, PropertyDefinition } from '@/domain/PropertyDefinition.ts';

export const COLOR_TYPE_NAME = 'color';
export const CARD_VIEW_TYPE_NAME = 'redherb-card';

/**
 * Runs RedHerb's registration package file the way ResourceLoader and NeoWiki do: the module
 * subscribes to neowiki.registration on load, and NeoWiki fires it with a registrar wired to
 * the live registries. Specs that mount RedHerb components need this, because the components
 * look their own property type up in the registry.
 *
 * mw.hook replays its last fire to handlers added afterwards, so the order of the two halves
 * does not matter here any more than it does in production.
 */
export async function loadRedHerbFrontend(): Promise<void> {
	stubMwHook();

	await import( '@redherb/init.js' );

	const extension = NeoWikiExtension.getInstance();
	mw.hook( 'neowiki.registration' ).fire(
		new FrontendRegistrar(
			extension.getTypeSpecificComponentRegistry(),
			extension.getPropertyTypeRegistry(),
			extension.getViewTypeRegistry(),
		),
	);
}

/**
 * Builds a color PropertyDefinition through the deserializer, so it passes through the
 * createPropertyDefinitionFromJson that RedHerb registered.
 */
export function newColorProperty( json: Record<string, unknown> = {} ): PropertyDefinition {
	return createPropertyDefinitionFromJson(
		( json.name as string ) ?? 'favouriteColor',
		{ type: COLOR_TYPE_NAME, ...json },
	);
}

interface HookState {
	firedArguments: unknown[] | null;
	handlers: ( ( ...args: unknown[] ) => void )[];
}

function stubMwHook(): void {
	const hooks: Record<string, HookState> = {};

	( globalThis as any ).mw = {
		hook: ( name: string ) => {
			hooks[ name ] ??= { firedArguments: null, handlers: [] };
			const hook = hooks[ name ];

			return {
				add: ( handler: ( ...args: unknown[] ) => void ): void => {
					hook.handlers.push( handler );
					if ( hook.firedArguments !== null ) {
						handler( ...hook.firedArguments );
					}
				},
				fire: ( ...args: unknown[] ): void => {
					hook.firedArguments = args;
					hook.handlers.forEach( ( handler ) => handler( ...args ) );
				},
			};
		},
	};
}
