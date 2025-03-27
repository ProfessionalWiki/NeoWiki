<template>
	<BaseMultiStringInput
		v-bind="$props"
		format-name="url"
		input-type="url"
		root-class="neo-url-field"
		:start-icon="cdxIconLink"
		@update:model-value="value => onInput( value )"
	/>
</template>

<script setup lang="ts">
import BaseMultiStringInput from '@/components/Value/BaseMultiStringInput.vue';
import { cdxIconLink } from '@wikimedia/codex-icons';
import { UrlProperty } from '@neo/domain/valueFormats/Url.ts';
import { ValueInputEmits, ValueInputProps } from '@/components/Value/ValueInputContract';
import { newStringValue, Value } from '@neo/domain/Value.ts';

withDefaults(
	defineProps<ValueInputProps<UrlProperty>>(),
	{
		modelValue: () => newStringValue( '' ),
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

function onInput( value: Value | undefined ): void {
	emit( 'update:modelValue', value );
}
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

// Only URL-specific styles
.neo-url-field {
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

		.cdx-text-input {
			&__input {
				padding-left: 36px;
			}

			&__start-icon {
				left: 8px;
				color: $color-subtle;
			}
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
