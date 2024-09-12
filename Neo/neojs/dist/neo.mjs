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
export {
  Neo
};
