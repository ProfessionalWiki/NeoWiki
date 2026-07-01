<template>
	<CdxField :is-fieldset="false" :disabled="true">
		<template #label>
			{{ props.label }}
		</template>
		<div class="ext-neowiki-unknown-value-input">
			<span class="ext-neowiki-unknown-value-input__raw">{{ rawText }}</span>
			<span class="ext-neowiki-unknown-value-input__note">{{ note }}</span>
		</div>
	</CdxField>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { ValueType, type Value } from '@/domain/Value.ts';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { formatRawValue } from '@/presentation/formatRawValue.ts';

const props = withDefaults(
	defineProps<ValueInputProps<PropertyDefinition>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

// Declared for consistency with the other value inputs and so the bindings in
// SubjectEditor do not fall through as attributes. This input is read-only and
// never emits.
defineEmits<ValueInputEmits>();

const unknownValue = computed( () =>
	props.modelValue?.type === ValueType.Unknown ? props.modelValue : undefined );

const typeName = computed( (): string => unknownValue.value?.typeName ?? props.property.type );

const rawText = computed( (): string => formatRawValue( unknownValue.value?.raw ) );

const note = computed( (): string => mw.msg( 'neowiki-property-type-unknown-input-note', typeName.value ) );

// An unknown type cannot be interpreted, so the stored value is preserved verbatim
// and returned unchanged on save rather than risking data loss through a generic input.
defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return props.modelValue;
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-unknown-value-input {
	&__raw {
		overflow-wrap: anywhere;
		word-break: break-word;
	}

	&__note {
		display: block;
		margin-top: @spacing-25;
		color: @color-subtle;
		font-size: @font-size-small;
	}
}
</style>
