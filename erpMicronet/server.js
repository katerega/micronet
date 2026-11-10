
import express from "express";
import multer from "multer";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const upload = multer({ storage: multer.memoryStorage() });

const PORT = Number(process.env.PORT || 3000);
const N8N_WEBHOOK = process.env.N8N_WEBHOOK || "http://localhost:5678/webhook/invoice-processing";
const TALLY_URL = process.env.TALLY_URL || "http://host.docker.internal:9000";
const TALLY_COMPANY = process.env.TALLY_COMPANY || "MICRONET";

app.use(express.json({ limit: "5mb" }));
app.use(express.static(path.join(__dirname, "public")));

function xmlEscape(v) {
  return String(v ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&apos;");
}

async function tallyPost(xml) {
  const response = await fetch(TALLY_URL, {
    method: "POST",
    headers: { "Content-Type": "text/xml" },
    body: xml
  });
  const text = await response.text();
  return { ok: response.ok, status: response.status, text };
}

app.get("/api/config", (_req, res) => {
  res.json({
    n8nWebhook: N8N_WEBHOOK,
    tallyUrl: TALLY_URL,
    company: TALLY_COMPANY
  });
});

app.get("/api/health/tally", async (_req, res) => {
  try {
    const result = await tallyPost("<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Data</TYPE><ID>DayBook</ID></HEADER><BODY><DESC><STATICVARIABLES><SVCURRENTCOMPANY>" +
      xmlEscape(TALLY_COMPANY) +
      "</SVCURRENTCOMPANY><SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT><SVFROMDATE>20260401</SVFROMDATE><SVTODATE>20260430</SVTODATE></STATICVARIABLES></DESC></BODY></ENVELOPE>");
    const connected = !/Could not set|Unknown Request|Connection refused|ECONNREFUSED/i.test(result.text);
    res.status(connected ? 200 : 502).json({
      connected,
      company: TALLY_COMPANY,
      endpoint: TALLY_URL,
      response: result.text
    });
  } catch (error) {
    res.status(502).json({ connected: false, error: error.message });
  }
});

app.post("/api/tally/po", async (req, res) => {
  const poNumber = String(req.body?.poNumber || "").trim();
  if (!poNumber) return res.status(400).json({ error: "poNumber is required" });

  // Read-only Tally collection query. The returned XML is shown to the UI;
  // parsing is intentionally conservative because Tally schemas can vary by installation.
  const xml = `<ENVELOPE>
<HEADER>
<VERSION>1</VERSION>
<TALLYREQUEST>Export</TALLYREQUEST>
<TYPE>Collection</TYPE>
<ID>N8N Purchase Order Lookup</ID>
</HEADER>
<BODY>
<DESC>
<STATICVARIABLES>
<SVCURRENTCOMPANY>${xmlEscape(TALLY_COMPANY)}</SVCURRENTCOMPANY>
<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
</STATICVARIABLES>
<TDL>
<TDLMESSAGE>
<COLLECTION NAME="N8N Purchase Order Lookup">
<TYPE>Vouchers : VoucherType</TYPE>
<CHILDOF>$$VchTypePurchaseOrder</CHILDOF>
<FILTER>N8NPOFilter</FILTER>
<FETCH>DATE</FETCH>
<FETCH>VOUCHERNUMBER</FETCH>
<FETCH>VOUCHERTYPENAME</FETCH>
<FETCH>PARTYLEDGERNAME</FETCH>
<FETCH>REFERENCE</FETCH>
<FETCH>NARRATION</FETCH>
<FETCH>ALLINVENTORYENTRIES.STOCKITEMNAME</FETCH>
<FETCH>ALLINVENTORYENTRIES.BILLEDQTY</FETCH>
<FETCH>ALLINVENTORYENTRIES.RATE</FETCH>
<FETCH>ALLINVENTORYENTRIES.AMOUNT</FETCH>
<FILTER NAME="N8NPOFilter">$VoucherNumber = "${xmlEscape(poNumber)}"</FILTER>
</COLLECTION>
</TDLMESSAGE>
</TDL>
</DESC>
</BODY>
</ENVELOPE>`;

  try {
    const result = await tallyPost(xml);
    res.status(result.ok ? 200 : 502).json({
      ok: result.ok,
      poNumber,
      company: TALLY_COMPANY,
      response: result.text
    });
  } catch (error) {
    res.status(502).json({ ok: false, error: error.message });
  }
});

app.post("/api/invoice/process", upload.single("data"), async (req, res) => {
  if (!req.file) return res.status(400).json({ error: "Attach the invoice PDF using field name 'data'." });

  try {
    const form = new FormData();
    form.append("data", new Blob([req.file.buffer], { type: req.file.mimetype || "application/pdf" }), req.file.originalname);

    for (const [key, value] of Object.entries(req.body || {})) {
      form.append(key, String(value));
    }

    const response = await fetch(N8N_WEBHOOK, { method: "POST", body: form });
    const text = await response.text();

    let payload;
    try { payload = JSON.parse(text); } catch { payload = { raw: text }; }

    res.status(response.ok ? 200 : 502).json({
      ok: response.ok,
      n8nStatus: response.status,
      result: payload
    });
  } catch (error) {
    res.status(502).json({ ok: false, error: error.message, n8nWebhook: N8N_WEBHOOK });
  }
});

app.post("/api/reconcile", (req, res) => {
  const { invoice, purchaseOrder } = req.body || {};
  if (!invoice || !purchaseOrder) return res.status(400).json({ error: "invoice and purchaseOrder are required" });

  const normalize = v => String(v ?? "").toLowerCase().replace(/[^a-z0-9]/g, "");
  const num = v => Number(String(v ?? "").replace(/,/g, "").replace(/[^0-9.-]/g, "")) || 0;
  const discrepancies = [];

  if (invoice.purchase_order_number && purchaseOrder.purchaseOrderNumber &&
      normalize(invoice.purchase_order_number) !== normalize(purchaseOrder.purchaseOrderNumber)) {
    discrepancies.push({ type: "PO_NUMBER_MISMATCH", severity: "HIGH" });
  }

  if (invoice.vendor_name && purchaseOrder.vendor &&
      normalize(invoice.vendor_name) !== normalize(purchaseOrder.vendor)) {
    discrepancies.push({ type: "VENDOR_MISMATCH", severity: "HIGH", invoice: invoice.vendor_name, po: purchaseOrder.vendor });
  }

  const invLines = invoice.line_items || [];
  const poLines = purchaseOrder.lineItems || [];

  for (const line of invLines) {
    const match = poLines.find(p =>
      (line.sku && p.sku && normalize(line.sku) === normalize(p.sku)) ||
      (line.description && p.description && normalize(line.description) === normalize(p.description))
    );

    if (!match) {
      discrepancies.push({ type: "LINE_NOT_ON_PO", severity: "HIGH", description: line.description });
      continue;
    }

    if (num(line.quantity) > num(match.quantity)) {
      discrepancies.push({
        type: "QUANTITY_OVER_PO",
        severity: "HIGH",
        description: line.description,
        invoiceQuantity: num(line.quantity),
        poQuantity: num(match.quantity)
      });
    }

    if (num(match.rate) && Math.abs(num(line.unit_price) - num(match.rate)) > 0.01) {
      discrepancies.push({
        type: "PRICE_MISMATCH",
        severity: "MEDIUM",
        description: line.description,
        invoicePrice: num(line.unit_price),
        poPrice: num(match.rate)
      });
    }
  }

  res.json({
    status: discrepancies.length ? "DISCREPANCY" : "MATCHED",
    discrepancyCount: discrepancies.length,
    discrepancies,
    checkedAt: new Date().toISOString()
  });
});

app.get("*", (_req, res) => {
  res.sendFile(path.join(__dirname, "public", "index.html"));
});

app.listen(PORT, () => {
  console.log(`MICRONET Invoice UI running at http://localhost:${PORT}`);
  console.log(`Tally: ${TALLY_URL} | Company: ${TALLY_COMPANY}`);
  console.log(`n8n webhook: ${N8N_WEBHOOK}`);
});
