<template>
	<div class="ext-neowiki-multi-text-inputs">
		<div
			v-for="( inputValue, index ) in internalValues"
			:key="index"
		>
			<CdxTextInput
				:model-value="inputValue"
				:start-icon="props.startIcon"
				:aria-label="`${props.label} item ${index + 1}`"
				:status="getStatusForIndex( index )"
				@update:model-value="( newValue ) => onInput( index, newValue )"
				@blur="() => onBlur( index )"
			/>
			<!-- Can't use CdxField because we need to show validation message under the input field -->
			<CdxMessage
				v-if="getMessageTextForIndex( index ) && touchedInputs.has( index )"
				:type="getMessageTypeForIndex( index )"
				inline
			>
				{{ getMessageTextForIndex( index ) }}
			</CdxMessage>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxTextInput, CdxMessage, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import type { Icon } from '@wikimedia/codex-icons';

// Define allowed message types locally (matching CdxMessage)
// Because ValidationMessagesType is not exported, and ValidationStatusType does not match exactly
type ValidationMessageType = 'error' | 'warning' | 'success' | 'notice';

// TODO: Should we move this somewhere else?
// This is different from the ValueInputProps because this is only used for presentation
interface MultiTextInputProps {
	modelValue?: string[];
	// This is only used for aria-label
	label: string;
	startIcon?: Icon;
	messages?: ValidationMessages[];
}

const props = withDefaults(
	defineProps<MultiTextInputProps>(),
	{
		modelValue: () => [ '' ],
		label: '',
		startIcon: undefined,
		messages: () => []
	}
);

type UpdateModelValueEmit = ( e: 'update:modelValue', value: string[] ) => void;

const emit = defineEmits<UpdateModelValueEmit>();

const internalValues = ref<string[]>( [ ...props.modelValue ] );
const touchedInputs = ref<Set<number>>( new Set() );

function getMessageForIndex( index: number ): ValidationMessages | undefined {
	return props.messages?.[ index ];
}

function getStatusForIndex( index: number ): ValidationStatusType | 'default' {
	const messages = getMessageForIndex( index );
	if ( !messages ) {
		return 'default';
	}
	return Object.keys( messages )[ 0 ] as keyof ValidationMessages;
}

function getMessageTypeForIndex( index: number ): ValidationMessageType | undefined {
	const messages = getMessageForIndex( index );
	if ( !messages ) {
		return undefined;
	}
	return Object.keys( messages )[ 0 ] as ValidationMessageType;
}

function getMessageTextForIndex( index: number ): string | undefined {
	const messages = getMessageForIndex( index );
	if ( !messages ) {
		return undefined;
	}
	const keys = Object.keys( messages ) as ( keyof ValidationMessages )[];
	return keys.length > 0 ? messages[ keys[ 0 ] ] : undefined;
}

// Ensure there's always at least one input, preferably an empty one at the end
watch( internalValues, ( newValues, _oldValues ) => { // Ignore oldValues for emit logic

	const lastValue = newValues[ newValues.length - 1 ];
	const secondLastValue = newValues.length > 1 ? newValues[ newValues.length - 2 ] : undefined;

	// Filter out the final empty string if it exists and is not the only item
	const valuesToEmit = newValues.filter( ( value, index ) =>
		!( index === newValues.length - 1 && value === '' && newValues.length > 1 )
	);

	emit( 'update:modelValue', valuesToEmit );

	if ( lastValue !== '' ) {
		internalValues.value.push( '' );
	}

	// Remove the last empty input if the second-to-last one also becomes empty
	if (
		newValues.length > 1 &&
		lastValue === '' &&
		secondLastValue === ''
	) {
		internalValues.value.pop();
	}

}, { deep: true } );

// Initial check in case modelValue starts empty or invalid
if ( internalValues.value.length === 0 || internalValues.value[ internalValues.value.length - 1 ] !== '' ) {
	internalValues.value.push( '' );
}

function onInput( index: number, newValue: string ): void {
	internalValues.value[ index ] = newValue;
}

function onBlur( index: number ): void {
	touchedInputs.value.add( index );
}

</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-multi-text-inputs {
	display: flex;
	flex-direction: column;
	gap: $spacing-25;
}
</style>
