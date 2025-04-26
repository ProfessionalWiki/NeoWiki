<template>
	<div class="ext-neowiki-multi-text-inputs">
		<div
			v-for="( inputValue, index ) in internalValues"
			:key="index"
		>
			<CdxTextInput
				:model-value="inputValue"
				:aria-label="`${props.label} item ${index + 1}`"
				:status="props.invalidValues?.has( inputValue ) ? 'error' : 'default'"
				@update:model-value="( newValue ) => onInput( index, newValue )"
			/>
			<CdxMessage
				v-if="props.invalidValues?.has( inputValue )"
				type="error"
				inline
			>
				<!-- TODO: Get validation message from property type instead of hardcoding -->
				Invalid format
			</CdxMessage>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxTextInput, CdxMessage } from '@wikimedia/codex';

// Define Props
interface MultiTextInputProps {
	modelValue?: string[];
	label: string;
	invalidValues?: Set<string>;
}

const props = withDefaults(
	defineProps<MultiTextInputProps>(),
	{
		modelValue: () => [ '' ],
		label: '',
		invalidValues: () => new Set()
	}
);

// Define Emits
type UpdateModelValueEmit = ( e: 'update:modelValue', value: string[] ) => void;

const emit = defineEmits<UpdateModelValueEmit>();

// Internal state
const internalValues = ref<string[]>( [ ...props.modelValue ] );

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

</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-multi-text-inputs {
	display: flex;
	flex-direction: column;
	gap: $spacing-25;
}
</style>
