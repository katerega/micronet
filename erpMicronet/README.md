
# MICRONET Invoice Processing UI

A working local web UI for:
- Tally connectivity check
- PDF invoice upload to the n8n invoice webhook
- Tally Purchase Order lookup
- Invoice-vs-PO reconciliation testing
- Live endpoint display

## Run

Requirements:
- Node.js 18+
- n8n reachable at http://localhost:5678
- Tally HTTP server reachable from the Node process

From this folder:

    npm install
    npm start

Open:

    http://localhost:3000

## Environment variables

Windows PowerShell:

    $env:N8N_WEBHOOK="http://localhost:5678/webhook/invoice-processing"
    $env:TALLY_URL="http://host.docker.internal:9000"
    $env:TALLY_COMPANY="MICRONET"
    npm start

If this Node app is running directly on Windows, TALLY_URL can instead be:

    http://localhost:9000

## Important

The UI proxies Tally through the Node backend, avoiding browser CORS restrictions.

The invoice upload uses multipart field `data`, matching the n8n workflow's binary property convention.

The Tally PO query is read-only. Automatic Tally voucher creation is intentionally not enabled in this version.
