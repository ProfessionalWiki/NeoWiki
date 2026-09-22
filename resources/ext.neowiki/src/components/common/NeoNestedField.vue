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
		// This row sits in a pane whose width the reader sets, so it wraps on the room it
		// actually has rather than on the viewport: the fields share a row while they fit and
		// stack once they no longer do, instead of pushing their labels out of the pane.
		display: flex;
		flex-flow: row wrap;
		// Wide enough that a bound's severity control, at the end of its label row, does not
		// crowd the next bound's label.
		gap: @spacing-100;

		// A basis small enough that two fields share the narrowest pane they are shown in, and a
		// lone field grows to fill the row anyway. Both minimums go because Codex asks 256px for
		// a text input, which alone would wrap the second field.
		.cdx-field {
			flex: 1 1 @size-800;
			min-width: 0;
			margin-top: 0;
		}

		.cdx-text-input {
			min-width: 0;
		}
	}
}
</style>
