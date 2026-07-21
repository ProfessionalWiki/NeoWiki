import { ref, watch, computed, ComputedRef, Ref } from 'vue';
import { Icon } from '@wikimedia/codex-icons';
import { ValidationMessages } from '@wikimedia/codex';
import { Value, StringValue } from '@/domain/Value.ts';
import { newStringValue, ValueType } from '@/domain/Value.ts';
import { MultiStringProperty } from '@/domain/PropertyDefinition.ts';
import { PropertyType } from '@/domain/PropertyType.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { ValueInputEmitFunction } from '@/components/Value/ValueInputContract.ts';
import { useServerViolations } from '@/composables/useServerViolations.ts';

interface UseStringValueInputReturn {
	displayValues: ComputedRef<string[]>;
	fieldMessages: Ref<ValidationMessages>;
	inputMessages: Ref<ValidationMessages[]>;
	onInput: ( newValue: string | string[] ) => void;
	getCurrentValue: () => Value | undefined;
	startIcon: Icon | undefined;
}

export function useStringValueInput<P extends MultiStringProperty>(
	modelValue: Ref<Value | undefined>,
	property: Ref<P>,
	emit: ValueInputEmitFunction,
	propertyType: PropertyType,
	serverViolations?: Ref<readonly SubjectViolation[] | undefined>,
): UseStringValueInputReturn {
	const internalValue: Ref<StringValue | undefined> = ref( undefined );
	const userInputValues: Ref<string[] | null> = ref( null );
	const fieldMessages: Ref<ValidationMessages> = ref( {} );
	const inputMessages: Ref<ValidationMessages[]> = ref( [] );

	const startIcon: Icon | undefined = propertyType ?
		NeoWikiServices.getComponentRegistry().getIcon( propertyType.getTypeName() ) :
		undefined;

	function initializeInternalValue( value: Value | undefined ): void {
		if ( value && value.type === ValueType.String ) {
			const stringVal = value as StringValue;
			if ( stringVal.parts.length > 0 && stringVal.parts.some( ( s ) => s !== '' ) ) {
				internalValue.value = stringVal;
			} else {
				internalValue.value = undefined;
			}
		} else {
			internalValue.value = undefined;
		}
	}

	initializeInternalValue( modelValue.value );

	const displayValues: ComputedRef<string[]> = computed<string[]>( () => {
		if ( userInputValues.value !== null ) {
			return userInputValues.value;
		}
		return internalValue.value ? internalValue.value.parts : [];
	} );

	const { relevant, format, emitClears } = useServerViolations(
		property,
		serverViolations ?? ref<readonly SubjectViolation[] | undefined>( undefined ),
		emit,
	);

	function mergeServerIntoInputMessages( baseMessages: ValidationMessages[] ): ValidationMessages[] {
		const merged = [ ...baseMessages ];
		for ( const v of relevant() ) {
			if ( typeof v.valuePartIndex !== 'number' ) {
				continue;
			}
			const i = v.valuePartIndex;
			if ( i < 0 || i >= merged.length ) {
				continue;
			}
			const existing = merged[ i ];
			if ( !existing || Object.keys( existing ).length === 0 ) {
				merged[ i ] = { error: format( v ) };
			}
		}
		return merged;
	}

	function getFieldMessagesForDisplay( errors: ValidationMessages[] ): ValidationMessages {
		// A server-sourced field-level violation (valuePartIndex null) has no
		// per-input slot to attach to, so we surface it through the field-level
		// summary regardless of multi/single — otherwise something like a
		// "required" violation on a multi-value property would silently vanish.
		const fieldLevel = relevant().find(
			( v ) => v.valuePartIndex === null || v.valuePartIndex === undefined,
		);

		if ( property.value.multiple ) {
			// For multi: per-input server violations stay in their slots; only a
			// server-sourced field-level violation surfaces in the summary slot.
			if ( fieldLevel ) {
				return { error: format( fieldLevel ) };
			}
			return {};
		}

		// Single-value: a per-input server violation wins, the field-level
		// violation is the fallback.
		const perInput = errors.find( ( e ) => e && Object.keys( e ).length > 0 );
		if ( perInput ) {
			return perInput;
		}

		if ( fieldLevel ) {
			return { error: format( fieldLevel ) };
		}

		return {};
	}

	function updateValidationMessages( values: string[] ): void {
		const merged = mergeServerIntoInputMessages( values.map( () => ( {} ) ) );
		inputMessages.value = merged;
		fieldMessages.value = getFieldMessagesForDisplay( merged );
	}

	function onInput( newValue: string | string[] ): void {
		const currentInputValues = typeof newValue === 'string' ? [ newValue ] : newValue;
		const previousInputValues = userInputValues.value ?? displayValues.value;
		userInputValues.value = currentInputValues;

		updateValidationMessages( currentInputValues );

		// Optimistically clear the server violations this edit could have resolved:
		// on a multi-value field, the value parts whose input changed plus the
		// shared field-level summary; on a single-value field, only the summary.
		// useServerViolations emits only for indices it actually holds.
		if ( property.value.multiple ) {
			const len = Math.max( currentInputValues.length, previousInputValues.length );
			const changedIndices: number[] = [];
			for ( let i = 0; i < len; i++ ) {
				if ( currentInputValues[ i ] !== previousInputValues[ i ] ) {
					changedIndices.push( i );
				}
			}
			emitClears( changedIndices );
		} else {
			emitClears( [] );
		}

		// Emit every non-empty entry, including ones that are invalid. The
		// backend is the authoritative validator — stripping
		// bad values here would silently drop the user's edit and bypass
		// enforcement (e.g. changing one valid URL to "ftp://..." would be
		// accepted because the proposed StringValue still matches the prior).
		// newStringValue trims whitespace and filters empties.
		const tempValue = newStringValue( ...currentInputValues );
		const newStringValueInstance: StringValue | undefined =
			tempValue.parts.length > 0 ? tempValue : undefined;

		// TODO: Maybe we should have a unified way to handle deep comparison of values
		// https://github.com/ProfessionalWiki/NeoWiki/pull/382#discussion_r2075610162
		if ( JSON.stringify( internalValue.value ) !== JSON.stringify( newStringValueInstance ) ) {
			internalValue.value = newStringValueInstance;
			emit( 'update:modelValue', internalValue.value );
		}
	}

	watch( modelValue, ( newModelValue ) => {
		const previousInternalValue = internalValue.value;
		initializeInternalValue( newModelValue );

		if ( JSON.stringify( previousInternalValue ) !== JSON.stringify( internalValue.value ) ) {
			userInputValues.value = null;
		}

		updateValidationMessages( displayValues.value );
	}, { immediate: true } );

	// Watch for changes in property configuration (e.g., required, uniqueItems)
	watch( property, () => {
		updateValidationMessages( displayValues.value );
	}, { deep: true } );

	// Re-merge when server violations update (e.g. after a failed save).
	if ( serverViolations ) {
		watch( () => serverViolations.value, () => {
			updateValidationMessages( displayValues.value );
		}, { deep: true } );
	}

	const getCurrentValue = (): Value | undefined =>
		internalValue.value;
	return {
		displayValues: displayValues,
		fieldMessages: fieldMessages,
		inputMessages: inputMessages,
		onInput: onInput,
		getCurrentValue: getCurrentValue,
		startIcon: startIcon,
	};
}
