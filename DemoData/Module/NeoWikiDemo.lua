local p = {}
local nw = require( 'mw.neowiki' )

function p.value( frame )
	local property = frame.args[1]
	local page = frame.args['page']

	local options = nil
	if page then
		options = { page = page }
	end

	local v = nw.getValue( property, options )

	if type( v ) == 'table' then
		local parts = {}
		for _, item in ipairs( v ) do
			parts[#parts + 1] = tostring( item )
		end
		return table.concat( parts, ', ' )
	end

	return tostring( v or '' )
end

function p.subject( frame )
	local page = frame.args[1]
	local s = nw.getMainSubject( page )

	if not s then
		return 'No subject found'
	end

	local rows = {}
	rows[#rows + 1] = '{| class="wikitable"'
	rows[#rows + 1] = '! Property !! Type !! Value(s)'

	for name, stmt in pairs( s.statements ) do
		local vals = {}
		for _, v in ipairs( stmt.values ) do
			if type( v ) == 'table' then
				vals[#vals + 1] = v.label or v.target or tostring( v )
			else
				vals[#vals + 1] = tostring( v )
			end
		end
		rows[#rows + 1] = '|-'
		rows[#rows + 1] = '| ' .. name .. ' || ' .. stmt.type .. ' || ' .. table.concat( vals, ', ' )
	end

	rows[#rows + 1] = '|}'
	return table.concat( rows, '\n' )
end

function p.children( frame )
	local page = frame.args[1]
	local children = nw.getChildSubjects( page )

	if not children or #children == 0 then
		return 'No child subjects'
	end

	local parts = {}
	for _, child in ipairs( children ) do
		parts[#parts + 1] = "'''" .. child.label .. "''' (" .. child.schema .. ")"
	end

	return table.concat( parts, ', ' )
end

return p
