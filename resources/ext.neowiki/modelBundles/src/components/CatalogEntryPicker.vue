<template>
	<div class="ext-neowiki-catalog-entries">
		<section
			v-for="group in groups"
			:key="group.kind"
			class="ext-neowiki-catalog-entries__group"
		>
			<h3 class="ext-neowiki-catalog-entries__heading">
				{{ $i18n( group.heading ).text() }}
			</h3>
			<ul class="ext-neowiki-catalog-entries__list">
				<li
					v-for="entry in group.entries"
					:key="entry.name"
					class="ext-neowiki-catalog-entries__entry"
				>
					<div class="ext-neowiki-catalog-entries__text">
						<strong>{{ entry.name }}</strong>
						<span
							v-if="installedText( entry ) !== null"
							class="ext-neowiki-catalog-entries__installed"
						>
							{{ installedText( entry ) }}
						</span>
						<p
							v-if="entry.description !== ''"
							class="ext-neowiki-catalog-entries__description"
						>
							{{ entry.description }}
						</p>
					</div>
					<CdxButton
						:aria-label="$i18n( 'neowiki-bundle-catalog-load', entry.name ).text()"
						:disabled="disabled"
						@click="emit( 'choose', entry )"
					>
						{{ $i18n( 'neowiki-bundle-source-url-button' ).text() }}
					</CdxButton>
				</li>
			</ul>
		</section>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import type { CatalogEntry } from '../application/Catalog.ts';
import type { InstallRecord } from '../application/InstallRecord.ts';
import { sameBundleName } from '../application/bundleName.ts';

const props = defineProps<{
	entries: readonly CatalogEntry[];
	records: readonly InstallRecord[];
	disabled: boolean;
}>();

const emit = defineEmits<{
	choose: [ entry: CatalogEntry ];
}>();

const groups = computed( () => [
	{ kind: 'model', heading: 'neowiki-bundle-catalog-models' },
	{ kind: 'destination', heading: 'neowiki-bundle-catalog-destinations' }
].map( ( group ) => ( { ...group, entries: props.entries.filter( ( entry ) => entry.kind === group.kind ) } ) )
	.filter( ( group ) => group.entries.length > 0 ) );

function installedText( entry: CatalogEntry ): string | null {
	const record = props.records.find( ( installed ) => sameBundleName( installed.name, entry.name ) );

	if ( record === undefined ) {
		return null;
	}

	return record.version === '' ?
		mw.msg( 'neowiki-bundle-catalog-installed' ) :
		mw.msg( 'neowiki-bundle-catalog-installed-version', record.version );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-catalog-entries {
	&__group + &__group {
		margin-top: @spacing-100;
	}

	&__heading {
		margin: 0 0 @spacing-50;
		font-size: @font-size-medium;
	}

	&__list {
		margin: 0;
		list-style: none;
	}

	&__entry {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: @spacing-75;
		margin: 0;
		padding: @spacing-50 0;
		border-bottom: @border-subtle;
	}

	&__installed {
		margin-inline-start: @spacing-50;
		color: @color-subtle;
	}

	&__description {
		margin: @spacing-25 0 0;
	}
}
</style>
