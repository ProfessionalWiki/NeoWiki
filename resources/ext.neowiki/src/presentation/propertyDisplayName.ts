import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { readerLanguageTags } from '@/presentation/languages';

/**
 * What the UI calls the property: its label in the first language the reader reads that it has a label in, or else
 * its name.
 */
export function propertyDisplayName( property: PropertyDefinition ): string {
	const labels = property.labels;

	if ( labels === undefined ) {
		return property.name.toString();
	}

	// Own keys only: `?uselang=constructor` would otherwise find the one every object inherits.
	const language = readerLanguageTags().find( ( tag ) => Object.prototype.hasOwnProperty.call( labels, tag ) );

	return language === undefined ? property.name.toString() : labels[ language ];
}
