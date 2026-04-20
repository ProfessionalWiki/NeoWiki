<template>
	<CdxDialog
		class="ext-neowiki-page-subjects-dialog"
		:open="store.pageSubjectsOpen"
		:title="$i18n( 'neowiki-page-subjects-dialog-title', pageName ).text()"
		:use-close-button="true"
		@update:open="onUpdateOpen"
	>
		<CdxTable
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-page-subjects-dialog-title', pageName ).text()"
			:hide-caption="true"
			:pending="loading"
		>
			<template #item-label="{ item, row }">
				{{ item }} <CdxInfoChip
					v-if="row.isMain"
					:aria-label="$i18n( 'neowiki-page-subjects-main-badge-aria' ).text()"
				>
					{{ $i18n( 'neowiki-page-subjects-main-badge' ).text() }}
				</CdxInfoChip>
			</template>

			<template #item-id="{ item }">
				<code>{{ item }}</code>
			</template>

			<template #item-schema="{ item }">
				<a :href="schemaUrl( item )">{{ item }}</a>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-page-subjects-empty' ).text() }}
			</template>
		</CdxTable>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxDialog, CdxInfoChip, CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

interface PageSubjectSummary {
	id: string;
	label: string;
	schema: string;
	isMain: boolean;
}

const store = useSubjectStore();
const loading = ref( false );
const rows = ref<PageSubjectSummary[]>( [] );
const pageName = String( mw.config.get( 'wgTitle' ) ?? '' );

const columns: TableColumn[] = [
	{ id: 'label', label: mw.msg( 'neowiki-page-subjects-column-label' ) },
	{ id: 'id', label: mw.msg( 'neowiki-page-subjects-column-id' ) },
	{ id: 'schema', label: mw.msg( 'neowiki-page-subjects-column-schema' ) }
];

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

async function fetchSubjects(): Promise<void> {
	loading.value = true;
	const pageId = mw.config.get( 'wgArticleId' ) as number;
	const ext = NeoWikiExtension.getInstance();
	const restApiUrl = ext.getMediaWiki().util.wikiScript( 'rest' );

	try {
		const response = await ext.newHttpClient().get( `${ restApiUrl }/neowiki/v0/page/${ pageId }/subjects?limit=50` );
		if ( !response.ok ) {
			rows.value = [];
			mw.notify(
				mw.msg( 'neowiki-page-subjects-fetch-error' ),
				{ type: 'error' }
			);
			return;
		}
		const data: { subjects: PageSubjectSummary[]; totalRows: number } = await response.json();
		rows.value = data.subjects;
	} catch ( _error ) {
		rows.value = [];
		mw.notify(
			mw.msg( 'neowiki-page-subjects-fetch-error' ),
			{ type: 'error' }
		);
	} finally {
		loading.value = false;
	}
}

function onUpdateOpen( value: boolean ): void {
	if ( !value ) {
		store.closePageSubjects();
		rows.value = [];
	}
}

watch( () => store.pageSubjectsOpen, ( open ) => {
	if ( open ) {
		fetchSubjects();
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-page-subjects-dialog {
	code {
		font-family: @font-family-monospace;
	}
}
</style>
