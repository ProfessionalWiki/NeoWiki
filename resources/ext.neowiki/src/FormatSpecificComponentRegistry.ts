import { Component } from 'vue';

interface Components {
	valueDisplayComponent: Component;
	valueEditingComponent: Component;
}

export class FormatSpecificComponentRegistry {
	private componentMap: Map<string, Components> = new Map();

	public registerComponents( valueFormat: string, valueDisplayComponent: Component, valueEditingComponent: Component ): void {
		this.componentMap.set( valueFormat, { valueDisplayComponent, valueEditingComponent } );
	}

	public getValueDisplayComponent( formatName: string ): Component {
		if ( !this.componentMap.has( formatName ) ) {
			throw new Error( `No value display component registered for format: ${ formatName }` );
		}
		return this.componentMap.get( formatName )!.valueDisplayComponent;
	}

	public getValueEditingComponent( formatName: string ): Component {
		if ( !this.componentMap.has( formatName ) ) {
			throw new Error( `No value editing component registered for format: ${ formatName }` );
		}
		return this.componentMap.get( formatName )!.valueEditingComponent;
	}
}
