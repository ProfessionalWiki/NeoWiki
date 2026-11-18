import type { Layout } from '@/domain/Layout';

export class LayoutSerializer {

	public serializeLayout( layout: Layout ): string {
		return JSON.stringify( this.layoutToJson( layout ), null, '\t' );
	}

	public layoutToJson( layout: Layout ): Record<string, unknown> {
		const data: Record<string, unknown> = {
			schema: layout.getSchema(),
			type: layout.getType(),
		};

		if ( layout.getDescription() ) {
			data.description = layout.getDescription();
		}

		const displayRules = layout.getDisplayRules();
		if ( displayRules.length > 0 ) {
			data.displayRules = displayRules.map( ( rule ) => {
				const entry: Record<string, unknown> = {
					property: rule.property.toString(),
				};
				if ( rule.displayAttributes && Object.keys( rule.displayAttributes ).length > 0 ) {
					entry.displayAttributes = rule.displayAttributes;
				}
				return entry;
			} );
		}

		const settings = layout.getSettings();
		if ( Object.keys( settings ).length > 0 ) {
			data.settings = settings;
		}

		return data;
	}

}
