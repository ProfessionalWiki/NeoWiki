<template>
	<CdxDialog
		v-model:open="isOpen"
		:title="$i18n( 'neowiki-create-subject-dialog-title' ).text()"
		class="create-subject-dialog"
	>
		<div class="create-subject-dialog__content">
			<div class="create-subject-dialog__blank-button-container">
				<CdxButton
					weight="primary"
					class="create-subject-dialog__blank-button"
					@click="proceedWithBlank"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-create-subject-dialog-start-blank' ).text() }}
				</CdxButton>
				<div class="neo-tooltip">
					<CdxIcon
						v-tooltip:right="$i18n( 'neowiki-create-subject-dialog-start-blank-description' ).text()"
						:icon="cdxIconInfoFilled" />
				</div>
			</div>
			<div class="create-subject-dialog__separator">
				<span>{{ $i18n( 'neowiki-create-subject-dialog-or-select' ).text() }}</span>
			</div>
			<CdxTypeaheadSearch
				:id="searchInputId"
				form-action=""
				:search-results="searchResults"
				:placeholder="$i18n( 'neowiki-create-subject-dialog-select-type' ).text()"
				:initial-input-value="searchQuery"
				:show-thumbnail="false"
				:highlight-query="true"
				class="create-subject-dialog__search"
				@input="handleInput"
				@search-result-click="handleSearchResultClick"
			/>
		</div>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
	CdxDialog, CdxButton, CdxTypeaheadSearch, CdxIcon,
	SearchResultClickEvent, SearchResult
} from '@wikimedia/codex';
import { useSchemaStore } from '@/stores/SchemaStore';
import { cdxIconAdd, cdxIconInfoFilled } from '@wikimedia/codex-icons';

const emit = defineEmits<( e: 'next', schemaName: string ) => void>();

const isOpen = ref( false );
const searchQuery = ref( '' );
const searchInputId = 'create-subject-search';
const schemaStore = useSchemaStore();
const schemas = computed( () => schemaStore.getSchemas );

const searchResults = computed<SearchResult[]>( () => {
	const query = searchQuery.value.toLowerCase();
	return Array.from( schemas.value.values() )
		.filter( ( schema ) => schema.getName().toLowerCase().includes( query ) )
		.map( ( schema ) => ( {
			value: schema.getName(),
			label: schema.getName(),
			description: schema.getDescription(),
			url: '#'
		} ) );
} );

const openDialog = (): void => {
	isOpen.value = true;
	searchQuery.value = '';
};

const proceedWithBlank = (): void => {
	isOpen.value = false;
	emit( 'next', '' );
};

const handleInput = ( value: string ): void => {
	searchQuery.value = value;
};

const handleSearchResultClick = ( result: SearchResultClickEvent ): void => {
	isOpen.value = false;
	searchQuery.value = result.searchResult?.value as string;
	emit( 'next', result.searchResult?.value as string );
};

defineExpose( { openDialog } );
</script>

<style lang="scss">
@import '@/assets/scss/variables';

.create-subject-dialog {
	padding-bottom: 50px;
	max-width: 500px;

	&__content {
		border-radius: 8px;
	}

	&__blank-button-container {
		display: flex;
		align-items: center;
	}

	&__blank-button {
		flex-grow: 1;
		margin-right: 8px;
		padding: 12px;
		font-size: 1rem;
	}

	&__button-icon {
		margin-right: 8px;
	}

	&__separator {
		display: flex;
		align-items: center;
		text-align: center;
		margin: 24px 0;

		&::before,
		&::after {
			content: '';
			flex: 1;
			border-bottom: 1px solid $neo-input-border-color;
		}

		span {
			padding: 0 10px;
			font-style: italic;
			color: $neo-secondary;
		}
	}

	&__search {
		position: absolute;
		width: 425px;
	}

	.cdx-text-input__input {
		padding-top: 10px;
		padding-bottom: 10px;
	}

	.cdx-text-input__input::placeholder {
		font-size: 0.9rem;
	}

	.cdx-typeahead-search {
		&__menu {
			max-height: 250px;
			overflow-y: scroll;
		}

		&:focus-within .cdx-typeahead-search__menu {
			display: block !important;
		}
	}
}

.neo-tooltip {
	margin-left: 15px;
}
</style>
