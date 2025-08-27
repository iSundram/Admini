<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication and admin role
if (!$auth->isLoggedIn() || !$auth->hasPermission('admin')) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_zone':
                $domainId = (int)$_POST['domain_id'];
                $zoneName = trim($_POST['zone_name']);
                $ttl = (int)$_POST['ttl'];
                
                // Check if zone already exists
                $existing = $db->fetch(
                    "SELECT id FROM dns_zones WHERE domain_id = ? AND zone_name = ?",
                    [$domainId, $zoneName]
                );
                
                if ($existing) {
                    throw new Exception('DNS zone already exists for this domain');
                }
                
                // Create zone
                $serialNumber = date('Ymd') . '01';
                $db->query(
                    "INSERT INTO dns_zones (domain_id, zone_name, ttl, serial_number, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [$domainId, $zoneName, $ttl, $serialNumber]
                );
                
                $message = 'DNS zone created successfully';
                break;
                
            case 'create_record':
                $zoneId = (int)$_POST['zone_id'];
                $name = trim($_POST['name']);
                $type = $_POST['type'];
                $value = trim($_POST['value']);
                $ttl = (int)$_POST['ttl'];
                $priority = (int)$_POST['priority'];
                
                // Insert DNS record
                $db->query(
                    "INSERT INTO dns_records (zone_id, name, type, value, ttl, priority, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$zoneId, $name, $type, $value, $ttl, $priority]
                );
                
                // Update zone serial number
                $newSerial = date('Ymd') . str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
                $db->query(
                    "UPDATE dns_zones SET serial_number = ?, updated_at = NOW() WHERE id = ?",
                    [$newSerial, $zoneId]
                );
                
                $message = 'DNS record created successfully';
                break;
                
            case 'update_record':
                $recordId = (int)$_POST['record_id'];
                $name = trim($_POST['name']);
                $type = $_POST['type'];
                $value = trim($_POST['value']);
                $ttl = (int)$_POST['ttl'];
                $priority = (int)$_POST['priority'];
                
                // Update DNS record
                $db->query(
                    "UPDATE dns_records SET name = ?, type = ?, value = ?, ttl = ?, priority = ?, updated_at = NOW() WHERE id = ?",
                    [$name, $type, $value, $ttl, $priority, $recordId]
                );
                
                $message = 'DNS record updated successfully';
                break;
                
            case 'delete_record':
                $recordId = (int)$_POST['record_id'];
                $db->query("DELETE FROM dns_records WHERE id = ?", [$recordId]);
                $message = 'DNS record deleted successfully';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get DNS zones with domain information
$zones = $db->fetchAll(
    "SELECT dz.*, d.domain_name, u.username FROM dns_zones dz 
     JOIN domains d ON dz.domain_id = d.id 
     JOIN users u ON d.user_id = u.id 
     ORDER BY d.domain_name"
);

// Get all domains for dropdown
$domains = $db->fetchAll(
    "SELECT d.*, u.username FROM domains d 
     JOIN users u ON d.user_id = u.id 
     ORDER BY d.domain_name"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Management - Admini Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-server me-2"></i>Admini</h4>
            <small>Admin Panel</small>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>Users
            </a>
            <a href="resellers.php" class="nav-link">
                <i class="fas fa-user-tie"></i>Resellers
            </a>
            <a href="packages.php" class="nav-link">
                <i class="fas fa-box"></i>Packages
            </a>
            <a href="domains.php" class="nav-link">
                <i class="fas fa-globe"></i>Domains
            </a>
            <a href="dns.php" class="nav-link active">
                <i class="fas fa-network-wired"></i>DNS Management
            </a>
            <a href="email.php" class="nav-link">
                <i class="fas fa-envelope"></i>Email
            </a>
            <a href="databases.php" class="nav-link">
                <i class="fas fa-database"></i>Databases
            </a>
            <a href="backups.php" class="nav-link">
                <i class="fas fa-download"></i>Backups
            </a>
            <a href="ssl.php" class="nav-link">
                <i class="fas fa-lock"></i>SSL Certificates
            </a>
            <a href="statistics.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Statistics
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>Settings
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <h1>DNS Management</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createZoneModal">
                        <i class="fas fa-plus me-2"></i>Create Zone
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRecordModal">
                        <i class="fas fa-plus me-2"></i>Add Record
                    </button>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- DNS Zones -->
            <?php foreach ($zones as $zone): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-network-wired me-2"></i><?= htmlspecialchars($zone['zone_name']) ?></h5>
                            <div>
                                <small class="text-muted">
                                    Owner: <?= htmlspecialchars($zone['username']) ?> | 
                                    Serial: <?= $zone['serial_number'] ?> | 
                                    TTL: <?= $zone['ttl'] ?>s
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get DNS records for this zone
                        $records = $db->fetchAll(
                            "SELECT * FROM dns_records WHERE zone_id = ? ORDER BY type, name",
                            [$zone['id']]
                        );
                        ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>TTL</th>
                                        <th>Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($records)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No DNS records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($record['name']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= getDnsRecordTypeColor($record['type']) ?>">
                                                        <?= $record['type'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($record['value']) ?></td>
                                                <td><?= $record['ttl'] ?>s</td>
                                                <td><?= $record['priority'] ?: '-' ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editRecord(<?= $record['id'] ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="delete_record">
                                                            <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this DNS record?">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-sm btn-success" onclick="addRecordToZone(<?= $zone['id'] ?>)">
                                <i class="fas fa-plus me-2"></i>Add Record to Zone
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($zones)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-network-wired text-muted" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">No DNS Zones</h4>
                        <p class="text-muted">Create your first DNS zone to get started.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createZoneModal">
                            <i class="fas fa-plus me-2"></i>Create DNS Zone
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Zone Modal -->
    <div class="modal fade" id="createZoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create DNS Zone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" data-validate="true">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_zone">
                        
                        <div class="mb-3">
                            <label for="domain_id" class="form-label">Domain</label>
                            <select class="form-select" id="domain_id" name="domain_id" required>
                                <option value="">Select Domain</option>
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain_name']) ?> (<?= htmlspecialchars($domain['username']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="zone_name" class="form-label">Zone Name</label>
                            <input type="text" class="form-control" id="zone_name" name="zone_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ttl" class="form-label">Default TTL (seconds)</label>
                            <input type="number" class="form-control" id="ttl" name="ttl" value="3600" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Zone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Record Modal -->
    <div class="modal fade" id="createRecordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add DNS Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" data-validate="true">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_record">
                        
                        <div class="mb-3">
                            <label for="zone_id" class="form-label">DNS Zone</label>
                            <select class="form-select" id="zone_id" name="zone_id" required>
                                <option value="">Select Zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['zone_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Record Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="type" class="form-label">Record Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="A">A</option>
                                <option value="AAAA">AAAA</option>
                                <option value="CNAME">CNAME</option>
                                <option value="MX">MX</option>
                                <option value="TXT">TXT</option>
                                <option value="NS">NS</option>
                                <option value="PTR">PTR</option>
                                <option value="SRV">SRV</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="value" class="form-label">Value</label>
                            <input type="text" class="form-control" id="value" name="value" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ttl_record" class="form-label">TTL (seconds)</label>
                                    <input type="number" class="form-control" id="ttl_record" name="ttl" value="3600" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority (MX/SRV only)</label>
                                    <input type="number" class="form-control" id="priority" name="priority" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function editRecord(recordId) {
            // Implementation for edit record modal
            console.log('Edit record:', recordId);
        }
        
        function addRecordToZone(zoneId) {
            const zoneSelect = document.getElementById('zone_id');
            zoneSelect.value = zoneId;
            const modal = new bootstrap.Modal(document.getElementById('createRecordModal'));
            modal.show();
        }
    </script>
</body>
</html>

<?php
function getDnsRecordTypeColor($type) {
    switch ($type) {
        case 'A': return 'primary';
        case 'AAAA': return 'info';
        case 'CNAME': return 'success';
        case 'MX': return 'warning';
        case 'TXT': return 'secondary';
        case 'NS': return 'dark';
        default: return 'light';
    }
}
?>