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
		// Wide enough that a bound's severity control, at the end of its label row, does not
		// crowd the next bound's label.
		gap: @spacing-100;

		// The viewport is only a guess at how much room this row has: it sits in a pane whose
		// width the reader sets. Wrapping is what makes the guess safe — a row that no longer
		// fits stacks instead of pushing its labels out of the pane.
		@media screen and ( min-width: @min-width-breakpoint-desktop ) {
			flex-flow: row wrap;
		}

		// Disable margin-top on all nested fields.
		.cdx-field {
			margin-top: 0;
		}
	}
}
</style>
