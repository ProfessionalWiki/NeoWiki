import { Component } from 'vue';

interface Components {
	valueDisplayComponent: Component;
	valueEditingComponent: Component;
	label: string;
	icon: string;
}

export class FormatSpecificComponentRegistry {

	private componentMap: Map<string, Components> = new Map();

	public registerComponents(
		valueFormat: string,
		valueDisplayComponent: Component,
		valueEditingComponent: Component,
		label: string,
		icon: string
	): void {
		this.componentMap.set( valueFormat, { valueDisplayComponent, valueEditingComponent, label, icon } );
	}

	public getValueDisplayComponent( formatName: string ): Component {
		if ( !this.componentMap.has( formatName ) ) {
			throw new Error( `No value display component registered for format: ${ formatName }` );
		}
		return this.componentMap.get( formatName )!.valueDisplayComponent;
	}

	/**
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueEditingComponent( formatName: string ): Component {
		if ( !this.componentMap.has( formatName ) ) {
			throw new Error( `No value editing component registered for format: ${ formatName }` );
		}
		return this.componentMap.get( formatName )!.valueEditingComponent;
	}

	public getValueFormats(): string[] {
		return Array.from( this.componentMap.keys() );
	}

	public getLabelsAndIcons(): { value: string; label: string; icon: string }[] {
		return Array.from( this.componentMap.entries() )
			.map( ( [ value, { label, icon } ] ) => ( {
				value: value,
				label: label,
				icon: icon
			} ) );
	}

}
