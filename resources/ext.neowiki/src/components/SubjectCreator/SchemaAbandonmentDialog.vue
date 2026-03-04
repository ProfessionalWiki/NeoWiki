<template>
	<CdxDialog
		:open="open"
		:title="$i18n( 'neowiki-schema-abandonment-title' ).text()"
		:use-close-button="true"
		@update:open="onUpdateOpen"
	>
		{{ $i18n( 'neowiki-schema-abandonment-message' ).text() }}

		<template #footer>
			<div class="ext-neowiki-schema-abandonment-dialog__actions">
				<CdxButton
					weight="primary"
					action="destructive"
					@click="$emit( 'abandon' )"
				>
					{{ $i18n( 'neowiki-schema-abandonment-abandon' ).text() }}
				</CdxButton>
				<CdxButton
					@click="$emit( 'save-schema' )"
				>
					{{ $i18n( 'neowiki-schema-abandonment-save-schema' ).text() }}
				</CdxButton>
				<CdxButton
					@click="$emit( 'keep-editing' )"
				>
					{{ $i18n( 'neowiki-schema-abandonment-keep-editing' ).text() }}
				</CdxButton>
			</div>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { CdxButton, CdxDialog } from '@wikimedia/codex';

defineProps<{
	open: boolean;
}>();

const emit = defineEmits<{
	'abandon': [];
	'save-schema': [];
	'keep-editing': [];
}>();

function onUpdateOpen( value: boolean ): void {
	if ( !value ) {
		emit( 'keep-editing' );
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-abandonment-dialog__actions {
	display: flex;
	flex-direction: column;
	gap: @spacing-75;
	width: @size-full;

	.cdx-button {
		max-width: none;
	}
}
</style>
