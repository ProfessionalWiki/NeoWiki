<template>
	<div class="ext-neowiki-subject-creator-container">
		<CdxButton
			@click="open = true"
		>
			{{ $i18n( 'neowiki-subject-creator-button-label' ).text() }}
		</CdxButton>
		<CdxDialog
			v-model:open="open"
			class="ext-neowiki-subject-creator-dialog"
			:title="$i18n( 'neowiki-subject-creator-title' ).text()"
			:use-close-button="true"
			@default="open = false"
		>
			<p>
				{{ $i18n( 'neowiki-subject-creator-schema-title' ).text() }}
			</p>

			<CdxToggleButtonGroup
				v-model="selectedValue"
				class="ext-neowiki-subject-creator-dialog-schema-options"
				:buttons="buttons"
			/>

			<div v-if="selectedValue === 'existing'">
				TODO:Existing schema UI
			</div>

			<div v-if="selectedValue === 'new'">
				TODO: New schema UI
			</div>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxDialog, CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconSearch, cdxIconAdd } from '@wikimedia/codex-icons';
import type { ButtonGroupItem } from '@wikimedia/codex';

const open = ref( false );
const selectedValue = ref( 'existing' );

const buttons = [
	{
		value: 'existing',
		label: mw.msg( 'neowiki-subject-creator-existing-schema' ),
		icon: cdxIconSearch
	},
	{
		value: 'new',
		label: mw.msg( 'neowiki-subject-creator-new-schema' ),
		icon: cdxIconAdd
	}
] as ButtonGroupItem[];

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-creator-dialog {
	&-schema-options.cdx-toggle-button-group {
		width: inherit;
		display: flex;
		flex-wrap: wrap;

		.cdx-toggle-button {
			flex-grow: 1;
		}
	}
}
</style>
