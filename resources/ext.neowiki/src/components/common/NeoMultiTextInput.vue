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
	label: string; // This is only used for aria-label
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

internalValues.value = normalizeInputValues( internalValues.value );

watch( internalValues, ( newValues ) => {
	const valuesToEmit = newValues.filter( ( value, index ) =>
		!( index === newValues.length - 1 && value === '' && newValues.length > 1 )
	);
	emit( 'update:modelValue', valuesToEmit );

	const nextInternalValues = normalizeInputValues( newValues );

	if ( !areArraysEqual( internalValues.value, nextInternalValues ) ) {
		internalValues.value = nextInternalValues;
	}
}, { deep: true } );

function onInput( index: number, newValue: string ): void {
	internalValues.value[ index ] = newValue;
}

function onBlur( index: number ): void {
	touchedInputs.value.add( index );
}

function getMessageForIndex( index: number ): ValidationMessages | undefined {
	return props.messages?.[ index ];
}

function getStatusForIndex( index: number ): ValidationStatusType | 'default' {
	const messages = getMessageForIndex( index );
	if ( messages === undefined ) {
		return 'default';
	}
	return Object.keys( messages )[ 0 ] as keyof ValidationMessages;
}

function getMessageTypeForIndex( index: number ): ValidationMessageType | undefined {
	const messages = getMessageForIndex( index );
	if ( messages === undefined ) {
		return undefined;
	}
	return Object.keys( messages )[ 0 ] as ValidationMessageType;
}

function getMessageTextForIndex( index: number ): string | undefined {
	const messages = getMessageForIndex( index );
	if ( messages === undefined ) {
		return undefined;
	}
	const keys = Object.keys( messages ) as ( keyof ValidationMessages )[];
	return keys.length > 0 ? messages[ keys[ 0 ] ] : undefined;
}

function normalizeInputValues( values: string[] ): string[] {
	let normalized = [ ...values ];

	const lastIndex = normalized.length - 1;
	normalized = normalized.filter( ( value, index ) =>
		value !== '' || index === lastIndex
	);

	if ( normalized.length === 0 || normalized[ normalized.length - 1 ] !== '' ) {
		normalized.push( '' );
	}

	return normalized;
}

function areArraysEqual( arr1: string[], arr2: string[] ): boolean {
	if ( arr1.length !== arr2.length ) {
		return false;
	}
	for ( let i = 0; i < arr1.length; i++ ) {
		if ( arr1[ i ] !== arr2[ i ] ) {
			return false;
		}
	}
	return true;
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
