import { computed, ComputedRef, Ref } from 'vue';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { ValueInputEmitFunction } from '@/components/Value/ValueInputContract.ts';

interface FieldServerViolation {
	validationError: ComputedRef<string | null>;
	clearServerViolation: () => void;
}

/**
 * Field-level server-violation handling shared by the single-value inputs
 * (Boolean, Number, Date, DateTime). Merges a server-sourced violation for
 * this property as the displayed error and clears it when the user edits the
 * field.
 *
 * Only field-level violations (valuePartIndex null/undefined) are handled;
 * single-value inputs have no per-index slot. Text and Url (via
 * useStringValueInput) merge per-index violations into their per-input slots;
 * Select and Relation do their own inline lookup, surfacing the first
 * violation for the property at field level.
 *
 * @param property The field's Property Definition; violations are matched on its name.
 * @param serverViolations The violations passed to this input.
 * @param emit The component's emit function; used for clear-server-violation.
 * @param formatArg Per-arg formatter; Date/DateTime format their bounds for display.
 */
export function useFieldServerViolation<P extends PropertyDefinition>(
	property: Ref<P>,
	serverViolations: Ref<readonly SubjectViolation[] | undefined>,
	emit: ValueInputEmitFunction,
	formatArg: ( arg: string ) => string = ( arg ) => arg,
): FieldServerViolation {
	const fieldLevelHit = (): SubjectViolation | undefined =>
		( serverViolations.value ?? [] ).find(
			( v ) => v.propertyName === property.value.name.toString() &&
				( v.valuePartIndex === null || v.valuePartIndex === undefined ),
		);

	const validationError = computed<string | null>( () => {
		const hit = fieldLevelHit();
		if ( hit ) {
			return mw.message(
				`neowiki-field-${ hit.code }`,
				...( hit.args as string[] ).map( formatArg ),
			).text();
		}
		return null;
	} );

	function clearServerViolation(): void {
		if ( fieldLevelHit() ) {
			emit( 'clear-server-violation', {
				propertyName: property.value.name.toString(),
				valuePartIndex: null,
			} );
		}
	}

	return { validationError, clearServerViolation };
}
