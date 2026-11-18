<template>
	<section class="ext-neowiki-bundle-group">
		<div class="ext-neowiki-bundle-group__header">
			<CdxButton
				weight="quiet"
				:aria-label="$i18n( expanded ? 'neowiki-bundle-group-collapse' : 'neowiki-bundle-group-expand', title ).text()"
				:aria-expanded="expanded"
				@click="toggle"
			>
				<CdxIcon :icon="expanded ? cdxIconCollapse : cdxIconExpand" />
			</CdxButton>

			<div class="ext-neowiki-bundle-group__title">
				<h3 class="ext-neowiki-bundle-group__name">
					{{ title }}
				</h3>
				<span
					v-if="record !== null && record.version !== ''"
					class="ext-neowiki-bundle-group__detail"
				>
					{{ $i18n( 'neowiki-bundle-version', record.version ).text() }}
				</span>
				<span
					v-if="record !== null"
					class="ext-neowiki-bundle-group__detail"
				>
					<I18nSlot
						v-if="isUrlSource( record.source )"
						message-key="neowiki-bundle-source-from-url"
					>
						<a :href="record.source">{{ record.source }}</a>
					</I18nSlot>
					<template v-else>
						{{ $i18n( 'neowiki-bundle-source-from-file' ).text() }}
					</template>
				</span>
				<span
					v-if="record !== null"
					class="ext-neowiki-bundle-group__detail"
				>
					<a :href="recordUrl( record )">{{ $i18n( 'neowiki-bundle-record-link' ).text() }}</a>
				</span>
				<span
					v-if="changedCount > 0"
					class="ext-neowiki-bundle-group__detail ext-neowiki-bundle-group__detail--changed"
				>
					{{ $i18n( 'neowiki-bundle-changed-count', changedCount ).text() }}
				</span>
				<span
					v-if="availableVersion !== null"
					class="ext-neowiki-bundle-group__detail ext-neowiki-bundle-group__detail--available"
				>
					{{ $i18n( 'neowiki-bundle-version-available', availableVersion ).text() }}
				</span>
			</div>

			<div
				v-if="record !== null && canInstall"
				class="ext-neowiki-bundle-group__actions"
			>
				<CdxButton
					v-if="isUrlSource( record.source )"
					@click="emit( 'update', true )"
				>
					{{ $i18n( 'neowiki-bundle-update-from-source' ).text() }}
				</CdxButton>
				<CdxButton @click="emit( 'update', false )">
					{{ $i18n( 'neowiki-bundle-update-from-file' ).text() }}
				</CdxButton>
			</div>
		</div>

		<p
			v-if="record !== null && record.description !== ''"
			class="ext-neowiki-bundle-group__description"
		>
			{{ record.description }}
		</p>

		<div
			v-if="expanded"
			class="ext-neowiki-bundle-group__items"
		>
			<CdxMessage
				v-if="error !== null"
				type="error"
				:inline="true"
			>
				{{ error }}
			</CdxMessage>
			<CdxProgressBar
				v-else-if="items === null"
				:inline="true"
			/>
			<p v-else-if="items.length === 0">
				{{ $i18n( 'neowiki-bundle-group-empty' ).text() }}
			</p>
			<ItemList
				v-else
				:items="items"
			/>
		</div>
	</section>
</template>

<script setup lang="ts">
import { computed, ref, shallowRef, watch } from 'vue';
import { CdxButton, CdxIcon, CdxMessage, CdxProgressBar } from '@wikimedia/codex';
import { cdxIconCollapse, cdxIconExpand } from '@wikimedia/codex-icons';
import { isUrlSource, type InstallRecord } from '../application/InstallRecord.ts';
import type { DataModelItem } from '../application/bundleStatus.ts';
import { sameBundleName } from '../application/bundleName.ts';
import { parseBundleFile } from '../persistence/bundleFile.ts';
import { fetchBundleFile } from '../persistence/fetchBundleFile.ts';
import { installRecordTitle } from '../persistence/MediaWikiInstallRecordRepository.ts';
import ItemList from './ItemList.vue';
import { I18nSlot } from 'ext.neowiki';

interface Props {
	/** The installed bundle; null for the group of what no bundle owns. */
	record: InstallRecord | null;
	canInstall: boolean;
	/** Called when the group is first expanded, as reading a bundle's items takes a request for each. */
	loadItems: () => Promise<DataModelItem[]>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
	/** Whether to read the new version from the URL the bundle was installed from. */
	update: [ fromSource: boolean ];
}>();

const expanded = ref( false );
const items = shallowRef<DataModelItem[] | null>( null );
const error = ref<string | null>( null );
const availableVersion = ref<string | null>( null );

const title = computed( () => props.record?.name ?? mw.msg( 'neowiki-bundle-group-this-wiki' ) );

// Where a bundle is detached, by deleting the page.
function recordUrl( record: InstallRecord ): string {
	return mw.util.getUrl( installRecordTitle( record.name ) );
}

const changedCount = computed( () => ( items.value ?? [] ).filter(
	( item ) => item.status === 'changed-here' || item.status === 'deleted-here'
).length );

function toggle(): void {
	expanded.value = !expanded.value;

	if ( expanded.value && items.value === null && error.value === null ) {
		load();
	}
}

async function load(): Promise<void> {
	try {
		items.value = await props.loadItems();
	} catch ( loadError ) {
		error.value = loadError instanceof Error ? loadError.message : String( loadError );
	}
}

// A source that cannot be reached, that holds another bundle, or that gives no version, shows no other version.
async function checkSource(): Promise<void> {
	const record = props.record;

	if ( record === null || !isUrlSource( record.source ) ) {
		return;
	}

	try {
		const metadata = parseBundleFile( await fetchBundleFile( record.source ) ).metadata;

		if ( metadata !== null && sameBundleName( metadata.name, record.name ) && metadata.version !== '' &&
			metadata.version !== record.version ) {
			availableVersion.value = metadata.version;
		}
	} catch ( _error ) {
		availableVersion.value = null;
	}
}

watch( () => props.canInstall, ( canInstall ) => {
	if ( canInstall ) {
		checkSource();
	}
}, { immediate: true } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-bundle-group {
	padding: @spacing-75 0;
	border-bottom: @border-subtle;

	&__header {
		display: flex;
		align-items: flex-start;
		gap: @spacing-50;
	}

	&__title {
		display: flex;
		flex-wrap: wrap;
		flex-grow: 1;
		align-items: baseline;
		column-gap: @spacing-75;
		min-height: @size-200;
		padding-top: @spacing-25;
	}

	&__name {
		margin: 0;
		padding: 0;
		border: 0;
		font-size: @font-size-large;
	}

	&__detail {
		color: @color-subtle;

		&--changed {
			color: @color-warning;
		}

		&--available {
			color: @color-progressive;
		}
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50;
	}

	&__description,
	&__items {
		margin: @spacing-50 0 0 calc( @size-200 + @spacing-50 );
	}
}
</style>
