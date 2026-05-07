local p = {}

-- Inline styles use Codex CSS custom properties so the row picks up the
-- current theme (light / dark) where Codex CSS is loaded; hex fallbacks
-- reproduce the light-mode values otherwise.
local style = table.concat( {
	'display:flex',
	'gap:8px',
	'padding:16px',
	'justify-content:safe center',
	'overflow-x:auto',
	'align-items:flex-start',
	'background:var(--background-color-interactive-subtle, #eaecf0)',
	'border:1px solid var(--border-color-subtle, #c8ccd1)',
	'border-radius:2px',
	'margin-block:1em',
}, ';' )

-- Each positional argument is a subject ID, optionally followed by @LayoutName
-- for a per-view layout override. The named layout= argument is the row's
-- default layout when no override is present.
function p.render( frame )
	local rowLayout = frame.args.layout
	local out = { '<div style="' .. style .. '">' }

	for _, arg in ipairs( frame.args ) do
		local id, viewLayout = arg, rowLayout
		local at = string.find( arg, '@', 1, true )
		if at then
			id = string.sub( arg, 1, at - 1 )
			viewLayout = string.sub( arg, at + 1 )
		end

		local view
		if viewLayout and viewLayout ~= '' then
			view = frame:preprocess( '{{#view:' .. id .. '|' .. viewLayout .. '}}' )
		else
			view = frame:preprocess( '{{#view:' .. id .. '}}' )
		end
		-- flex-shrink:0 keeps each infobox at its natural width so the row
		-- scrolls horizontally on narrow viewports instead of squishing.
		out[#out + 1] = '<div style="flex-shrink:0">' .. view .. '</div>'
	end

	out[#out + 1] = '</div>'
	return table.concat( out )
end

return p
