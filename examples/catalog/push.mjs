// Node 20+. Export/map your real source catalog into the documented product contract first.
import { readFile } from 'node:fs/promises';
const endpoint = new URL(process.env.RASLO_API_URL || 'https://invalid.example/api/integrations/v1/products/batch');
if (endpoint.protocol !== 'https:' || endpoint.username || endpoint.password) throw Error('Use a credential-free HTTPS Raslo API URL.');
if (!process.env.RASLO_API_KEY || !process.env.CATALOG_SOURCE_FILE) throw Error('Set RASLO_API_KEY and CATALOG_SOURCE_FILE.');
const products = JSON.parse(await readFile(process.env.CATALOG_SOURCE_FILE, 'utf8'));
if (!Array.isArray(products)) throw Error('Source file must contain a mapped product array.');
for (let offset = 0; offset < products.length; offset += 100) {
  const response = await fetch(endpoint, { method: 'POST', redirect: 'error', signal: AbortSignal.timeout(30000),
    headers: { Authorization: `Bearer ${process.env.RASLO_API_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ products: products.slice(offset, offset + 100) }) });
  if (!response.ok) throw Error(`Raslo returned HTTP ${response.status}; fix entitlement/payload or retry later. Provider content is not logged.`);
  const result = await response.json();
  console.log(JSON.stringify({ processed: result.processed, changed: result.changed, skipped: result.skipped, sync_run_id: result.sync_run_id }));
}
