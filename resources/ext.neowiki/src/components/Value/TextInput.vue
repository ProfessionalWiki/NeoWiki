<template>
	<BaseMultiStringInput
		v-bind="$props"
		format-name="text"
		input-type="text"
		root-class="neo-text-field"
	/>
</template>

<script setup lang="ts">
import BaseMultiStringInput from '@/components/Value/BaseMultiStringInput.vue';
import { TextProperty } from '@neo/domain/valueFormats/Text.ts';
import { ValueInputProps } from '@/components/Value/ValueInputContract';
import { newStringValue } from '@neo/domain/Value.ts';

withDefaults(
	defineProps<ValueInputProps<TextProperty>>(),
	{
		modelValue: () => newStringValue( '' ),
		label: ''
	}
);
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

// Only text-specific styles
.neo-text-field {
	label {
		font-weight: bold;
	}

	&__input-wrapper {
		display: flex;
		align-items: center;
		margin-bottom: 8px;

		.cdx-field {
			flex: 0 0 100%;
		}
	}

	&__add-button {
		float: right;
		margin-top: 8px;

		.cdx-icon {
			color: $color-success;
		}
	}
}
</style>
