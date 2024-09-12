"use strict";
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __export = (target, all) => {
  for (var name in all)
    __defProp(target, name, { get: all[name], enumerable: true });
};
var __copyProps = (to, from, except, desc) => {
  if (from && typeof from === "object" || typeof from === "function") {
    for (let key of __getOwnPropNames(from))
      if (!__hasOwnProp.call(to, key) && key !== except)
        __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
  }
  return to;
};
var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

// src/neo.ts
var neo_exports = {};
__export(neo_exports, {
  Neo: () => Neo
});
module.exports = __toCommonJS(neo_exports);

// src/application/AnotherThing.ts
var AnotherThing = class {
  doAnotherThing() {
    return "AnotherThing is done!";
  }
};

// src/application/Something.ts
var Something = class {
  doSomething() {
    return "Something is done!";
  }
  getAnotherThing() {
    return new AnotherThing();
  }
};

// src/neo.ts
var Neo = class _Neo {
  static getInstance() {
    var _a;
    (_a = _Neo.instance) != null ? _a : _Neo.instance = new _Neo();
    return _Neo.instance;
  }
  getSomething() {
    return new Something();
  }
  add(a, b) {
    return a + b;
  }
  multiply(a, b) {
    return a * b;
  }
};
// Annotate the CommonJS export names for ESM import in node:
0 && (module.exports = {
  Neo
});
