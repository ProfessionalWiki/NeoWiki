import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { type SelectOption, type SelectProperty, SelectType } from '@/domain/propertyTypes/Select.ts';

/**
 * The message naming what is wrong with each option, by option id; an option with nothing wrong has no
 * entry. Every option needs a label, and no two labels may differ only in letter case or surrounding
 * whitespace. The wiki stores a property that breaks this, then drops it when reading it back.
 */
export function selectOptionProblems( options: readonly SelectOption[] ): Map<string, string> {
	const labels = options.map( ( option ) => comparableLabel( option.label ) );
	const problems = new Map<string, string>();

	options.forEach( ( option, index ) => {
		if ( labels[ index ] === '' ) {
			problems.set( option.id, 'neowiki-property-editor-option-label-required' );
		} else if ( labels.indexOf( labels[ index ] ) !== labels.lastIndexOf( labels[ index ] ) ) {
			problems.set( option.id, 'neowiki-property-editor-options-unique' );
		}
	} );

	return problems;
}

function comparableLabel( label: string ): string {
	return label.trim().toLowerCase();
}

/**
 * The message naming what is wrong with the first faulty option of a select property, or null.
 */
export function invalidSelectOption( property: PropertyDefinition ): string | null {
	if ( property.type !== SelectType.typeName ) {
		return null;
	}

	return [ ...selectOptionProblems( ( property as SelectProperty ).options ).values() ][ 0 ] ?? null;
}
