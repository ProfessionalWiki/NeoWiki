<template>
	<div
		v-if="setupOffered || updateCount > 0"
		class="ext-neowiki-model-bundles-notice"
	>
		<CdxCard
			v-if="setupOffered"
			:url="setupUrl"
			:icon="cdxIconPuzzle"
		>
			<template #title>
				{{ $i18n( 'neowiki-bundle-setup-title' ).text() }}
			</template>
			<template #description>
				{{ $i18n( 'neowiki-bundle-setup-card-description' ).text() }}
			</template>
		</CdxCard>

		<CdxMessage v-if="updateCount > 0">
			<a :href="dataModelUrl">{{ $i18n( 'neowiki-bundle-updates-available', updateCount ).text() }}</a>
		</CdxMessage>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { CdxCard, CdxMessage } from '@wikimedia/codex';
import { cdxIconPuzzle } from '@wikimedia/codex-icons';
import { ModelBundleServices } from '../ModelBundleServices.ts';
import { otherVersionAtSource } from '../persistence/sourceVersion.ts';

const recordRepository = ModelBundleServices.getInstallRecordRepository();

// Set by the page for a user who may install model bundles, while the wiki has no Schemas and no installed bundle.
const setupOffered = mw.config.get( 'wgNeoWikiModelBundlesSetupOffered' ) === true;
const setupUrl = mw.util.getUrl( 'Special:DataModel/setup' );
const dataModelUrl = mw.util.getUrl( 'Special:DataModel' );
const updateCount = ref( 0 );

onMounted( async () => {
	try {
		const records = await recordRepository.getInstallRecords();
		const versions = await Promise.all( records.map( otherVersionAtSource ) );
		updateCount.value = versions.filter( ( version ) => version !== null ).length;
	} catch ( _error ) {
		updateCount.value = 0;
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-model-bundles-notice {
	display: flex;
	flex-direction: column;
	gap: @spacing-75;
	margin-bottom: @spacing-150;
}
</style>
