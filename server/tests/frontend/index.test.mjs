import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../../resources/scripts/index.js', import.meta.url), 'utf8');

function load(document, confirm = () => true) {
  const context = { document, confirm };
  vm.createContext(context);
  vm.runInContext(source, context);
  return context;
}

test('setup tolerates pages without the activity table', () => {
  const context = load({ querySelector: () => null });
  assert.doesNotThrow(() => context.setup());
});

test('submitWithUiState preserves the selected user', () => {
  const hiddenInputs = [];
  const form = {
    appendChild(input) {
      hiddenInputs.push(input);
    },
  };
  const context = load({
    querySelector(selector) {
      if (selector === '#idUsers') {
        return { value: 'u1' };
      }
      return null;
    },
    createElement() {
      return {};
    },
  });

  context.submitWithUiState({ type: 'submit', form });

  assert.deepEqual(hiddenInputs, [{ type: 'hidden', name: 'selectedUser', value: 'u1' }]);
});
