Procurement system
ssh root@62.171.155.173
http://62.171.155.173:5678/micronet
pwd QFQu01NDwU3Iwb3Nc3TBUduwCFw

on every change run
npm run build
sudo systemctl restart nginx

/var/www/micronet/src/App.js
react applicaiton http://62.171.155.173:3000/micronet
n8nworkflow http://62.171.155.173:5678/workflow/w1S0hkUKd6xwl3RE

access locally
http://localhost:3000


70*2m ugx

60 workflows = 60*0.2m ugx = 12m ugx full system
PROCUREMENT MODULE (12 Workflows)
WF-P01 Product Requirement Entry
User enters product request.
AI extracts specifications.
AI identifies category.
AI stores request.
AI triggers supplier search.
WF-P02 Local Supplier Search
AI searches Uganda suppliers.
Collects pricing.
Collects stock availability.
Collects lead times.
Ranks suppliers.
WF-P03 International Supplier Search
Search Dubai suppliers.
Search manufacturers.
Search distributors.
Search Alibaba.
Search websites.
Store results.
WF-P04 Supplier Communication Agent
Generate inquiry.
Send email.
Send WhatsApp.
Track responses.
Follow-up automatically.
WF-P05 Supplier Comparison
Collect quotations.
Compare price.
Compare delivery.
Compare warranty.
Compare supplier score.
WF-P06 Landed Cost Calculation
Product cost.
Freight cost.
Insurance.
Import duty.
VAT.
Clearing charges.
Profit margin.
WF-P07 Quotation Generation
Generate Quote A.
Generate Quote B.
Generate Quote C.
Generate PDF.
Email customer.
WF-P08 LPO Processing
Receive LPO.
Verify approval.
Create procurement task.
Notify procurement team.
WF-P09 Supplier Purchase Order
Generate supplier PO.
Send PO.
Await confirmation.
Track status.
WF-P10 Delivery Tracking
Track shipment.
Update ETA.
Notify customer.
Escalate delays.
WF-P11 Credit Monitoring
Start credit timer.
21-day count.
3-day reminder.
Payment follow-up.
WF-P12 Procurement Performance Analysis
Analyze suppliers.
Analyze margins.
Analyze savings.
Recommend improvements.
SALES MODULE (10 Workflows)
WF-S01 Lead Capture
Email lead.
WhatsApp lead.
Website lead.
Create CRM record.
WF-S02 Lead Qualification
Analyze organization.
Analyze website.
Analyze industry.
Score lead.
WF-S03 Lead Assignment
Evaluate sales team.
Assign salesperson.
Notify salesperson.
WF-S04 AI Sales Follow-up
Email customer.
WhatsApp customer.
Call scheduling.
Follow-up tracking.
WF-S05 Quotation Workflow
Request quotation.
Supplier search.
Cost analysis.
Generate quote.
WF-S06 Opportunity Pipeline
Prospect.
Qualification.
Quotation.
Negotiation.
Closing.
WF-S07 Customer Support
Ticket creation.
AI response.
Escalation.
Resolution.
WF-S08 Delivery Follow-up
Monitor order.
Notify customer.
Delivery confirmation.
WF-S09 Customer Retention
Detect inactive customer.
Generate offers.
Schedule engagement.
WF-S10 Sales Performance Reporting
Leads.
Quotations.
Orders.
Revenue.
Conversion rate.
TENDER MANAGEMENT MODULE (10 Workflows)
WF-T01 Tender Discovery
Scan websites.
Scan portals.
Scan email.
Store opportunities.
WF-T02 Tender Qualification Analysis
Analyze requirements.
Check eligibility.
Score probability.
WF-T03 Tender Alerting
Notify management.
Notify sales.
Notify tender team.
WF-T04 Compliance Requirement Extraction
Extract documents.
Extract deadlines.
Extract qualifications.
WF-T05 Tender Document Generation
Generate cover letter.
Generate technical proposal.
Generate financial proposal.
WF-T06 Product Configuration
Analyze specifications.
Match products.
Match model numbers.
WF-T07 Tender Costing
Supplier sourcing.
Cost calculation.
Margin optimization.
WF-T08 Document Repository
VAT certificate.
Tax clearance.
Registration.
Licenses.
WF-T09 Submission Workflow
Internal approval.
Packaging.
Submission.
Tracking.
WF-T10 Award Monitoring
Monitor results.
Track contracts.
Generate award reports.
ACCOUNTS MODULE (10 Workflows)
WF-A01 Invoice Creation
Generate invoice.
Customer approval.
Send invoice.
WF-A02 Receipt Processing
Receive payment.
Match invoice.
Generate receipt.
WF-A03 EFRIS Invoice Generation
Generate EFRIS.
Submit.
Store reference.
WF-A04 EFRIS Invoice Import
Receive invoice.
Validate.
Post accounting entry.
WF-A05 Bank Statement Upload
Upload statement.
Parse transactions.
Match records.
WF-A06 Bank Reconciliation
Compare books.
Detect missing entries.
Suggest corrections.
WF-A07 VAT Analysis
Calculate input VAT.
Calculate output VAT.
Determine payable VAT.
WF-A08 Stock Accounting
Purchases.
Sales.
Stock movements.
WF-A09 Profitability Analysis
Product profits.
Customer profits.
Department profits.
WF-A10 Financial Reporting
P&L.
Cashflow.
Balance Sheet.
TECHNICAL SUPPORT MODULE (6 Workflows)
WF-TS01 Job Card Creation
Receive item.
Create job card.
Assign technician.
WF-TS02 Repair Tracking
Update progress.
Notify customer.
WF-TS03 Warranty Verification
Check warranty.
Approve claim.
WF-TS04 Spare Parts Procurement
Request parts.
Procurement approval.
Supplier sourcing.
WF-TS05 Service Completion
Verify repair.
Customer acceptance.
Close job.
WF-TS06 Technical Performance Reporting
Jobs completed.
Repair times.
SLA compliance.
HR & PAYROLL MODULE (5 Workflows)
WF-HR01 Attendance Management
Check-in.
Check-out.
Attendance report.
WF-HR02 Leave Management
Request leave.
Approval.
Payroll adjustment.
WF-HR03 Payroll Processing
Salary.
Loan deduction.
NSSF.
PAYE.
WF-HR04 Staff Performance Analysis
KPIs.
Targets.
Ratings.
WF-HR05 Recruitment Workflow
Job posting.
Applicant screening.
Interview scheduling.
EXECUTIVE AI MODULE (7 Workflows)
WF-E01 Executive Dashboard

Real-time KPIs.

WF-E02 AI Question Answering

Natural language business questions.

WF-E03 Procurement Intelligence

Supplier insights.

WF-E04 Sales Intelligence

Revenue forecasting.

WF-E05 Financial Intelligence

Profit and cashflow analysis.

WF-E06 Tender Intelligence

Upcoming tenders and opportunities.

WF-E07 Risk Management

Fraud detection, overdue payments, supplier risk.

END-TO-END BUSINESS PROCESS (MASTER WORKFLOW)

The complete sales-to-delivery workflow:

Lead → AI Qualification → Supplier Search → Costing → Quotation → Customer Approval → LPO → Procurement → Supplier Purchase Order → Shipping → Delivery → Installation → Invoice → EFRIS → Payment Collection → Bank Reconciliation → Profit Analysis → Customer Support → Warranty → Repeat Sale.

 250–300 database tables, 100+ AI agents, 50 dashboards, and 200+ API integrations for a full enterprise-grade AI ERP platform.