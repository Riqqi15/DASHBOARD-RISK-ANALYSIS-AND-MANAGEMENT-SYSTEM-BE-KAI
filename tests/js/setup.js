import { config } from '@vue/test-utils';
import { beforeEach } from 'vitest';

const localStorageValues = new Map();

Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
        clear: () => localStorageValues.clear(),
        getItem: (key) => localStorageValues.get(String(key)) ?? null,
        key: (index) => [...localStorageValues.keys()][index] ?? null,
        removeItem: (key) => localStorageValues.delete(String(key)),
        setItem: (key, value) => localStorageValues.set(String(key), String(value)),
        get length() {
            return localStorageValues.size;
        },
    },
});

beforeEach(() => {
    window.localStorage.clear();
});

config.global.stubs = {
    Head: true,
    Link: true,
};
