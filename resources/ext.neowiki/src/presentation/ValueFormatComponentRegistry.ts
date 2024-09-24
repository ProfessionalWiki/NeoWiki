import { ValueFormatComponent } from '@/presentation/ValueFormatComponent';

export class ValueFormatComponentRegistry {

	private componentMap: Map<string, ValueFormatComponent> = new Map();

	public registerComponent( component: ValueFormatComponent ): void {
		this.componentMap.set( component.getFormatName(), component );
	}

	public getComponent( formatName: string ): ValueFormatComponent {
		const component = this.componentMap.get( formatName );

		if ( component === undefined ) {
			throw new Error( 'Unknown value format: ' + formatName );
		}

		return component;
	}

}
