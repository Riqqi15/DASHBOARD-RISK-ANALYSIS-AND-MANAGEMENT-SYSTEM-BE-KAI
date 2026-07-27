import { config } from '@vue/test-utils';

config.global.stubs = {
    Head: true,
    Link: true,
};
