import { ComputedRef, Ref } from 'vue';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { ValueInputEmitFunction } from '@/components/Value/ValueInputContract.ts';
import { useServerViolations } from '@/composables/useServerViolations.ts';

interface FieldServerViolation {
	validationError: ComputedRef<string | null>;
	clearServerViolation: () => void;
}

/**
 * Field-level server-violation handling for the single-value inputs (Boolean,
 * Number, Date, DateTime). A thin adapter over useServerViolations: these
 * inputs have no per-part slot, so only the field-level violation (valuePartIndex
 * null/undefined) is displayed, and clearing passes an empty touched-index set
 * so exactly that violation is dropped — a per-index violation is neither shown
 * nor cleared here.
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
	const { fieldLevelMessage, emitClears } = useServerViolations( property, serverViolations, emit, formatArg );

	return {
		validationError: fieldLevelMessage,
		clearServerViolation: () => emitClears( [] ),
	};
}
