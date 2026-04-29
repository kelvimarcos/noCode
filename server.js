const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const publicDir = path.join(__dirname, 'public');

let metrics = { acessos: 0, giros: 0, ganhos: 0 };
let config = {
  bloqueada: true,
  limitePorUsuario: 1,
  premios: [
    { id: 1, tipo: 'Cupom', texto: 'R$ 50 OFF', valor: 'PROMO50', prob: 20, cor: '#6568E8' },
    { id: 2, tipo: 'Prêmio', texto: 'Frete Grátis', valor: 'FRETEFREE', prob: 30, cor: '#E64F9B' },
    { id: 3, tipo: 'Sem Prêmio', texto: 'Tente novamente', valor: '-', prob: 50, cor: '#2E274B' }
  ]
};

function sendJson(res, statusCode, payload) {
  res.writeHead(statusCode, { 'Content-Type': 'application/json; charset=utf-8' });
  res.end(JSON.stringify(payload));
}

function serveFile(res, filePath) {
  const ext = path.extname(filePath);
  const map = { '.html': 'text/html', '.css': 'text/css', '.js': 'application/javascript' };
  fs.readFile(filePath, (err, data) => {
    if (err) return sendJson(res, 404, { ok: false, message: 'Arquivo não encontrado' });
    res.writeHead(200, { 'Content-Type': `${map[ext] || 'text/plain'}; charset=utf-8` });
    res.end(data);
  });
}

const server = http.createServer((req, res) => {
  const { url, method } = req;

  if (url === '/api/config' && method === 'GET') return sendJson(res, 200, { config, metrics });

  if (url === '/api/config' && method === 'POST') {
    let body = '';
    req.on('data', (chunk) => (body += chunk));
    req.on('end', () => {
      const incoming = JSON.parse(body || '{}');
      config = { ...config, ...incoming };
      sendJson(res, 200, { ok: true, config });
    });
    return;
  }

  if (url === '/api/spin' && method === 'POST') {
    metrics.acessos += 1;
    if (config.bloqueada) return sendJson(res, 403, { ok: false, message: 'Roleta bloqueada.' });
    metrics.giros += 1;
    const random = Math.random() * 100;
    let acumulado = 0;
    const premio = config.premios.find((p) => (acumulado += Number(p.prob || 0), random <= acumulado)) || config.premios[0];
    if (premio.tipo !== 'Sem Prêmio') metrics.ganhos += 1;
    return sendJson(res, 200, { ok: true, premio, metrics });
  }

  if (url === '/api/metrics/reset' && method === 'POST') {
    metrics = { acessos: 0, giros: 0, ganhos: 0 };
    return sendJson(res, 200, { ok: true, metrics });
  }

  const cleanPath = url === '/' ? '/index.html' : url;
  const filePath = path.join(publicDir, cleanPath);
  if (filePath.startsWith(publicDir)) return serveFile(res, filePath);
  return sendJson(res, 403, { ok: false, message: 'Acesso negado' });
});

server.listen(PORT, () => console.log(`Servidor iniciado em http://localhost:${PORT}`));
