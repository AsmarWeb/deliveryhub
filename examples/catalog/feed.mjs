// Reference adapter endpoint, not a native Shopify/WooCommerce connector.
// Put behind your HTTPS reverse proxy; refresh CATALOG_SOURCE_FILE from the actual store.
import { createServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { readFile } from 'node:fs/promises';
const token = process.env.CATALOG_FEED_TOKEN;
if (!token || token.length < 16 || !process.env.CATALOG_SOURCE_FILE) throw Error('Set a private CATALOG_FEED_TOKEN and CATALOG_SOURCE_FILE.');
const expected = Buffer.from(`Bearer ${token}`);
createServer(async (request, response) => {
  response.setHeader('Content-Type', 'application/json');
  response.setHeader('Cache-Control', 'no-store');
  const provided = Buffer.from(request.headers.authorization || '');
  if (provided.length !== expected.length || !timingSafeEqual(provided, expected)) { response.writeHead(401).end('{"error":"unauthorized"}'); return; }
  const url = new URL(request.url, 'http://localhost');
  if (request.method !== 'GET' || url.pathname !== '/raslo/catalog') { response.writeHead(404).end('{}'); return; }
  const cursor = url.searchParams.get('cursor') || '0';
  if (!/^(0|[1-9][0-9]{0,6})$/.test(cursor)) { response.writeHead(400).end('{"error":"invalid_cursor"}'); return; }
  try {
    const products = JSON.parse(await readFile(process.env.CATALOG_SOURCE_FILE, 'utf8'));
    if (!Array.isArray(products)) throw Error('Invalid source');
    const start = Number(cursor), end = start + 100;
    response.end(JSON.stringify({ products: products.slice(start, end), next_cursor: end < products.length ? String(end) : null }));
  } catch { response.writeHead(503).end('{"error":"catalog_unavailable"}'); }
}).listen(Number(process.env.PORT || 8091), '127.0.0.1');
