<template>
	<CdxCheckbox
		ref="checkboxRef"
		:model-value="props.modelValue"
		:disabled="props.disabled"
		@update:model-value="( checked: boolean ) => emit( 'update:modelValue', checked )"
	>
		{{ props.label }}
		<template #description>
			<slot name="description" />
		</template>
	</CdxCheckbox>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxCheckbox } from '@wikimedia/codex';

const props = defineProps<{
	modelValue: boolean;
	label: string;
	disabled: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ value: boolean ];
}>();

defineSlots<{
	description(): unknown;
}>();

const checkboxRef = ref<InstanceType<typeof CdxCheckbox> | null>( null );

function focus(): void {
	( checkboxRef.value?.$el as HTMLElement | undefined )?.querySelector( 'input' )?.focus();
}

defineExpose( { focus } );
</script>
