import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';

export interface UnifiedRow {
	property: PropertyDefinition;
	shown: boolean;
}

export function unifiedRows(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
): UnifiedRow[] {
	if ( displayRules.length === 0 ) {
		return schemaProperties.map( ( property ) => ( { property, shown: true } ) );
	}

	const propertiesByName = new Map(
		schemaProperties.map( ( property ) => [ property.name.toString(), property ] ),
	);
	const shownNames = new Set( displayRules.map( ( rule ) => rule.property.toString() ) );

	const shown = displayRules
		.map( ( rule ) => propertiesByName.get( rule.property.toString() ) )
		.filter( ( property ): property is PropertyDefinition => property !== undefined )
		.map( ( property ): UnifiedRow => ( { property, shown: true } ) );

	const hidden = schemaProperties
		.filter( ( property ) => !shownNames.has( property.name.toString() ) )
		.map( ( property ): UnifiedRow => ( { property, shown: false } ) );

	return [ ...shown, ...hidden ];
}

export function rulesAfterToggle(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
	name: string,
): DisplayRule[] {
	const rows = unifiedRows( schemaProperties, displayRules ).map(
		( row ) => ( row.property.name.toString() === name ? { ...row, shown: !row.shown } : row ),
	);

	return rulesFromRows( rows, displayRules );
}

export function rulesAfterShowingAll(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
): DisplayRule[] {
	const rows = unifiedRows( schemaProperties, displayRules ).map(
		( row ) => ( { ...row, shown: true } ),
	);

	return rulesFromRows( rows, displayRules );
}

export function rulesAfterReorder(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
	oldIndex: number,
	newIndex: number,
): DisplayRule[] {
	const rows = unifiedRows( schemaProperties, displayRules );
	const [ moved ] = rows.splice( oldIndex, 1 );
	rows.splice( newIndex, 0, moved );

	return rulesFromRows( rows, displayRules );
}

function rulesFromRows( rows: UnifiedRow[], displayRules: DisplayRule[] ): DisplayRule[] {
	const rulesByName = new Map(
		displayRules.map( ( rule ) => [ rule.property.toString(), rule ] ),
	);

	return rows
		.filter( ( row ) => row.shown )
		.map( ( row ) => rulesByName.get( row.property.name.toString() ) ?? { property: row.property.name } );
}
