import type { Component } from 'vue';

export class ViewTypeRegistry {

	private types: Map<string, Component> = new Map();

	public registerType( typeName: string, component: Component ): void {
		this.types.set( typeName, component );
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

}
