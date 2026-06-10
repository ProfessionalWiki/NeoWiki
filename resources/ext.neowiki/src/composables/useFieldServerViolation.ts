import { computed, ComputedRef, Ref } from 'vue';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';

interface FieldServerViolation {
	validationError: ComputedRef<string | null>;
	clearServerViolation: () => void;
}

/**
 * Field-level server-violation handling shared by the single-value inputs
 * (Boolean, Number, Date, DateTime). Merges a server-sourced violation for
 * this property into the displayed error — live client-side errors take
 * precedence — and clears it when the user edits the field.
 *
 * Only field-level violations (valuePartIndex null/undefined) are handled;
 * single-value inputs have no per-index slot. Multi-value inputs (Text and Url
 * via useStringValueInput, plus Select and Relation) do their own per-index
 * handling and do not use this.
 *
 * @param propertyName Getter for the field's property name.
 * @param serverViolations Getter for the violations passed to this input.
 * @param liveValidationError The component's live client-side error, if any.
 * @param emitClear Emits clear-server-violation up to the parent.
 * @param formatArg Per-arg formatter; Date/DateTime format their bounds for display.
 */
export function useFieldServerViolation(
	propertyName: () => string,
	serverViolations: () => readonly SubjectViolation[] | undefined,
	liveValidationError: Ref<string | null>,
	emitClear: ( payload: { propertyName: string; valuePartIndex: number | null } ) => void,
	formatArg: ( arg: string ) => string = ( arg ) => arg,
): FieldServerViolation {
	const fieldLevelHit = (): SubjectViolation | undefined =>
		( serverViolations() ?? [] ).find(
			( v ) => v.propertyName === propertyName() &&
				( v.valuePartIndex === null || v.valuePartIndex === undefined ),
		);

	const validationError = computed<string | null>( () => {
		if ( liveValidationError.value !== null ) {
			return liveValidationError.value;
		}
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
			emitClear( { propertyName: propertyName(), valuePartIndex: null } );
		}
	}

	return { validationError, clearServerViolation };
}
