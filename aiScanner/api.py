import json
import os
import time
import mimetypes
import base64
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_http_methods
from websiteFunctions.models import Websites
from loginSystem.models import Administrator
from .models import FileAccessToken, ScanHistory
from plogical.CyberCPLogFileWriter import CyberCPLogFileWriter as logging


class SecurityError(Exception):
    """Custom exception for security violations"""
    pass


def validate_access_token(token, scan_id):
    """
    Implement proper token validation
    - Check token format
    - Verify token hasn't expired
    - Confirm token is for the correct scan
    - Log access attempts
    """
    try:
        if not token or not token.startswith('cp_'):
            logging.writeToFile(f'[API] Invalid token format: {token[:20] if token else "None"}...')
            return None, "Invalid token format"

        # Find the token in database
        try:
            file_token = FileAccessToken.objects.get(
                token=token,
                scan_history__scan_id=scan_id,
                is_active=True
            )
            
            if file_token.is_expired():
                logging.writeToFile(f'[API] Token expired for scan {scan_id}')
                return None, "Token expired"
            
            logging.writeToFile(f'[API] Token validated successfully for scan {scan_id}')
            return file_token, None
            
        except FileAccessToken.DoesNotExist:
            logging.writeToFile(f'[API] Token not found for scan {scan_id}')
            return None, "Invalid token"
            
    except Exception as e:
        logging.writeToFile(f'[API] Token validation error: {str(e)}')
        return None, "Token validation failed"


def secure_path_check(base_path, requested_path):
    """
    Ensure requested path is within allowed directory
    Prevent directory traversal attacks
    """
    try:
        if requested_path:
            full_path = os.path.join(base_path, requested_path.strip('/'))
        else:
            full_path = base_path
            
        full_path = os.path.abspath(full_path)
        base_path = os.path.abspath(base_path)

        if not full_path.startswith(base_path):
            raise SecurityError("Path outside allowed directory")

        return full_path
    except Exception as e:
        raise SecurityError(f"Path security check failed: {str(e)}")


@csrf_exempt
@require_http_methods(['POST'])
def authenticate_worker(request):
    """
    POST /api/ai-scanner/authenticate
    
    Request Body:
    {
        "access_token": "cp_access_abc123...",
        "scan_id": "550e8400-e29b-41d4-a716-446655440000",
        "worker_id": "scanner-1.domain.com"
    }
    
    Response:
    {
        "success": true,
        "site_info": {
            "domain": "client-domain.com",
            "wp_path": "/home/client/public_html",
            "php_version": "8.1",
            "wp_version": "6.3.1"
        },
        "permissions": ["read_files", "list_directories"],
        "expires_at": "2024-12-25T11:00:00Z"
    }
    """
    try:
        data = json.loads(request.body)
        access_token = data.get('access_token')
        scan_id = data.get('scan_id')
        worker_id = data.get('worker_id', 'unknown')
        
        logging.writeToFile(f'[API] Authentication request from worker {worker_id} for scan {scan_id}')
        
        if not access_token or not scan_id:
            return JsonResponse({'error': 'Missing access_token or scan_id'}, status=400)

        # Validate access token
        file_token, error = validate_access_token(access_token, scan_id)
        if error:
            return JsonResponse({'error': error}, status=401)
        
        # Get website info
        try:
            website = Websites.objects.get(domain=file_token.domain)
            
            # Get WordPress info
            wp_path = file_token.wp_path
            wp_version = 'Unknown'
            php_version = 'Unknown'
            
            # Try to get WP version from wp-includes/version.php using ProcessUtilities
            version_file = os.path.join(wp_path, 'wp-includes', 'version.php')
            try:
                from plogical.processUtilities import ProcessUtilities
                
                # Use ProcessUtilities to read file as the website user
                command = f'cat "{version_file}"'
                result = ProcessUtilities.outputExecutioner(command, user=website.externalApp, retRequired=True)
                
                if result[1]:  # Check if there's content (ignore return code)
                    content = result[1]
                    import re
                    match = re.search(r'\$wp_version\s*=\s*[\'"]([^\'"]+)[\'"]', content)
                    if match:
                        wp_version = match.group(1)
                        logging.writeToFile(f'[API] Detected WordPress version: {wp_version}')
                else:
                    logging.writeToFile(f'[API] Could not read WP version file: {result[1] if len(result) > 1 else "No content returned"}')
                    
            except Exception as e:
                logging.writeToFile(f'[API] Error reading WP version: {str(e)}')
            
            # Try to detect PHP version (basic detection)
            try:
                import subprocess
                result = subprocess.run(['php', '-v'], capture_output=True, text=True, timeout=5)
                if result.returncode == 0:
                    import re
                    match = re.search(r'PHP (\d+\.\d+)', result.stdout)
                    if match:
                        php_version = match.group(1)
            except Exception:
                pass
            
            response_data = {
                'success': True,
                'site_info': {
                    'domain': file_token.domain,
                    'wp_path': wp_path,
                    'php_version': php_version,
                    'wp_version': wp_version,
                    'scan_id': scan_id
                },
                'permissions': ['read_files', 'list_directories'],
                'expires_at': file_token.expires_at.strftime('%Y-%m-%dT%H:%M:%SZ')
            }
            
            logging.writeToFile(f'[API] Authentication successful for {file_token.domain}')
            return JsonResponse(response_data)
            
        except Websites.DoesNotExist:
            logging.writeToFile(f'[API] Website not found: {file_token.domain}')
            return JsonResponse({'error': 'Website not found'}, status=404)
            
    except json.JSONDecodeError:
        return JsonResponse({'error': 'Invalid JSON'}, status=400)
    except Exception as e:
        logging.writeToFile(f'[API] Authentication error: {str(e)}')
        return JsonResponse({'error': 'Authentication failed'}, status=500)


@csrf_exempt  
@require_http_methods(['GET'])
def list_files(request):
    """
    GET /api/ai-scanner/files/list?path=wp-content/plugins
    
    Headers:
    Authorization: Bearer cp_access_abc123...
    X-Scan-ID: 550e8400-e29b-41d4-a716-446655440000
    
    Response:
    {
        "path": "wp-content/plugins",
        "items": [
            {
                "name": "akismet",
                "type": "directory",
                "modified": "2024-12-20T10:30:00Z"
            },
            {
                "name": "suspicious-plugin.php",
                "type": "file",
                "size": 15420,
                "modified": "2024-12-24T15:20:00Z",
                "permissions": "644"
            }
        ]
    }
    """
    try:
        # Validate authorization
        auth_header = request.META.get('HTTP_AUTHORIZATION', '')
        if not auth_header.startswith('Bearer '):
            return JsonResponse({'error': 'Missing or invalid Authorization header'}, status=401)
        
        access_token = auth_header.replace('Bearer ', '')
        scan_id = request.META.get('HTTP_X_SCAN_ID', '')
        
        if not scan_id:
            return JsonResponse({'error': 'X-Scan-ID header required'}, status=400)

        # Validate access token
        file_token, error = validate_access_token(access_token, scan_id)
        if error:
            return JsonResponse({'error': error}, status=401)

        # Get parameters
        path = request.GET.get('path', '').strip('/')
        
        try:
            # Security check and get full path
            full_path = secure_path_check(file_token.wp_path, path)
            
            # Path existence and type checking will be done by ProcessUtilities

            # List directory contents using ProcessUtilities
            items = []
            try:
                from plogical.processUtilities import ProcessUtilities
                from websiteFunctions.models import Websites
                
                # Get website object for user context
                try:
                    website = Websites.objects.get(domain=file_token.domain)
                    user = website.externalApp
                except Websites.DoesNotExist:
                    return JsonResponse({'error': 'Website not found'}, status=404)
                
                # Use ls command with ProcessUtilities to list directory as website user
                ls_command = f'ls -la "{full_path}"'
                result = ProcessUtilities.outputExecutioner(ls_command, user=user, retRequired=True)
                
                if result[1]:  # Check if there's content (ignore return code)
                    lines = result[1].strip().split('\n')
                    for line in lines[1:]:  # Skip the 'total' line
                        if not line.strip():
                            continue
                            
                        parts = line.split()
                        if len(parts) < 9:
                            continue
                            
                        permissions = parts[0]
                        size = parts[4] if parts[4].isdigit() else 0
                        name = ' '.join(parts[8:])  # Handle filenames with spaces
                        
                        # Skip hidden files, current/parent directory entries
                        if name.startswith('.') or name in ['.', '..'] or name in ['__pycache__', 'node_modules']:
                            continue
                        
                        item_data = {
                            'name': name,
                            'type': 'directory' if permissions.startswith('d') else 'file',
                            'permissions': permissions[1:4] if len(permissions) >= 4 else '644'
                        }
                        
                        if permissions.startswith('-'):  # Regular file
                            try:
                                item_data['size'] = int(size)
                            except ValueError:
                                item_data['size'] = 0
                                
                            # Only include certain file types
                            if name.endswith(('.php', '.js', '.html', '.htm', '.css', '.txt', '.md', '.json', '.xml', '.sql', '.log', '.conf', '.ini', '.yml', '.yaml')):
                                items.append(item_data)
                        elif permissions.startswith('d'):  # Directory
                            # Directories don't have a size in the same way
                            item_data['size'] = 0
                            items.append(item_data)
                        else:
                            # Other file types (links, etc.) - include with size 0
                            item_data['size'] = 0
                            items.append(item_data)
                else:
                    logging.writeToFile(f'[API] Directory listing failed: {result[1] if len(result) > 1 else "No content returned"}')
                    return JsonResponse({'error': 'Directory access failed'}, status=403)

            except Exception as e:
                logging.writeToFile(f'[API] Directory listing error: {str(e)}')
                return JsonResponse({'error': 'Directory access failed'}, status=403)

            logging.writeToFile(f'[API] Listed {len(items)} items in {path or "root"} for scan {scan_id}')
            
            return JsonResponse({
                'path': path,
                'items': sorted(items, key=lambda x: (x['type'] == 'file', x['name'].lower()))
            })
            
        except SecurityError as e:
            logging.writeToFile(f'[API] Security violation: {str(e)}')
            return JsonResponse({'error': 'Path not allowed'}, status=403)

    except Exception as e:
        logging.writeToFile(f'[API] List files error: {str(e)}')
        return JsonResponse({'error': 'Internal server error'}, status=500)


@csrf_exempt
@require_http_methods(['GET']) 
def get_file_content(request):
    """
    GET /api/ai-scanner/files/content?path=wp-content/plugins/plugin.php
    
    Headers:
    Authorization: Bearer cp_access_abc123...
    X-Scan-ID: 550e8400-e29b-41d4-a716-446655440000
    
    Response:
    {
        "path": "wp-content/plugins/plugin.php",
        "content": "<?php\n// Plugin code here...",
        "size": 15420,
        "encoding": "utf-8",
        "mime_type": "text/x-php"
    }
    """
    try:
        # Validate authorization
        auth_header = request.META.get('HTTP_AUTHORIZATION', '')
        if not auth_header.startswith('Bearer '):
            return JsonResponse({'error': 'Missing or invalid Authorization header'}, status=401)
        
        access_token = auth_header.replace('Bearer ', '')
        scan_id = request.META.get('HTTP_X_SCAN_ID', '')
        
        if not scan_id:
            return JsonResponse({'error': 'X-Scan-ID header required'}, status=400)

        # Get file path
        path = request.GET.get('path', '').strip('/')
        if not path:
            return JsonResponse({'error': 'File path required'}, status=400)

        # Validate access token
        file_token, error = validate_access_token(access_token, scan_id)
        if error:
            return JsonResponse({'error': error}, status=401)

        try:
            # Security check and get full path
            full_path = secure_path_check(file_token.wp_path, path)

            # File existence, type, and size checking will be done by ProcessUtilities

            # Only allow specific file types for security
            allowed_extensions = {
                '.php', '.js', '.html', '.htm', '.css', '.txt', '.md',
                '.json', '.xml', '.sql', '.log', '.conf', '.ini', '.yml', '.yaml'
            }

            file_ext = os.path.splitext(full_path)[1].lower()
            if file_ext not in allowed_extensions:
                return JsonResponse({'error': f'File type not allowed: {file_ext}'}, status=403)

            # Read file content using ProcessUtilities
            try:
                from plogical.processUtilities import ProcessUtilities
                from websiteFunctions.models import Websites
                
                # Get website object for user context
                try:
                    website = Websites.objects.get(domain=file_token.domain)
                    user = website.externalApp
                except Websites.DoesNotExist:
                    return JsonResponse({'error': 'Website not found'}, status=404)
                
                # Check file size first using stat command
                stat_command = f'stat -c %s "{full_path}"'
                stat_result = ProcessUtilities.outputExecutioner(stat_command, user=user, retRequired=True)
                
                if stat_result[1]:  # Check if there's content (ignore return code)
                    try:
                        file_size = int(stat_result[1].strip())
                        if file_size > 10 * 1024 * 1024:  # 10MB limit
                            return JsonResponse({'error': 'File too large (max 10MB)'}, status=400)
                    except ValueError:
                        logging.writeToFile(f'[API] Could not parse file size: {stat_result[1]}')
                        file_size = 0
                else:
                    logging.writeToFile(f'[API] Could not get file size: {stat_result[1] if len(stat_result) > 1 else "No content returned"}')
                    return JsonResponse({'error': 'File not found or inaccessible'}, status=404)
                
                # Use cat command with ProcessUtilities to read file as website user
                cat_command = f'cat "{full_path}"'
                result = ProcessUtilities.outputExecutioner(cat_command, user=user, retRequired=True)
                
                # Check if content was returned (file might be empty, which is valid)
                if len(result) > 1:  # We got a tuple back
                    content = result[1] if result[1] is not None else ''
                    encoding = 'utf-8'
                else:
                    logging.writeToFile(f'[API] File read failed: No result returned')
                    return JsonResponse({'error': 'Unable to read file'}, status=400)

            except Exception as e:
                logging.writeToFile(f'[API] File read error: {str(e)}')
                return JsonResponse({'error': 'Unable to read file'}, status=400)

            # Detect MIME type
            mime_type, _ = mimetypes.guess_type(full_path)
            if not mime_type:
                if file_ext == '.php':
                    mime_type = 'text/x-php'
                elif file_ext == '.js':
                    mime_type = 'application/javascript'
                else:
                    mime_type = 'text/plain'

            # Base64 encode the content for safe transport
            try:
                content_base64 = base64.b64encode(content.encode('utf-8')).decode('utf-8')
            except UnicodeEncodeError:
                # Handle binary files or encoding issues
                try:
                    content_base64 = base64.b64encode(content.encode('latin-1')).decode('utf-8')
                    encoding = 'latin-1'
                except:
                    logging.writeToFile(f'[API] Failed to encode file content for {path}')
                    return JsonResponse({'error': 'File encoding not supported'}, status=400)

            logging.writeToFile(f'[API] File content retrieved: {path} ({file_size} bytes) for scan {scan_id}')

            return JsonResponse({
                'path': path,
                'content': content_base64,
                'size': file_size,
                'encoding': encoding,
                'mime_type': mime_type
            })
            
        except SecurityError as e:
            logging.writeToFile(f'[API] Security violation: {str(e)}')
            return JsonResponse({'error': 'Path not allowed'}, status=403)

    except Exception as e:
        logging.writeToFile(f'[API] Get file content error: {str(e)}')
        return JsonResponse({'error': 'Internal server error'}, status=500)


@csrf_exempt
@require_http_methods(['POST'])
def scan_callback(request):
    """
    Receive scan completion callbacks from AI Scanner platform
    
    POST /api/ai-scanner/callback
    Content-Type: application/json
    
    Expected payload:
    {
        "scan_id": "uuid",
        "status": "completed",
        "summary": {
            "threat_level": "HIGH|MEDIUM|LOW",
            "total_findings": 3,
            "files_scanned": 25,
            "cost": "$0.0456"
        },
        "findings": [
            {
                "file_path": "wp-content/plugins/file.php",
                "severity": "CRITICAL|HIGH|MEDIUM|LOW", 
                "title": "Issue title",
                "description": "Detailed description",
                "ai_confidence": 95
            }
        ],
        "ai_analysis": "AI summary text",
        "completed_at": "2025-06-23T11:40:12Z"
    }
    """
    try:
        # Parse JSON payload
        data = json.loads(request.body)

        scan_id = data.get('scan_id')
        status = data.get('status')
        summary = data.get('summary', {})
        findings = data.get('findings', [])
        ai_analysis = data.get('ai_analysis', '')
        completed_at = data.get('completed_at')

        logging.writeToFile(f"[API] Received callback for scan {scan_id}: {status}")

        # Update scan status in CyberPanel database
        try:
            from .models import ScanHistory
            from django.utils import timezone
            import datetime
            
            # Find the scan record
            scan_record = ScanHistory.objects.get(scan_id=scan_id)
            
            # Update scan record
            scan_record.status = status
            scan_record.issues_found = summary.get('total_findings', 0)
            scan_record.files_scanned = summary.get('files_scanned', 0)
            
            # Parse and store cost
            cost_str = summary.get('cost', '$0.00')
            try:
                # Remove '$' and convert to float
                cost_value = float(cost_str.replace('$', '').replace(',', ''))
                scan_record.cost_usd = cost_value
            except (ValueError, AttributeError):
                scan_record.cost_usd = 0.0
            
            # Store findings and AI analysis
            scan_record.set_findings(findings)
            
            # Build summary dict
            summary_dict = {
                'threat_level': summary.get('threat_level', 'UNKNOWN'),
                'total_findings': summary.get('total_findings', 0),
                'files_scanned': summary.get('files_scanned', 0),
                'ai_analysis': ai_analysis
            }
            scan_record.set_summary(summary_dict)
            
            # Set completion time
            if completed_at:
                try:
                    # Parse ISO format datetime
                    completed_datetime = datetime.datetime.fromisoformat(completed_at.replace('Z', '+00:00'))
                    scan_record.completed_at = completed_datetime
                except ValueError:
                    scan_record.completed_at = timezone.now()
            else:
                scan_record.completed_at = timezone.now()
            
            scan_record.save()
            
            # Also update the ScanStatusUpdate record with final statistics
            try:
                from .status_models import ScanStatusUpdate
                status_update, _ = ScanStatusUpdate.objects.get_or_create(scan_id=scan_id)
                status_update.phase = 'completed'
                status_update.progress = 100
                status_update.files_discovered = summary.get('files_scanned', 0)  # Use files_scanned as approximation
                status_update.files_scanned = summary.get('files_scanned', 0)
                status_update.files_remaining = 0
                status_update.threats_found = summary.get('total_findings', 0)
                # Extract critical and high threats from findings if available
                critical_count = 0
                high_count = 0
                for finding in findings:
                    severity = finding.get('severity', '').lower()
                    if severity == 'critical':
                        critical_count += 1
                    elif severity == 'high':
                        high_count += 1
                status_update.critical_threats = critical_count
                status_update.high_threats = high_count
                status_update.activity_description = f"Scan completed - {summary.get('total_findings', 0)} threats found"
                status_update.save()
                logging.writeToFile(f"[API] Updated ScanStatusUpdate for completed scan {scan_id}")
            except Exception as e:
                logging.writeToFile(f"[API] Error updating ScanStatusUpdate: {str(e)}")
            
            # Update user balance if scan cost money
            if scan_record.cost_usd > 0:
                try:
                    scanner_settings = scan_record.admin.ai_scanner_settings
                    if scanner_settings.balance >= scan_record.cost_usd:
                        # Convert to same type to avoid Decimal/float issues
                        scanner_settings.balance = float(scanner_settings.balance) - float(scan_record.cost_usd)
                        scanner_settings.save()
                        logging.writeToFile(f"[API] Deducted ${scan_record.cost_usd} from {scan_record.admin.userName} balance")
                    else:
                        logging.writeToFile(f"[API] Insufficient balance for scan cost: ${scan_record.cost_usd}")
                except Exception as e:
                    logging.writeToFile(f"[API] Error updating balance: {str(e)}")

            # Deactivate file access tokens for this scan
            try:
                from .models import FileAccessToken
                FileAccessToken.objects.filter(scan_history=scan_record).update(is_active=False)
                logging.writeToFile(f"[API] Deactivated file access tokens for scan {scan_id}")
            except Exception as e:
                logging.writeToFile(f"[API] Error deactivating tokens: {str(e)}")

            logging.writeToFile(f"[API] Scan {scan_id} completed successfully:")
            logging.writeToFile(f"[API]   Status: {status}")
            logging.writeToFile(f"[API]   Threat Level: {summary.get('threat_level')}")
            logging.writeToFile(f"[API]   Findings: {summary.get('total_findings')}")
            logging.writeToFile(f"[API]   Files Scanned: {summary.get('files_scanned')}")
            logging.writeToFile(f"[API]   Cost: {summary.get('cost')}")

        except ScanHistory.DoesNotExist:
            logging.writeToFile(f"[API] Scan record not found: {scan_id}")
            return JsonResponse({
                'status': 'error',
                'message': 'Scan record not found',
                'scan_id': scan_id
            }, status=404)
            
        except Exception as e:
            logging.writeToFile(f"[API] Failed to update scan record: {str(e)}")
            return JsonResponse({
                'status': 'error',
                'message': 'Failed to update scan record',
                'scan_id': scan_id
            }, status=500)

        # Return success response
        return JsonResponse({
            'status': 'success',
            'message': 'Callback received successfully',
            'scan_id': scan_id
        })

    except json.JSONDecodeError:
        logging.writeToFile("[API] Invalid JSON in callback request")
        return JsonResponse({
            'status': 'error',
            'message': 'Invalid JSON payload'
        }, status=400)

    except Exception as e:
        logging.writeToFile(f"[API] Callback processing error: {str(e)}")
        return JsonResponse({
            'status': 'error',
            'message': 'Internal server error'
        }, status=500)