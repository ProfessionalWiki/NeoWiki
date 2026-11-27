import { type PropertyDefinition, SEVERITY_SURVIVES_CLEARING } from '@/domain/PropertyDefinition';
import type { Severity } from '@/domain/Severity';
import { type DateProperty, DateType, minPrecisionOf } from '@/domain/propertyTypes/Date';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { RelationType } from '@/domain/propertyTypes/Relation';
import { SelectType } from '@/domain/propertyTypes/Select';

interface ConstraintFields {
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;
	readonly uniqueLanguages?: boolean;
	readonly minimum?: unknown;
	readonly maximum?: unknown;
	readonly minLength?: number;
	readonly maxLength?: number;
	readonly options?: readonly unknown[];
}

/** Only these check `multiple`; elsewhere a single value is no Constraint. */
const SINGLE_VALUE_TYPES = [ SelectType.typeName, RelationType.typeName, MonolingualTextType.typeName ];

/**
 * The names of the Constraints active in the definition: those a value can violate. Constraints are known by the names
 * NeoWiki's own Property Types give them.
 */
export function activeConstraints( property: PropertyDefinition ): string[] {
	const fields = property as PropertyDefinition & ConstraintFields;
	const active: Record<string, boolean> = {
		required: property.required,
		multiple: SINGLE_VALUE_TYPES.includes( property.type ) && fields.multiple !== true,
		uniqueItems: fields.uniqueItems === true,
		uniqueLanguages: fields.multiple === true && fields.uniqueLanguages === true,
		minimum: fields.minimum !== undefined,
		maximum: fields.maximum !== undefined,
		minLength: fields.minLength !== undefined,
		maxLength: fields.maxLength !== undefined,
		options: ( fields.options?.length ?? 0 ) > 0,
		minPrecision: property.type === DateType.typeName && minPrecisionOf( property as DateProperty ) !== 'year',
	};

	return Object.keys( active ).filter( ( constraint ) => active[ constraint ] );
}

/**
 * The definition as the Schema editor leaves it after a change, on a wiki that gives a Constraint `initialSeverity`
 * when it is set: each Constraint the change makes active, as against `activeConstraintsBefore`, starts at it, unless
 * the Constraint has a severity already. A Constraint the change switches off in a way its severity survives keeps the
 * severity it has, warning included, so that it comes back at that.
 */
export function withInitialSeverities<T extends PropertyDefinition>(
	property: T,
	activeConstraintsBefore: readonly string[],
	initialSeverity: Severity,
): T {
	// A Constraint without a severity has this one already.
	if ( initialSeverity === 'warning' ) {
		return property;
	}

	const active = activeConstraints( property );
	const severities: Record<string, Severity> = { ...property.constraintSeverities };

	for ( const constraint of active.filter( ( name ) => !activeConstraintsBefore.includes( name ) ) ) {
		severities[ constraint ] ??= initialSeverity;
	}

	for ( const constraint of activeConstraintsBefore.filter( ( name ) => !active.includes( name ) ) ) {
		if ( severitySurvivesSwitchingOff( property, constraint ) ) {
			severities[ constraint ] ??= 'warning';
		}
	}

	return {
		...property,
		...fullDateWrittenOut( property, severities ),
		constraintSeverities: Object.keys( severities ).length > 0 ? severities : undefined,
	};
}

/**
 * See withoutSeveritiesOfClearedConstraints: an emptied bound keeps its severity too.
 */
function severitySurvivesSwitchingOff( property: PropertyDefinition, constraint: string ): boolean {
	return SEVERITY_SURVIVES_CLEARING.includes( constraint ) ||
		( property as unknown as Record<string, unknown> )[ constraint ] === undefined;
}

/**
 * A severity is saved only on a Constraint the Schema holds, so the full date a date property asks for by default is
 * written out once it has one.
 */
function fullDateWrittenOut( property: PropertyDefinition, severities: Record<string, Severity> ): Partial<DateProperty> {
	if ( property.type !== DateType.typeName || severities.minPrecision === undefined ) {
		return {};
	}

	return { minPrecision: minPrecisionOf( property as DateProperty ) };
}
