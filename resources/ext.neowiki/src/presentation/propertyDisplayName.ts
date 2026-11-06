import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { labelForReader } from '@/presentation/languages';

/**
 * What the UI calls the property: its label in the first language the reader reads that it has a label in, or else
 * its name.
 */
export function propertyDisplayName( property: PropertyDefinition ): string {
	return labelForReader( property.labels ) ?? property.name.toString();
}
