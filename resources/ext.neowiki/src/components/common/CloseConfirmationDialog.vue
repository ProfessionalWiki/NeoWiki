<template>
	<CdxDialog
		:open="open"
		:title="$i18n( 'neowiki-close-confirmation-title' ).text()"
		@update:open="onUpdateOpen"
	>
		{{ $i18n( 'neowiki-close-confirmation-message' ).text() }}

		<template #footer>
			<div class="cdx-dialog__footer__actions">
				<CdxButton
					ref="discardButtonRef"
					weight="primary"
					action="destructive"
					@click="$emit( 'discard' )"
				>
					{{ $i18n( 'neowiki-close-confirmation-discard' ).text() }}
				</CdxButton>
				<CdxButton
					@click="$emit( 'keep-editing' )"
				>
					{{ $i18n( 'neowiki-close-confirmation-keep-editing' ).text() }}
				</CdxButton>
			</div>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import { CdxButton, CdxDialog } from '@wikimedia/codex';

const props = defineProps<{
	open: boolean;
}>();

const emit = defineEmits<{
	'discard': [];
	'keep-editing': [];
}>();

const discardButtonRef = ref<InstanceType<typeof CdxButton> | null>( null );

watch( () => props.open, async ( isOpen ) => {
	if ( isOpen ) {
		await nextTick();
		await nextTick();
		( discardButtonRef.value?.$el as HTMLElement | undefined )?.focus();
	}
} );

function onUpdateOpen( value: boolean ): void {
	if ( !value ) {
		emit( 'keep-editing' );
	}
}
</script>
