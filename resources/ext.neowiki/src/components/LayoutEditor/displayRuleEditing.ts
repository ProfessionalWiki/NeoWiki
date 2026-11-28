import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { withItemMoved } from '@/components/common/reordering.ts';

export interface UnifiedRow {
	property: PropertyDefinition;
	shown: boolean;
}

/**
 * The Display Rules a View follows: the Layout's own, or for a Layout without any, one for every property but those
 * left out.
 */
export function rulesInEffect(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
	leftOut: PropertyName[],
): DisplayRule[] {
	if ( displayRules.length > 0 ) {
		return displayRules;
	}

	const leftOutNames = leftOut.map( ( name ) => name.toString() );

	return schemaProperties
		.filter( ( property ) => !leftOutNames.includes( property.name.toString() ) )
		.map( ( property ) => ( { property: property.name } ) );
}

/** What a Display Attributes editor's patch leaves: what it sets, without what it drops, and the rest as it was. */
export function attributesAfterPatch(
	attributes: Record<string, unknown>,
	patch: Record<string, unknown>,
): Record<string, unknown> {
	return Object.fromEntries( Object.entries( { ...attributes, ...patch } ).filter( ( [ , value ] ) => value !== undefined ) );
}

export function rulesAfterSettingDisplayAttributes(
	displayRules: DisplayRule[],
	name: string,
	displayAttributes: Record<string, unknown>,
): DisplayRule[] {
	return displayRules.map( ( rule ): DisplayRule => {
		if ( rule.property.toString() !== name ) {
			return rule;
		}

		return Object.keys( displayAttributes ).length === 0 ?
			{ property: rule.property } :
			{ property: rule.property, displayAttributes };
	} );
}

export function unifiedRows(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
): UnifiedRow[] {
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
	const rows = withItemMoved( unifiedRows( schemaProperties, displayRules ), oldIndex, newIndex );

	return rulesFromRows( rows, displayRules );
}

export function rulesAfterShowingAt(
	schemaProperties: PropertyDefinition[],
	displayRules: DisplayRule[],
	name: string,
	index: number,
): DisplayRule[] {
	const rows = unifiedRows( schemaProperties, displayRules ).filter( ( row ) => row.property.name.toString() !== name );
	const property = schemaProperties.find( ( candidate ) => candidate.name.toString() === name );

	if ( property !== undefined ) {
		rows.splice( index, 0, { property, shown: true } );
	}

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
