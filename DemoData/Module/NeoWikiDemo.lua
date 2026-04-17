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

	return tostring( v or '' )
end

function p.values( frame )
	local property = frame.args[1]
	local page = frame.args['page']
	local separator = frame.args['separator'] or ', '

	local options = nil
	if page then
		options = { page = page }
	end

	local all = nw.getAll( property, options )

	if not all then
		return ''
	end

	local parts = {}
	for _, item in ipairs( all ) do
		parts[#parts + 1] = tostring( item )
	end

	return table.concat( parts, separator )
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

local function renderRowsAsTable( rows )
	if #rows == 0 then
		return 'No results'
	end

	local columns = {}
	for k in pairs( rows[1] ) do
		columns[#columns + 1] = k
	end
	table.sort( columns )

	local out = { '{| class="wikitable"', '! ' .. table.concat( columns, ' !! ' ) }

	for _, row in ipairs( rows ) do
		local cells = {}
		for _, col in ipairs( columns ) do
			local v = row[col]
			cells[#cells + 1] = v == nil and '' or tostring( v )
		end
		out[#out + 1] = '|-'
		out[#out + 1] = '| ' .. table.concat( cells, ' || ' )
	end

	out[#out + 1] = '|}'
	return table.concat( out, '\n' )
end

function p.query( frame )
	return renderRowsAsTable( nw.query( frame.args[1] ) )
end

function p.productsFoundedSince( frame )
	local year = tonumber( frame.args[1] ) or 2000

	return renderRowsAsTable( nw.query(
		'MATCH (n:Product) WHERE n.`Available since` >= $year ' ..
			'RETURN n.name AS name, n.`Available since` AS year ORDER BY year',
		{ year = year }
	) )
end

return p
