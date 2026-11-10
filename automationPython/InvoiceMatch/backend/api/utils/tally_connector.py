import socket
import json
import logging
import xml.etree.ElementTree as ET
from datetime import datetime
from typing import Dict, Any, Optional

logger = logging.getLogger(__name__)

class TallyConnector:
    """
    Connector for Tally ERP 9 using TCP/IP socket connection
    """
    
    def __init__(self, host: str = 'localhost', port: int = 9000, timeout: int = 10):
        self.host = host
        self.port = port
        self.timeout = timeout
    
    def _send_request(self, request: str) -> Optional[str]:
        """
        Send XML request to Tally and return response
        """
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(self.timeout)
            sock.connect((self.host, self.port))
            
            # Send request
            sock.send(request.encode('utf-8'))
            
            # Receive response
            response = sock.recv(8192).decode('utf-8')
            sock.close()
            
            return response
            
        except socket.timeout:
            logger.error("Tally connection timeout")
            return None
        except ConnectionRefusedError:
            logger.error("Tally connection refused")
            return None
        except Exception as e:
            logger.error(f"Tally connection error: {str(e)}")
            return None
    
    def test_connection(self, company_name: Optional[str] = None) -> Dict[str, Any]:
        """
        Test connection to Tally
        """
        xml_request = f"""<ENVELOPE>
            <HEADER>
                <TALLYREQUEST>Test Connection</TALLYREQUEST>
            </HEADER>
            <BODY>
                <DESC>
                    <STATICVARIABLES>
                        <SVEXPORTFORMAT>XML</SVEXPORTFORMAT>
                    </STATICVARIABLES>
                </DESC>
            </BODY>
        </ENVELOPE>"""
        
        response = self._send_request(xml_request)
        
        if response is None:
            return {
                'success': False,
                'error': 'Connection failed'
            }
        
        # Parse response
        try:
            root = ET.fromstring(response)
            return {
                'success': True,
                'message': 'Tally connection successful',
                'data': response
            }
        except ET.ParseError:
            return {
                'success': False,
                'error': 'Invalid response from Tally',
                'raw_response': response
            }
    
    def lookup_po(self, po_number: str, company_name: Optional[str] = None) -> Dict[str, Any]:
        """
        Look up a purchase order in Tally
        """
        xml_request = f"""<ENVELOPE>
            <HEADER>
                <TALLYREQUEST>Export</TALLYREQUEST>
            </HEADER>
            <BODY>
                <DESC>
                    <STATICVARIABLES>
                        <SVEXPORTFORMAT>XML</SVEXPORTFORMAT>
                        <SVCURRENTCOMPANY>{company_name or ''}</SVCURRENTCOMPANY>
                    </STATICVARIABLES>
                    <TDL>
                        <TDLMESSAGE>
                            <COLLECTION NAME="PurchaseOrder">
                                <FETCH TYPE="Purchase Order">
                                    <FILTERS>
                                        <EQ NAME="Order No" VALUE="{po_number}"/>
                                    </FILTERS>
                                </FETCH>
                            </COLLECTION>
                        </TDLMESSAGE>
                    </TDL>
                </DESC>
            </BODY>
        </ENVELOPE>"""
        
        response = self._send_request(xml_request)
        
        if response is None:
            return {
                'success': False,
                'error': 'Failed to connect to Tally'
            }
        
        # Parse the XML response
        try:
            root = ET.fromstring(response)
            
            # Extract purchase order details
            # This is simplified - actual parsing would depend on Tally's XML structure
            po_data = {
                'success': True,
                'data': {
                    'po_number': po_number,
                    'found': True,
                    'status': 'found'
                }
            }
            
            # Try to extract more details
            for order in root.findall('.//PURCHASEORDER'):
                order_no = order.find('.//ORDERNO')
                if order_no is not None and order_no.text == po_number:
                    # Extract supplier
                    supplier = order.find('.//PARTYNAME')
                    if supplier is not None:
                        po_data['data']['supplier_name'] = supplier.text
                    
                    # Extract total amount
                    total = order.find('.//TOTALAMOUNT')
                    if total is not None:
                        po_data['data']['total_amount'] = float(total.text)
                    
                    # Extract items
                    items = []
                    for item in order.findall('.//ITEM'):
                        item_data = {}
                        name = item.find('.//NAME')
                        if name is not None:
                            item_data['name'] = name.text
                        qty = item.find('.//QUANTITY')
                        if qty is not None:
                            item_data['quantity'] = float(qty.text)
                        rate = item.find('.//RATE')
                        if rate is not None:
                            item_data['rate'] = float(rate.text)
                        amount = item.find('.//AMOUNT')
                        if amount is not None:
                            item_data['amount'] = float(amount.text)
                        items.append(item_data)
                    
                    if items:
                        po_data['data']['items'] = items
                    
                    break
            
            if 'data' not in po_data:
                po_data['data'] = {
                    'po_number': po_number,
                    'found': False,
                    'status': 'not_found'
                }
            
            return po_data
            
        except ET.ParseError as e:
            return {
                'success': False,
                'error': f'Invalid XML response from Tally: {str(e)}',
                'raw_response': response
            }