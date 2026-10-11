<template>
	<CdxField
		ref="fieldRef"
		:is-fieldset="true"
		:disabled="props.disabled"
	>
		<template #label>
			{{ props.label }}
		</template>
		<template #description>
			<slot name="description" />
		</template>
		<CdxRadio
			v-for="( choice, index ) in props.choices"
			:key="index"
			:model-value="props.modelValue"
			:input-value="choice.value"
			:name="name"
			@update:model-value="( value: ConfigChoice[ 'value' ] ) => emit( 'update:modelValue', value )"
		>
			{{ choice.label }}
			<template #description>
				{{ choice.help }}
			</template>
		</CdxRadio>
	</CdxField>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxField, CdxRadio, useGeneratedId } from '@wikimedia/codex';
import type { ConfigChoice } from '@/components/ConfigEditor/configEditing.ts';

const props = defineProps<{
	modelValue: ConfigChoice[ 'value' ];
	label: string;
	disabled: boolean;
	choices: readonly ConfigChoice[];
}>();

const emit = defineEmits<{
	'update:modelValue': [ value: ConfigChoice[ 'value' ] ];
}>();

defineSlots<{
	description(): unknown;
}>();

const name = useGeneratedId( 'ext-neowiki-choice' );

const fieldRef = ref<InstanceType<typeof CdxField> | null>( null );

function focus(): void {
	( fieldRef.value?.$el as HTMLElement | undefined )?.querySelector<HTMLInputElement>( 'input:checked' )?.focus();
}

defineExpose( { focus } );
</script>
