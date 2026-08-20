(function (global) {
  'use strict';

  class RequestCoordinator {
    constructor() { this.sequence = 0; this.controller = null; }
    async run(factory) {
      this.cancel();
      const sequence = ++this.sequence;
      this.controller = new AbortController();
      try {
        const value = await factory(this.controller.signal);
        return sequence === this.sequence ? value : null;
      } catch (error) {
        if (error?.name === 'AbortError') return null;
        throw error;
      }
    }
    cancel() {
      if (this.controller) this.controller.abort();
      this.controller = null;
      this.sequence++;
    }
  }

  class PositionTree {
    constructor(fen) {
      this.nodes = new Map();
      this.nextId = 1;
      this.rootId = this.addNode(null, fen, null, null);
      this.currentId = this.rootId;
    }
    addNode(parentId, fen, uci, san) {
      const id = this.nextId++;
      this.nodes.set(id, { id, parentId, fen, uci, san, children: [], analysis: null });
      if (parentId) this.nodes.get(parentId)?.children.push(id);
      return id;
    }
    current() { return this.nodes.get(this.currentId); }
    play(fen, uci, san) {
      const parent = this.current();
      const existing = parent.children.map(id => this.nodes.get(id)).find(node => node.uci === uci);
      this.currentId = existing ? existing.id : this.addNode(parent.id, fen, uci, san);
      return this.current();
    }
    back() {
      const parentId = this.current()?.parentId;
      if (parentId) this.currentId = parentId;
      return this.current();
    }
    forward(childId = null) {
      const children = this.current()?.children || [];
      const target = childId && children.includes(childId) ? childId : children[0];
      if (target) this.currentId = target;
      return this.current();
    }
    reset() { this.currentId = this.rootId; return this.current(); }
    line() {
      const line = [];
      let node = this.current();
      while (node) { line.unshift(node); node = node.parentId ? this.nodes.get(node.parentId) : null; }
      return line;
    }
  }

  async function api(body, signal) {
    const response = await fetch('api/position-analysis.php', {
      method: 'POST',
      headers: global.chessCoachCsrfHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(body), signal,
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo analizar la posición.');
    return data;
  }

  function fenGrid(fen) {
    return String(fen || '').split(' ')[0].split('/').map(rank => {
      const row = [];
      for (const char of rank) /^\d$/.test(char) ? row.push(...Array(Number(char)).fill('')) : row.push(char);
      return row;
    });
  }

  function renderBoard(element, fen, options = {}) {
    if (!element) return;
    const grid = fenGrid(fen);
    const black = options.orientation === 'black';
    const ranks = black ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
    const files = black ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];
    const images = { P:'wp.png',N:'wn.png',B:'wb.png',R:'wr.png',Q:'wq.png',K:'wk.png',p:'bp.png',n:'bn.png',b:'bb.png',r:'br.png',q:'bq.png',k:'bk.png' };
    const path = String(options.piecePath || global.CHESS_COACH_PIECE_PATH || 'assets/pieces/Set%201/');
    const selected = options.selected || '';
    const targets = new Set(options.targets || []);
    const last = options.lastMove || '';
    let html = '';
    ranks.forEach((row, rankIndex) => files.forEach((file, fileIndex) => {
      const square = String.fromCharCode(97 + file) + (8 - row);
      const piece = grid[row]?.[file] || '';
      const classes = [((row + file) % 2 ? 'dark' : 'light')];
      if (square === selected) classes.push('selected');
      if (targets.has(square)) classes.push('legal-target');
      if (square === last.slice(0,2)) classes.push('from');
      if (square === last.slice(2,4)) classes.push('to');
      const image = piece ? `<img class="board-piece" src="${path}${images[piece]}" alt="" draggable="false">` : '';
      const fileLabel = rankIndex === 7 ? `<span class="analysis-file-label">${square[0].toUpperCase()}</span>` : '';
      const rankLabel = fileIndex === 0 ? `<span class="analysis-rank-label">${square[1]}</span>` : '';
      html += `<button type="button" class="sq ${classes.join(' ')}" data-square="${square}">${image}${fileLabel}${rankLabel}</button>`;
    }));
    element.innerHTML = html;
    if (typeof options.onSquare === 'function') {
      element.querySelectorAll('[data-square]').forEach(button => button.addEventListener('click', () => options.onSquare(button.dataset.square)));
    }
  }

  global.ChessInteractive = { RequestCoordinator, PositionTree, api, fenGrid, renderBoard };
  if (typeof module !== 'undefined' && module.exports) module.exports = { RequestCoordinator, PositionTree, fenGrid };
})(typeof window !== 'undefined' ? window : globalThis);
