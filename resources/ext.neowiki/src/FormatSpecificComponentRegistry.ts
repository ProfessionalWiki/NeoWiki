import type { Component } from 'vue';

export interface FormatSpecificStuff {
	valueDisplayComponent: Component;
	valueEditor: Component;
	attributesEditor: Component;
	label: string;
	icon: string;
}

export class FormatSpecificComponentRegistry {

	private formatMap: Map<string, FormatSpecificStuff> = new Map();

	public registerFormat(
		valueFormat: string,
		stuff: FormatSpecificStuff
	): void {
		this.formatMap.set( valueFormat, stuff );
	}

	/**
	 * Returns a Component following ValueDisplayContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueDisplayComponent( formatName: string ): Component {
		if ( !this.formatMap.has( formatName ) ) {
			throw new Error( `No value display component registered for format: ${ formatName }` );
		}
		return this.formatMap.get( formatName )!.valueDisplayComponent;
	}

	/**
	 * Returns a Component following ValueInputContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueEditingComponent( formatName: string ): Component {
		if ( !this.formatMap.has( formatName ) ) {
			throw new Error( `No value editing component registered for format: ${ formatName }` );
		}
		return this.formatMap.get( formatName )!.valueEditor;
	}

	/**
	 * Returns a Component following AttributesEditorContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getAttributesEditor( formatName: string ): Component {
		if ( !this.formatMap.has( formatName ) ) {
			throw new Error( `No attributes editor component registered for format: ${ formatName }` );
		}
		return this.formatMap.get( formatName )!.attributesEditor;
	}

	public getValueFormats(): string[] {
		return Array.from( this.formatMap.keys() );
	}

	public getLabelsAndIcons(): { value: string; label: string; icon: string }[] {
		return Array.from( this.formatMap.entries() )
			.map( ( [ value, { label, icon } ] ) => ( {
				value: value, // TODO: rename to name or formatName
				label: label,
				icon: icon
			} ) );
	}

}
