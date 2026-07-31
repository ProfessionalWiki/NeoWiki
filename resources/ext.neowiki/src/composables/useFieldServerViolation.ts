import { computed, ComputedRef, Ref } from 'vue';
import { ValidationMessages } from '@wikimedia/codex';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { ValueInputEmitFunction } from '@/components/Value/ValueInputContract.ts';
import { useServerViolations, violationStatus } from '@/composables/useServerViolations.ts';

interface FieldServerViolation {
	validationMessages: ComputedRef<ValidationMessages>;
	validationStatus: ComputedRef<'default' | 'error' | 'warning'>;
	clearServerViolation: () => void;
}

/**
 * Field-level server-violation handling for the single-value inputs (Boolean,
 * Number, Date, DateTime). A thin adapter over useServerViolations: these
 * inputs have no per-part slot, so only the field-level violation (valuePartIndex
 * null/undefined) is displayed — keyed by its severity, with the matching Codex
 * status — and clearing passes an empty touched-index set so exactly that
 * violation is dropped. A per-index violation is neither shown nor cleared here.
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
	const { fieldLevelMessages, emitClears } = useServerViolations( property, serverViolations, emit, formatArg );

	return {
		validationMessages: fieldLevelMessages,
		validationStatus: computed( () => violationStatus( fieldLevelMessages.value ) ),
		clearServerViolation: () => emitClears( [] ),
	};
}
