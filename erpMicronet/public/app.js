
const $ = id => document.getElementById(id);
const state = { processed: 0, matched: 0, exceptions: 0 };

document.querySelectorAll(".nav").forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".nav").forEach(x => x.classList.remove("active"));
    document.querySelectorAll(".view").forEach(x => x.classList.remove("active"));
    btn.classList.add("active");
    $(btn.dataset.view).classList.add("active");
    const titles = {
      dashboard:["AP Dashboard","Invoice extraction, PO matching and Tally integration"],
      invoice:["Process Invoice","Send an invoice through the active n8n workflow"],
      po:["Purchase Orders","Read-only Purchase Order lookup against Tally"],
      reconcile:["Reconciliation","Test invoice-to-PO matching"],
      settings:["Connections","Live API endpoints and connection status"]
    };
    $("title").textContent = titles[btn.dataset.view][0];
    $("subtitle").textContent = titles[btn.dataset.view][1];
    if(btn.dataset.view === "settings") loadConfig();
  });
});

function show(el, data) {
  el.classList.remove("hidden");
  el.textContent = typeof data === "string" ? data : JSON.stringify(data, null, 2);
}

async function checkTally() {
  $("tallyStatus").textContent = "Checking Tally…";
  try {
    const r = await fetch("/api/health/tally");
    const d = await r.json();
    const good = d.connected;
    $("tallyDot").className = "dot " + (good ? "good" : "bad");
    $("tallyStatus").textContent = good ? "Tally connected" : "Tally error";
    $("tallyCard").textContent = good ? "ONLINE" : "OFFLINE";
  } catch(e) {
    $("tallyDot").className = "dot bad";
    $("tallyStatus").textContent = "Backend unavailable";
    $("tallyCard").textContent = "OFFLINE";
  }
}

async function processInvoice() {
  const file = $("invoiceFile").files[0];
  if(!file) return show($("invoiceResult"), "Choose a PDF invoice first.");
  const form = new FormData();
  form.append("data", file, file.name);
  const result = $("invoiceResult");
  result.classList.remove("hidden");
  result.textContent = "Sending invoice to n8n…";
  try {
    const r = await fetch("/api/invoice/process", {method:"POST", body:form});
    const d = await r.json();
    state.processed++;
    $("processed").textContent = state.processed;
    if(d.result?.status === "POSTED" || d.result?.reconciliation?.status === "MATCHED") state.matched++;
    if(d.result?.status === "EXCEPTION" || d.result?.reconciliation?.status === "DISCREPANCY") state.exceptions++;
    $("matched").textContent = state.matched;
    $("exceptions").textContent = state.exceptions;
    show(result, d);
  } catch(e) { show(result, {error:e.message}); }
}

async function lookupPO() {
  const poNumber = $("poNumber").value.trim();
  if(!poNumber) return show($("poResult"), "Enter a PO number.");
  const result = $("poResult");
  result.classList.remove("hidden");
  result.textContent = "Querying Tally…";
  try {
    const r = await fetch("/api/tally/po", {
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify({poNumber})
    });
    show(result, await r.json());
  } catch(e) { show(result, {error:e.message}); }
}

async function reconcile() {
  const result = $("reconcileResult");
  try {
    const invoice = JSON.parse($("invoiceJson").value);
    const purchaseOrder = JSON.parse($("poJson").value);
    const r = await fetch("/api/reconcile", {
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify({invoice,purchaseOrder})
    });
    const d = await r.json();
    if(d.status === "MATCHED") state.matched++; else state.exceptions++;
    $("matched").textContent = state.matched;
    $("exceptions").textContent = state.exceptions;
    show(result, d);
  } catch(e) { show(result, {error:e.message}); }
}

async function loadConfig() {
  try {
    const r = await fetch("/api/config");
    const c = await r.json();
    $("config").innerHTML = `
      <div><b>Tally endpoint</b><br>${c.tallyUrl}</div>
      <div><b>Tally company</b><br>${c.company}</div>
      <div><b>n8n webhook</b><br>${c.n8nWebhook}</div>
    `;
  } catch(e) { $("config").textContent = e.message; }
}

checkTally();
