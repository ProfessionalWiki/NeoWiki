<template>
	<CdxField
		class="ext-neowiki-subject-creator-page-title-field"
		:status="error === null ? 'default' : 'error'"
		:messages="error === null ? {} : { error }"
	>
		<CdxTextInput
			ref="inputRef"
			:model-value="modelValue"
			:disabled="disabled"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
		<template #label>
			{{ $i18n( 'neowiki-subject-creator-page-title-field' ).text() }}
		</template>
	</CdxField>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';

defineProps<{
	modelValue: string;
	/** What was refused about the title, shown at the field. */
	error: string | null;
	disabled: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ value: string ];
}>();

const inputRef = ref<InstanceType<typeof CdxTextInput> | null>( null );

defineExpose( {
	focus: (): void => inputRef.value?.focus()
} );
</script>
