import { markRaw, reactive } from 'vue';
import type { Component } from 'vue';

export class ViewTypeRegistry {

	// Reactive so a view type registered after a consumer (e.g. NeoWikiApp) has
	// already resolved its views re-triggers that resolution. Extensions register
	// through the neowiki.registration hook and their frontend module may load
	// after the app has mounted, so registration can race view rendering.
	// Components are markRaw'd so Vue does not wrap them in a reactive proxy.
	private types: Map<string, Component> = reactive( new Map<string, Component>() );

	public registerType( typeName: string, component: Component ): void {
		this.types.set( typeName, markRaw( component ) );
	}

	public getComponent( typeName: string ): Component {
		const component = this.types.get( typeName );

		if ( component === undefined ) {
			throw new Error( `Unknown view type: ${ typeName }` );
		}

		return component;
	}

	public hasType( typeName: string ): boolean {
		return this.types.has( typeName );
	}

	public getTypeNames(): string[] {
		return [ ...this.types.keys() ];
	}

}
