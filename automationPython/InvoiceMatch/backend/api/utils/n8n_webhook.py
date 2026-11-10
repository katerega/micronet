import requests
import json
import logging
from typing import Dict, Any
import os

logger = logging.getLogger(__name__)

class N8nWebhook:
    """
    Client for n8n webhook integration
    """
    
    def __init__(self, webhook_url: str):
        self.webhook_url = webhook_url
    
    def test_connection(self) -> Dict[str, Any]:
        """
        Test n8n webhook connection
        """
        try:
            response = requests.post(
                self.webhook_url,
                json={'test': True},
                headers={'Content-Type': 'application/json'},
                timeout=10
            )
            
            if response.status_code == 200:
                return {
                    'success': True,
                    'message': 'n8n webhook is reachable',
                    'status_code': response.status_code
                }
            else:
                return {
                    'success': False,
                    'error': f'HTTP {response.status_code}: {response.text}'
                }
        except requests.RequestException as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def process_invoice(self, file_path: str) -> Dict[str, Any]:
        """
        Send invoice to n8n for processing
        """
        try:
            with open(file_path, 'rb') as f:
                files = {'file': f}
                response = requests.post(
                    self.webhook_url,
                    files=files,
                    timeout=30
                )
            
            if response.status_code == 200:
                data = response.json()
                return {
                    'success': True,
                    'data': data
                }
            else:
                return {
                    'success': False,
                    'error': f'HTTP {response.status_code}: {response.text}'
                }
                
        except FileNotFoundError:
            return {
                'success': False,
                'error': f'File not found: {file_path}'
            }
        except requests.RequestException as e:
            return {
                'success': False,
                'error': str(e)
            }
        except json.JSONDecodeError as e:
            return {
                'success': False,
                'error': f'Invalid JSON response: {str(e)}'
            }