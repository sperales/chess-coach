const assert = require('assert');
const { PositionTree, RequestCoordinator, fenGrid } = require('../assets/js/interactive-position.js');

const tree = new PositionTree('root');
tree.play('after-e4', 'e2e4', 'e4');
tree.play('after-e5', 'e7e5', 'e5');
tree.back();
tree.play('after-c5', 'c7c5', 'c5');
assert.strictEqual(tree.nodes.get(2).children.length, 2, 'Branches must remain available.');
tree.back();
tree.forward(tree.nodes.get(2).children[0]);
assert.strictEqual(tree.current().uci, 'e7e5', 'Forward navigation must select a retained branch.');
tree.reset();
assert.strictEqual(tree.current().fen, 'root', 'Reset must return to the original position.');
assert.strictEqual(fenGrid('8/8/8/8/8/8/8/8 w - - 0 1').length, 8, 'FEN renderer must produce eight ranks.');

(async () => {
  const coordinator = new RequestCoordinator();
  const slow = coordinator.run(() => new Promise(resolve => setTimeout(() => resolve('old'), 25)));
  const fresh = coordinator.run(() => Promise.resolve('new'));
  assert.strictEqual(await fresh, 'new', 'Latest request must resolve normally.');
  assert.strictEqual(await slow, null, 'Superseded response must be discarded.');
  console.log('Interactive position JavaScript tests passed.');
})().catch(error => { console.error(error); process.exit(1); });
