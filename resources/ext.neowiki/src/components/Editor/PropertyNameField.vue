<template>
	<div class="neo-property-name__wrapper">
		<span class="property-name">
			{{ modelValue }}
		</span>

		<CdxMenuButton
			v-if="canEditSchema"
			class="neo-property-name__menu-button"
			:selected="null"
			:menu-items="menuItems"
			aria-label=" "
			@update:selected="onMenuSelect"
		>
			<CdxIcon :icon="cdxIconMenu" class="menu-icon" />
		</CdxMenuButton>
		<DeleteDialog
			:is-open="isDeleteDialogOpen"
			@delete="onDelete"
			@close="isDeleteDialogOpen = false"
		>
			<p v-html="$i18n( 'neowiki-delete-dialog-confirmation-message', schemaName.toString(), modelValue ).text()" />
		</DeleteDialog>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, PropType } from 'vue';
import { type SchemaName } from '@neo/domain/Schema.ts';
import {
	CdxMenuButton,
	MenuButtonItemData,
	CdxIcon
} from '@wikimedia/codex';
import { cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import { cdxIconMenu } from '@/assets/CustomIcons';
import DeleteDialog from '@/components/Editor/DeleteDialog.vue';

defineProps( {
	modelValue: {
		type: String,
		required: true
	},
	canEditSchema: {
		type: Boolean,
		required: true
	},
	schemaName: {
		type: String as PropType<SchemaName>,
		required: true
	}
} );

const emit = defineEmits( [ 'edit', 'delete' ] );

const isDeleteDialogOpen = ref( false );

const menuItems = computed<MenuButtonItemData[]>( () => [
	{
		value: 'edit',
		label: mw.message( 'neowiki-infobox-editor-edit-property' ).text(),
		icon: cdxIconEdit
	},
	{
		value: 'delete',
		label: mw.message( 'neowiki-infobox-editor-delete-property' ).text(),
		icon: cdxIconTrash,
		action: 'destructive'
	}
] );

const onMenuSelect = ( value: string ): void => {
	if ( value === 'edit' ) {
		emit( 'edit' );
	} else if ( value === 'delete' ) {
		isDeleteDialogOpen.value = true;
	}
};

const onDelete = (): void => {
	emit( 'delete' );
	isDeleteDialogOpen.value = false;
};
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.neo-property-name__wrapper {
	display: flex;
	margin-top: 5px;

	.property-name {
		width: 230px;
		padding-top: 3px;
		padding-left: 4px;
		font-weight: $font-weight-semi-bold;
		font-size: $font-size-small;
		color: $color-base;
	}

	.menu-icon {
		float: right;

		svg {
			fill: #404244b3;

			&:hover {
				box-shadow: 0 1px 2px rgba( 0, 0, 0, 0.05 );
				border-radius: 5px;
			}
		}
	}

	&:hover {
		.menu-icon {
			svg {
				fill: $color-emphasized !important;
			}
		}
	}
}
</style>
