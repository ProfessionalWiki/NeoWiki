<template>
	<div
		v-if="item.status !== 'conflict' && item.versionHere !== null && item.versionUpstream !== null"
		class="ext-neowiki-item-changes"
	>
		<CdxButton
			weight="quiet"
			:aria-expanded="shown"
			@click="shown = !shown"
		>
			{{ $i18n( shown ? 'neowiki-bundle-preview-hide-changes' : 'neowiki-bundle-preview-show-changes' ).text() }}
		</CdxButton>
		<VersionDiff
			v-if="shown"
			:here="item.versionHere"
			:upstream="item.versionUpstream"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import type { ImportItem } from '../application/importPlan.ts';
import VersionDiff from './VersionDiff.vue';

defineProps<{
	item: ImportItem;
}>();

const shown = ref( false );
</script>
