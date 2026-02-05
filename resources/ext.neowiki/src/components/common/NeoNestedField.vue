<template>
	<CdxField
		class="ext-neowiki-nested-field"
		:is-fieldset="true"
		:optional="optional"
	>
		<template #label>
			<slot name="label" />
		</template>

		<div class="ext-neowiki-nested-field__inputs">
			<slot />
		</div>
	</CdxField>
</template>

<script setup lang="ts">
import { CdxField } from '@wikimedia/codex';

withDefaults( defineProps<{
	optional?: boolean;
}>(), {
	optional: false
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-nested-field {
	&__inputs {
		display: flex;
		flex-direction: column;
		gap: @spacing-50;

		@media screen and ( min-width: @min-width-breakpoint-desktop ) {
			flex-direction: row;
		}

		// Disable margin-top on all nested fields.
		.cdx-field {
			margin-top: 0;
		}
	}
}
</style>
