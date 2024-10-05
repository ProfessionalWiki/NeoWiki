<template>
	<div class="neo-property-name__wrapper">
		<span class="property-name"
		>
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
			<CdxIcon :icon="cdxIconMenu" />
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
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.neo-property-name__wrapper {
	display: flex;
	border-radius: $border-radius-base;
	border: $border-width-base solid rgba( $border-color-disabled, 0.55 );
	margin-top: 5px;
	padding-right: -2px;

	.property-name {
		width: 210px;
		padding-top: 5px;
		padding-left: 8px;
	}

	.cdx-menu {
		max-width: 150px !important;

		.cdx-menu-item__text__label {
			bdi {
				font-size: $font-size-x-small !important;
			}
		}
	}

	.cdx-menu-item {
		.cdx-icon {
			svg {
				height: 15px !important;
				width: 15px !important;
			}
		}

		&:nth-child( 1 ) {
			.cdx-icon svg {
				fill: #54595d !important;
			}
		}
	}

	.neo-property-name__menu-button {
		background-color: #c8ccd147;
		margin-right: -1px;
	}
}
</style>
