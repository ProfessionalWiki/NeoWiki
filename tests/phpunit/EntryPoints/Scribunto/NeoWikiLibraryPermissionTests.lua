local testframework = require 'Module:TestFramework'
local nw = require( 'mw.neowiki' )

local restrictedPage = 'NeoWikiLuaRestrictedPage'
local openPage = 'NeoWikiLuaTestPage'

local function testGetValueOnRestrictedPageIsNil()
	return nw.getValue( 'City', { page = restrictedPage } )
end

local function testGetValueOnOpenPageStillReads()
	return nw.getValue( 'City', { page = openPage } )
end

local function testGetMainSubjectOnRestrictedPageIsNil()
	return nw.getMainSubject( restrictedPage )
end

local function testGetSubjectsOnRestrictedPageIsEmpty()
	return #nw.getSubjects( restrictedPage )
end

local function testGetSchemaOnRestrictedPageIsNil()
	return nw.getSchema( 'RestrictedSchema' )
end

local function testGetSchemaOnOpenPageStillReads()
	return nw.getSchema( 'Employee' ) ~= nil
end

local function testQueryReturnsNoRowsWithoutTheRight()
	return next( nw.query( 'RETURN 1 AS n' ) ) == nil
end

local function testSparqlQueryReturnsNoVariablesBindingsOrBooleanWithoutTheRight()
	local document = nw.sparqlQuery( 'SELECT * WHERE { ?s ?p ?o }' )
	return #document.head.vars, #document.results.bindings, document.boolean == nil
end

local tests = {
	{ name = 'getValue returns nil for a page the parsing user may not read',
	  func = testGetValueOnRestrictedPageIsNil, expect = { nil } },
	{ name = 'getValue still reads a page the parsing user may read',
	  func = testGetValueOnOpenPageStillReads, expect = { 'Berlin' } },
	{ name = 'getMainSubject returns nil for a page the parsing user may not read',
	  func = testGetMainSubjectOnRestrictedPageIsNil, expect = { nil } },
	{ name = 'getSubjects returns nothing for a page the parsing user may not read',
	  func = testGetSubjectsOnRestrictedPageIsEmpty, expect = { 0 } },
	{ name = 'getSchema returns nil for a Schema page the parsing user may not read',
	  func = testGetSchemaOnRestrictedPageIsNil, expect = { nil } },
	{ name = 'getSchema still reads a Schema page the parsing user may read',
	  func = testGetSchemaOnOpenPageStillReads, expect = { true } },
	{ name = 'query returns no rows without the neowiki-query right',
	  func = testQueryReturnsNoRowsWithoutTheRight, expect = { true } },
	{ name = 'sparqlQuery returns no variables, bindings or boolean without the neowiki-query right',
	  func = testSparqlQueryReturnsNoVariablesBindingsOrBooleanWithoutTheRight, expect = { 0, 0, true } },
}

return testframework.getTestProvider( tests )
