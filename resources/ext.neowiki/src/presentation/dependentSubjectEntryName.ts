import type { Subject } from '@/domain/Subject';
import type { Schema } from '@/domain/Schema';
import { resolveAllNonEmptyProperties } from '@/domain/resolveDisplayProperties';
import { valueText } from '@/presentation/valueText';

/**
 * Tells apart Dependent Subjects held by the same property, which share a name: the property, then the first field, in
 * the Schema's order, that reads as text. "Attendance figures, 2023".
 */
export function dependentSubjectEntryName( holderName: string, subject: Subject, schema: Schema | undefined ): string {
	const field = ( schema === undefined ? [] : resolveAllNonEmptyProperties( schema, subject ) )
		.map( ( { propertyDefinition, value } ) => valueText( propertyDefinition, value ) )
		.find( ( text ) => text !== null ) ?? null;

	return field === null ? holderName : `${ holderName }, ${ field }`;
}
