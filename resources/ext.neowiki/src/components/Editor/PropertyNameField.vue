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
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
	CdxMenuButton,
	MenuButtonItemData,
	CdxIcon
} from '@wikimedia/codex';
import { cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import { cdxIconMenu } from '@/assets/CustomIcons';

defineProps( {
	modelValue: {
		type: String,
		required: true
	},
	canEditSchema: {
		type: Boolean,
		required: true
	}
} );

const emit = defineEmits( [ 'edit', 'delete' ] );

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
		emit( 'delete' );
	}
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
		font-size: 14px;
		color: #202122c4;
	}

	.menu-icon {
		float: right;

		svg {
			fill: #404244b3;
		}
	}

	&:hover {
		.menu-icon {
			svg {
				fill: black !important;
			}
		}
	}
}
</style>
