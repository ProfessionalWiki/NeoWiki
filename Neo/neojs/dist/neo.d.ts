declare class AnotherThing {
    doAnotherThing(): string;
}

declare class Something {
    doSomething(): string;
    getAnotherThing(): AnotherThing;
}

declare class Neo {
    private static instance;
    static getInstance(): Neo;
    getSomething(): Something;
    add(a: number, b: number): number;
    multiply(a: number, b: number): number;
}

export { Neo };
