local p = {}

-- Codex tokens read live via CSS custom properties so cards adapt to dark mode
-- where Codex CSS is loaded. Hex/px fallbacks reproduce the light-mode values
-- on pages that don't load Codex (and for tokens not yet exposed by Vector,
-- e.g. --spacing-* and --border-radius-*).
local C = {
	background = 'var(--background-color-base, #ffffff)',
	border = 'var(--border-color-base, #a2a9b1)',
	borderRadius = 'var(--border-radius-base, 2px)',
	paddingLg = 'var(--spacing-100, 16px)',
	titleColor = 'var(--color-base, #202122)',
	descColor = 'var(--color-subtle, #54595d)',
	titleSize = '1.125rem',
	descSize = '0.875rem',
	gap = 'var(--spacing-75, 12px)',
	flexBasis = '240px',
	minWidth = '200px',
}

-- Inline-level spans (with display:block) let MediaWiki's wikitext link
-- syntax wrap the whole card body in a single sanitizer-approved <a>.
local function cardBody( title, description )
	local bodyStyle = table.concat( {
		'display:block',
		'background:' .. C.background,
		'border:1px solid ' .. C.border,
		'border-radius:' .. C.borderRadius,
		'padding:' .. C.paddingLg,
		'color:inherit',
		'text-decoration:none',
		'height:100%',
		'box-sizing:border-box',
	}, ';' )

	local titleStyle = table.concat( {
		'display:block',
		'font-size:' .. C.titleSize,
		'font-weight:600',
		'color:' .. C.titleColor,
		'margin-bottom:4px',
	}, ';' )

	local descStyle = table.concat( {
		'display:block',
		'font-size:' .. C.descSize,
		'color:' .. C.descColor,
		'line-height:1.4',
	}, ';' )

	return string.format(
		'<span style="%s"><span style="%s">%s</span><span style="%s">%s</span></span>',
		bodyStyle,
		titleStyle,
		mw.text.encode( title ),
		descStyle,
		mw.text.encode( description )
	)
end

local function cardHtml( args )
	local title = args.title or ''
	local description = args.description or ''
	local link = args.link or ''
	local body = cardBody( title, description )

	-- Flex sizing lives on an outer wrapper so it applies to the link itself,
	-- not to a child of the link (since wikitext link syntax forbids attributes
	-- on the produced <a>).
	local wrapperStyle = table.concat( {
		'display:block',
		'flex:1 1 ' .. C.flexBasis,
		'min-width:' .. C.minWidth,
	}, ';' )

	if link == '' then
		return string.format( '<span style="%s">%s</span>', wrapperStyle, body )
	end

	return string.format( '<span style="%s">[[%s|%s]]</span>', wrapperStyle, link, body )
end

function p.card( frame )
	return cardHtml( frame.args )
end

function p.cards( frame )
	-- Reads card1_title, card1_description, card1_link, card2_*, card3_*. Up to 9.
	local out = { '<div style="display:flex;gap:' .. C.gap .. ';flex-wrap:wrap;margin:1em 0">' }

	for i = 1, 9 do
		local title = frame.args['card' .. i .. '_title']
		if title then
			out[#out + 1] = cardHtml( {
				title = title,
				description = frame.args['card' .. i .. '_description'],
				link = frame.args['card' .. i .. '_link'],
			} )
		end
	end

	out[#out + 1] = '</div>'
	return table.concat( out )
end

return p
