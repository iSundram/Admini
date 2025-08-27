<?php
/**
 * Advanced Workflow Automation Engine
 * Visual workflow builder with complex business logic and automation
 */

class WorkflowEngine {
    private $database;
    private $cache;
    private $eventStream;
    private $executionEngine;
    private $nodeRegistry;
    private $conditionEvaluator;
    
    public function __construct($database, $cache, $eventStream) {
        $this->database = $database;
        $this->cache = $cache;
        $this->eventStream = $eventStream;
        $this->executionEngine = new WorkflowExecutionEngine($database, $cache);
        $this->nodeRegistry = new NodeRegistry();
        $this->conditionEvaluator = new ConditionEvaluator();
        $this->initializeNodes();
    }
    
    /**
     * Create new workflow
     */
    public function createWorkflow($definition) {
        $workflowId = uniqid('wf_');
        
        $workflow = [
            'id' => $workflowId,
            'name' => $definition['name'],
            'description' => $definition['description'] ?? '',
            'trigger' => json_encode($definition['trigger']),
            'nodes' => json_encode($definition['nodes']),
            'connections' => json_encode($definition['connections']),
            'variables' => json_encode($definition['variables'] ?? []),
            'settings' => json_encode($definition['settings'] ?? []),
            'status' => 'active',
            'version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Validate workflow
        $validation = $this->validateWorkflow($definition);
        if (!$validation['valid']) {
            throw new Exception('Invalid workflow: ' . implode(', ', $validation['errors']));
        }
        
        // Store workflow
        $this->storeWorkflow($workflow);
        
        // Register triggers
        $this->registerTriggers($workflowId, $definition['trigger']);
        
        return $workflowId;
    }
    
    /**
     * Execute workflow
     */
    public function executeWorkflow($workflowId, $triggerData = [], $context = []) {
        $workflow = $this->getWorkflow($workflowId);
        if (!$workflow) {
            throw new Exception("Workflow not found: {$workflowId}");
        }
        
        $executionId = uniqid('exec_');
        
        $execution = [
            'id' => $executionId,
            'workflow_id' => $workflowId,
            'trigger_data' => json_encode($triggerData),
            'context' => json_encode($context),
            'status' => 'running',
            'started_at' => time(),
            'current_node' => null,
            'variables' => json_encode($workflow['variables'] ?? []),
            'execution_log' => json_encode([])
        ];
        
        // Store execution
        $this->storeExecution($execution);
        
        try {
            // Initialize execution context
            $executionContext = new WorkflowExecutionContext(
                $executionId,
                $workflowId,
                $triggerData,
                $context,
                json_decode($workflow['variables'], true) ?? []
            );
            
            // Start execution
            $result = $this->executionEngine->execute($workflow, $executionContext);
            
            // Update execution status
            $this->updateExecutionStatus($executionId, 'completed', $result);
            
            return $result;
            
        } catch (Exception $e) {
            // Update execution status
            $this->updateExecutionStatus($executionId, 'failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Create visual workflow from template
     */
    public function createFromTemplate($templateId, $customizations = []) {
        $template = $this->getWorkflowTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }
        
        $definition = json_decode($template['definition'], true);
        
        // Apply customizations
        if (!empty($customizations)) {
            $definition = $this->applyCustomizations($definition, $customizations);
        }
        
        // Generate unique names
        $definition['name'] = ($customizations['name'] ?? $definition['name']) . ' - ' . date('Y-m-d H:i:s');
        
        return $this->createWorkflow($definition);
    }
    
    /**
     * Schedule workflow execution
     */
    public function scheduleWorkflow($workflowId, $schedule, $data = []) {
        $scheduleId = uniqid('sched_');
        
        $scheduledWorkflow = [
            'id' => $scheduleId,
            'workflow_id' => $workflowId,
            'schedule' => $schedule, // cron format
            'data' => json_encode($data),
            'status' => 'active',
            'last_run' => null,
            'next_run' => $this->calculateNextRun($schedule),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->storeScheduledWorkflow($scheduledWorkflow);
        
        return $scheduleId;
    }
    
    /**
     * Process scheduled workflows
     */
    public function processScheduledWorkflows() {
        $query = "SELECT * FROM scheduled_workflows 
                  WHERE status = 'active' AND next_run <= NOW()";
        
        $result = $this->database->query($query);
        
        while ($row = $result->fetch_assoc()) {
            try {
                // Execute workflow
                $data = json_decode($row['data'], true) ?? [];
                $this->executeWorkflow($row['workflow_id'], $data);
                
                // Update schedule
                $nextRun = $this->calculateNextRun($row['schedule']);
                $this->updateScheduledWorkflow($row['id'], $nextRun);
                
            } catch (Exception $e) {
                error_log("Scheduled workflow execution failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Create workflow from business rules
     */
    public function createFromRules($rules, $name, $description = '') {
        $nodes = [];
        $connections = [];
        $nodeId = 1;
        
        // Start node
        $startNodeId = "node_{$nodeId}";
        $nodes[$startNodeId] = [
            'type' => 'start',
            'label' => 'Start',
            'config' => []
        ];
        $nodeId++;
        
        $previousNodeId = $startNodeId;
        
        // Process rules
        foreach ($rules as $rule) {
            $ruleNodeId = "node_{$nodeId}";
            
            // Condition node
            $nodes[$ruleNodeId] = [
                'type' => 'condition',
                'label' => $rule['name'],
                'config' => [
                    'condition' => $rule['condition'],
                    'description' => $rule['description'] ?? ''
                ]
            ];
            
            // Connect previous node to condition
            $connections[] = [
                'from' => $previousNodeId,
                'to' => $ruleNodeId,
                'type' => 'default'
            ];
            
            $nodeId++;
            
            // Action nodes for true branch
            if (isset($rule['actions'])) {
                $actionNodeId = "node_{$nodeId}";
                
                $nodes[$actionNodeId] = [
                    'type' => 'action_group',
                    'label' => 'Execute Actions',
                    'config' => [
                        'actions' => $rule['actions']
                    ]
                ];
                
                // Connect condition to actions (true branch)
                $connections[] = [
                    'from' => $ruleNodeId,
                    'to' => $actionNodeId,
                    'type' => 'true'
                ];
                
                $nodeId++;
                $previousNodeId = $actionNodeId;
            }
        }
        
        // End node
        $endNodeId = "node_{$nodeId}";
        $nodes[$endNodeId] = [
            'type' => 'end',
            'label' => 'End',
            'config' => []
        ];
        
        $connections[] = [
            'from' => $previousNodeId,
            'to' => $endNodeId,
            'type' => 'default'
        ];
        
        $definition = [
            'name' => $name,
            'description' => $description,
            'trigger' => [
                'type' => 'manual',
                'config' => []
            ],
            'nodes' => $nodes,
            'connections' => $connections,
            'variables' => []
        ];
        
        return $this->createWorkflow($definition);
    }
    
    private function initializeNodes() {
        // Register all available node types
        $this->nodeRegistry->register('start', new StartNode());
        $this->nodeRegistry->register('end', new EndNode());
        $this->nodeRegistry->register('condition', new ConditionNode($this->conditionEvaluator));
        $this->nodeRegistry->register('action', new ActionNode($this->database, $this->cache));
        $this->nodeRegistry->register('action_group', new ActionGroupNode($this->database, $this->cache));
        $this->nodeRegistry->register('delay', new DelayNode());
        $this->nodeRegistry->register('loop', new LoopNode());
        $this->nodeRegistry->register('parallel', new ParallelNode());
        $this->nodeRegistry->register('merge', new MergeNode());
        $this->nodeRegistry->register('webhook', new WebhookNode());
        $this->nodeRegistry->register('email', new EmailNode());
        $this->nodeRegistry->register('notification', new NotificationNode());
        $this->nodeRegistry->register('database', new DatabaseNode($this->database));
        $this->nodeRegistry->register('api_call', new APICallNode());
        $this->nodeRegistry->register('script', new ScriptNode());
        $this->nodeRegistry->register('approval', new ApprovalNode($this->database));
        $this->nodeRegistry->register('user_task', new UserTaskNode($this->database));
        $this->nodeRegistry->register('data_transform', new DataTransformNode());
        $this->nodeRegistry->register('file_operation', new FileOperationNode());
    }
    
    private function validateWorkflow($definition) {
        $errors = [];
        
        // Basic validation
        if (empty($definition['name'])) {
            $errors[] = 'Workflow name is required';
        }
        
        if (empty($definition['nodes']) || !is_array($definition['nodes'])) {
            $errors[] = 'Workflow must have nodes';
        }
        
        if (empty($definition['trigger'])) {
            $errors[] = 'Workflow must have a trigger';
        }
        
        // Validate nodes
        $nodeValidation = $this->validateNodes($definition['nodes']);
        if (!$nodeValidation['valid']) {
            $errors = array_merge($errors, $nodeValidation['errors']);
        }
        
        // Validate connections
        $connectionValidation = $this->validateConnections($definition['connections'], $definition['nodes']);
        if (!$connectionValidation['valid']) {
            $errors = array_merge($errors, $connectionValidation['errors']);
        }
        
        // Check for cycles
        if ($this->hasCycles($definition['nodes'], $definition['connections'])) {
            $errors[] = 'Workflow contains cycles';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function validateNodes($nodes) {
        $errors = [];
        $hasStart = false;
        $hasEnd = false;
        
        foreach ($nodes as $nodeId => $node) {
            if (empty($node['type'])) {
                $errors[] = "Node {$nodeId} is missing type";
                continue;
            }
            
            if ($node['type'] === 'start') {
                $hasStart = true;
            } elseif ($node['type'] === 'end') {
                $hasEnd = true;
            }
            
            // Validate node configuration
            $nodeHandler = $this->nodeRegistry->get($node['type']);
            if (!$nodeHandler) {
                $errors[] = "Unknown node type: {$node['type']}";
                continue;
            }
            
            $nodeValidation = $nodeHandler->validate($node['config'] ?? []);
            if (!$nodeValidation['valid']) {
                $errors[] = "Node {$nodeId} validation failed: " . implode(', ', $nodeValidation['errors']);
            }
        }
        
        if (!$hasStart) {
            $errors[] = 'Workflow must have a start node';
        }
        
        if (!$hasEnd) {
            $errors[] = 'Workflow must have an end node';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function validateConnections($connections, $nodes) {
        $errors = [];
        
        if (!is_array($connections)) {
            $connections = [];
        }
        
        foreach ($connections as $connection) {
            if (empty($connection['from']) || empty($connection['to'])) {
                $errors[] = 'Connection is missing from or to node';
                continue;
            }
            
            if (!isset($nodes[$connection['from']])) {
                $errors[] = "Connection references unknown from node: {$connection['from']}";
            }
            
            if (!isset($nodes[$connection['to']])) {
                $errors[] = "Connection references unknown to node: {$connection['to']}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function hasCycles($nodes, $connections) {
        // Implement cycle detection using DFS
        $visited = [];
        $recursionStack = [];
        
        foreach ($nodes as $nodeId => $node) {
            if (!isset($visited[$nodeId])) {
                if ($this->dfsHasCycle($nodeId, $connections, $visited, $recursionStack)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function dfsHasCycle($nodeId, $connections, &$visited, &$recursionStack) {
        $visited[$nodeId] = true;
        $recursionStack[$nodeId] = true;
        
        // Get adjacent nodes
        $adjacentNodes = [];
        foreach ($connections as $connection) {
            if ($connection['from'] === $nodeId) {
                $adjacentNodes[] = $connection['to'];
            }
        }
        
        foreach ($adjacentNodes as $adjacentNode) {
            if (!isset($visited[$adjacentNode])) {
                if ($this->dfsHasCycle($adjacentNode, $connections, $visited, $recursionStack)) {
                    return true;
                }
            } elseif (isset($recursionStack[$adjacentNode]) && $recursionStack[$adjacentNode]) {
                return true;
            }
        }
        
        $recursionStack[$nodeId] = false;
        return false;
    }
    
    private function registerTriggers($workflowId, $trigger) {
        switch ($trigger['type']) {
            case 'webhook':
                $this->registerWebhookTrigger($workflowId, $trigger);
                break;
            case 'schedule':
                $this->registerScheduleTrigger($workflowId, $trigger);
                break;
            case 'event':
                $this->registerEventTrigger($workflowId, $trigger);
                break;
            case 'database':
                $this->registerDatabaseTrigger($workflowId, $trigger);
                break;
        }
    }
    
    private function registerWebhookTrigger($workflowId, $trigger) {
        $webhookUrl = "/webhooks/workflow/{$workflowId}/" . uniqid();
        
        $query = "INSERT INTO workflow_triggers (workflow_id, type, config, webhook_url, status) 
                  VALUES (?, 'webhook', ?, ?, 'active')";
        
        $stmt = $this->database->prepare($query);
        $config = json_encode($trigger['config']);
        $stmt->bind_param('sss', $workflowId, $config, $webhookUrl);
        $stmt->execute();
    }
    
    private function registerEventTrigger($workflowId, $trigger) {
        $eventType = $trigger['config']['event_type'];
        
        // Subscribe to event stream
        $this->eventStream->subscribe($eventType, 'workflow_group', $workflowId, function($event) use ($workflowId) {
            $this->executeWorkflow($workflowId, $event['data']);
        });
    }
    
    private function calculateNextRun($schedule) {
        // Parse cron format and calculate next run time
        // This is a simplified implementation
        $parts = explode(' ', $schedule);
        
        if (count($parts) !== 5) {
            throw new Exception('Invalid cron format');
        }
        
        // For simplicity, handle basic cases
        if ($schedule === '0 * * * *') { // Every hour
            return date('Y-m-d H:i:s', strtotime('+1 hour'));
        } elseif ($schedule === '0 0 * * *') { // Daily
            return date('Y-m-d H:i:s', strtotime('+1 day'));
        } else {
            // Default to next hour
            return date('Y-m-d H:i:s', strtotime('+1 hour'));
        }
    }
    
    // Storage methods
    
    private function storeWorkflow($workflow) {
        $query = "INSERT INTO workflows (id, name, description, trigger_config, nodes, connections, variables, settings, status, version, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ssssssssssss',
            $workflow['id'],
            $workflow['name'],
            $workflow['description'],
            $workflow['trigger'],
            $workflow['nodes'],
            $workflow['connections'],
            $workflow['variables'],
            $workflow['settings'],
            $workflow['status'],
            $workflow['version'],
            $workflow['created_at'],
            $workflow['updated_at']
        );
        
        $stmt->execute();
    }
    
    private function getWorkflow($workflowId) {
        $query = "SELECT * FROM workflows WHERE id = ? AND status = 'active'";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('s', $workflowId);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $result['trigger'] = json_decode($result['trigger_config'], true);
            $result['nodes'] = json_decode($result['nodes'], true);
            $result['connections'] = json_decode($result['connections'], true);
            $result['variables'] = json_decode($result['variables'], true);
            $result['settings'] = json_decode($result['settings'], true);
        }
        
        return $result;
    }
    
    private function storeExecution($execution) {
        $query = "INSERT INTO workflow_executions (id, workflow_id, trigger_data, context, status, started_at, current_node, variables, execution_log) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sssssssss',
            $execution['id'],
            $execution['workflow_id'],
            $execution['trigger_data'],
            $execution['context'],
            $execution['status'],
            date('Y-m-d H:i:s', $execution['started_at']),
            $execution['current_node'],
            $execution['variables'],
            $execution['execution_log']
        );
        
        $stmt->execute();
    }
    
    private function updateExecutionStatus($executionId, $status, $result = null) {
        $query = "UPDATE workflow_executions SET status = ?, completed_at = NOW(), result = ? WHERE id = ?";
        $stmt = $this->database->prepare($query);
        $resultJson = $result ? json_encode($result) : null;
        $stmt->bind_param('sss', $status, $resultJson, $executionId);
        $stmt->execute();
    }
    
    private function storeScheduledWorkflow($scheduledWorkflow) {
        $query = "INSERT INTO scheduled_workflows (id, workflow_id, schedule_pattern, data, status, last_run, next_run, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ssssssss',
            $scheduledWorkflow['id'],
            $scheduledWorkflow['workflow_id'],
            $scheduledWorkflow['schedule'],
            $scheduledWorkflow['data'],
            $scheduledWorkflow['status'],
            $scheduledWorkflow['last_run'],
            $scheduledWorkflow['next_run'],
            $scheduledWorkflow['created_at']
        );
        
        $stmt->execute();
    }
    
    private function updateScheduledWorkflow($scheduleId, $nextRun) {
        $query = "UPDATE scheduled_workflows SET last_run = NOW(), next_run = ? WHERE id = ?";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ss', $nextRun, $scheduleId);
        $stmt->execute();
    }
    
    private function getWorkflowTemplate($templateId) {
        $query = "SELECT * FROM workflow_templates WHERE id = ? AND status = 'active'";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('s', $templateId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    private function applyCustomizations($definition, $customizations) {
        // Apply field mappings
        if (isset($customizations['field_mappings'])) {
            $definition = $this->applyFieldMappings($definition, $customizations['field_mappings']);
        }
        
        // Apply variable values
        if (isset($customizations['variables'])) {
            $definition['variables'] = array_merge($definition['variables'] ?? [], $customizations['variables']);
        }
        
        // Apply node configurations
        if (isset($customizations['node_configs'])) {
            foreach ($customizations['node_configs'] as $nodeId => $config) {
                if (isset($definition['nodes'][$nodeId])) {
                    $definition['nodes'][$nodeId]['config'] = array_merge(
                        $definition['nodes'][$nodeId]['config'] ?? [],
                        $config
                    );
                }
            }
        }
        
        return $definition;
    }
    
    private function applyFieldMappings($definition, $mappings) {
        // Apply field mappings to node configurations
        foreach ($definition['nodes'] as $nodeId => &$node) {
            if (isset($node['config'])) {
                $node['config'] = $this->replaceFieldMappings($node['config'], $mappings);
            }
        }
        
        return $definition;
    }
    
    private function replaceFieldMappings($config, $mappings) {
        if (is_array($config)) {
            foreach ($config as $key => &$value) {
                if (is_array($value)) {
                    $value = $this->replaceFieldMappings($value, $mappings);
                } elseif (is_string($value)) {
                    foreach ($mappings as $placeholder => $replacement) {
                        $value = str_replace("{{{$placeholder}}}", $replacement, $value);
                    }
                }
            }
        }
        
        return $config;
    }
}

/**
 * Workflow Execution Context
 */
class WorkflowExecutionContext {
    private $executionId;
    private $workflowId;
    private $triggerData;
    private $context;
    private $variables;
    private $currentNode;
    private $executionLog;
    
    public function __construct($executionId, $workflowId, $triggerData, $context, $variables) {
        $this->executionId = $executionId;
        $this->workflowId = $workflowId;
        $this->triggerData = $triggerData;
        $this->context = $context;
        $this->variables = $variables;
        $this->executionLog = [];
    }
    
    public function getExecutionId() { return $this->executionId; }
    public function getWorkflowId() { return $this->workflowId; }
    public function getTriggerData() { return $this->triggerData; }
    public function getContext() { return $this->context; }
    public function getVariables() { return $this->variables; }
    public function getCurrentNode() { return $this->currentNode; }
    public function getExecutionLog() { return $this->executionLog; }
    
    public function setCurrentNode($nodeId) {
        $this->currentNode = $nodeId;
    }
    
    public function setVariable($name, $value) {
        $this->variables[$name] = $value;
    }
    
    public function getVariable($name, $default = null) {
        return $this->variables[$name] ?? $default;
    }
    
    public function addLogEntry($message, $level = 'info', $data = null) {
        $this->executionLog[] = [
            'timestamp' => time(),
            'level' => $level,
            'message' => $message,
            'data' => $data,
            'node' => $this->currentNode
        ];
    }
}

/**
 * Workflow Execution Engine
 */
class WorkflowExecutionEngine {
    private $database;
    private $cache;
    private $nodeRegistry;
    
    public function __construct($database, $cache) {
        $this->database = $database;
        $this->cache = $cache;
        $this->nodeRegistry = new NodeRegistry();
    }
    
    public function execute($workflow, $context) {
        $nodes = $workflow['nodes'];
        $connections = $workflow['connections'];
        
        // Find start node
        $startNode = null;
        foreach ($nodes as $nodeId => $node) {
            if ($node['type'] === 'start') {
                $startNode = $nodeId;
                break;
            }
        }
        
        if (!$startNode) {
            throw new Exception('No start node found in workflow');
        }
        
        // Execute workflow
        return $this->executeNode($startNode, $nodes, $connections, $context);
    }
    
    private function executeNode($nodeId, $nodes, $connections, $context) {
        $context->setCurrentNode($nodeId);
        $context->addLogEntry("Executing node: {$nodeId}");
        
        $node = $nodes[$nodeId];
        $nodeHandler = $this->nodeRegistry->get($node['type']);
        
        if (!$nodeHandler) {
            throw new Exception("Unknown node type: {$node['type']}");
        }
        
        // Execute node
        $result = $nodeHandler->execute($node['config'] ?? [], $context);
        
        // Handle result
        if ($result['status'] === 'completed') {
            // Find next nodes
            $nextNodes = $this->getNextNodes($nodeId, $connections, $result['output'] ?? null);
            
            // If no next nodes, workflow is complete
            if (empty($nextNodes)) {
                return ['status' => 'completed', 'result' => $result];
            }
            
            // Execute next nodes
            foreach ($nextNodes as $nextNodeId) {
                $nextResult = $this->executeNode($nextNodeId, $nodes, $connections, $context);
                
                // If any node fails, stop execution
                if ($nextResult['status'] === 'failed') {
                    return $nextResult;
                }
            }
            
            return ['status' => 'completed', 'result' => $result];
            
        } else {
            return $result;
        }
    }
    
    private function getNextNodes($currentNodeId, $connections, $output) {
        $nextNodes = [];
        
        foreach ($connections as $connection) {
            if ($connection['from'] === $currentNodeId) {
                // Check connection conditions
                if ($this->shouldFollowConnection($connection, $output)) {
                    $nextNodes[] = $connection['to'];
                }
            }
        }
        
        return $nextNodes;
    }
    
    private function shouldFollowConnection($connection, $output) {
        $type = $connection['type'] ?? 'default';
        
        switch ($type) {
            case 'true':
                return $output === true || $output === 'true';
            case 'false':
                return $output === false || $output === 'false';
            case 'error':
                return isset($output['error']);
            case 'success':
                return !isset($output['error']);
            case 'default':
            default:
                return true;
        }
    }
}

/**
 * Node Registry
 */
class NodeRegistry {
    private $nodes = [];
    
    public function register($type, $handler) {
        $this->nodes[$type] = $handler;
    }
    
    public function get($type) {
        return $this->nodes[$type] ?? null;
    }
    
    public function getAll() {
        return $this->nodes;
    }
}

/**
 * Base Node Class
 */
abstract class BaseNode {
    abstract public function execute($config, $context);
    abstract public function validate($config);
    
    protected function evaluateExpression($expression, $context) {
        // Simple expression evaluator
        // In production, use a proper expression parser
        $variables = $context->getVariables();
        $triggerData = $context->getTriggerData();
        
        // Replace variables
        foreach ($variables as $name => $value) {
            $expression = str_replace("{{\${$name}}}", $value, $expression);
        }
        
        // Replace trigger data
        foreach ($triggerData as $name => $value) {
            $expression = str_replace("{{trigger.{$name}}}", $value, $expression);
        }
        
        return $expression;
    }
}

/**
 * Start Node
 */
class StartNode extends BaseNode {
    public function execute($config, $context) {
        $context->addLogEntry('Workflow started');
        return ['status' => 'completed', 'output' => true];
    }
    
    public function validate($config) {
        return ['valid' => true, 'errors' => []];
    }
}

/**
 * End Node
 */
class EndNode extends BaseNode {
    public function execute($config, $context) {
        $context->addLogEntry('Workflow completed');
        return ['status' => 'completed', 'output' => true];
    }
    
    public function validate($config) {
        return ['valid' => true, 'errors' => []];
    }
}

/**
 * Condition Node
 */
class ConditionNode extends BaseNode {
    private $evaluator;
    
    public function __construct($evaluator) {
        $this->evaluator = $evaluator;
    }
    
    public function execute($config, $context) {
        $condition = $config['condition'];
        $result = $this->evaluator->evaluate($condition, $context);
        
        $context->addLogEntry("Condition evaluated to: " . ($result ? 'true' : 'false'));
        
        return ['status' => 'completed', 'output' => $result];
    }
    
    public function validate($config) {
        $errors = [];
        
        if (empty($config['condition'])) {
            $errors[] = 'Condition is required';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
}

/**
 * Action Node
 */
class ActionNode extends BaseNode {
    private $database;
    private $cache;
    
    public function __construct($database, $cache) {
        $this->database = $database;
        $this->cache = $cache;
    }
    
    public function execute($config, $context) {
        $actionType = $config['action_type'];
        
        switch ($actionType) {
            case 'send_email':
                return $this->sendEmail($config, $context);
            case 'create_user':
                return $this->createUser($config, $context);
            case 'update_record':
                return $this->updateRecord($config, $context);
            case 'call_api':
                return $this->callAPI($config, $context);
            default:
                throw new Exception("Unknown action type: {$actionType}");
        }
    }
    
    public function validate($config) {
        $errors = [];
        
        if (empty($config['action_type'])) {
            $errors[] = 'Action type is required';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    private function sendEmail($config, $context) {
        $to = $this->evaluateExpression($config['to'], $context);
        $subject = $this->evaluateExpression($config['subject'], $context);
        $body = $this->evaluateExpression($config['body'], $context);
        
        $success = mail($to, $subject, $body);
        
        return [
            'status' => $success ? 'completed' : 'failed',
            'output' => $success,
            'data' => ['to' => $to, 'subject' => $subject]
        ];
    }
    
    private function createUser($config, $context) {
        $userData = [];
        foreach ($config['user_data'] as $field => $value) {
            $userData[$field] = $this->evaluateExpression($value, $context);
        }
        
        $query = "INSERT INTO users (name, email, role) VALUES (?, ?, ?)";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sss', $userData['name'], $userData['email'], $userData['role']);
        
        $success = $stmt->execute();
        $userId = $success ? $this->database->insert_id : null;
        
        return [
            'status' => $success ? 'completed' : 'failed',
            'output' => $success,
            'data' => ['user_id' => $userId, 'user_data' => $userData]
        ];
    }
    
    private function updateRecord($config, $context) {
        $table = $config['table'];
        $recordId = $this->evaluateExpression($config['record_id'], $context);
        
        $updates = [];
        $values = [];
        $types = '';
        
        foreach ($config['updates'] as $field => $value) {
            $updates[] = "{$field} = ?";
            $values[] = $this->evaluateExpression($value, $context);
            $types .= 's';
        }
        
        $values[] = $recordId;
        $types .= 's';
        
        $query = "UPDATE {$table} SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param($types, ...$values);
        
        $success = $stmt->execute();
        
        return [
            'status' => $success ? 'completed' : 'failed',
            'output' => $success,
            'data' => ['table' => $table, 'record_id' => $recordId]
        ];
    }
    
    private function callAPI($config, $context) {
        $url = $this->evaluateExpression($config['url'], $context);
        $method = $config['method'] ?? 'GET';
        $headers = $config['headers'] ?? [];
        $data = $config['data'] ?? [];
        
        // Evaluate data
        foreach ($data as $key => $value) {
            $data[$key] = $this->evaluateExpression($value, $context);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if (!empty($data) && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $success = !$error && $httpCode >= 200 && $httpCode < 300;
        
        return [
            'status' => $success ? 'completed' : 'failed',
            'output' => $success,
            'data' => [
                'response' => $response,
                'http_code' => $httpCode,
                'error' => $error
            ]
        ];
    }
}

/**
 * Action Group Node - Execute multiple actions
 */
class ActionGroupNode extends BaseNode {
    private $database;
    private $cache;
    
    public function __construct($database, $cache) {
        $this->database = $database;
        $this->cache = $cache;
    }
    
    public function execute($config, $context) {
        $actions = $config['actions'] ?? [];
        $results = [];
        
        foreach ($actions as $action) {
            $actionNode = new ActionNode($this->database, $this->cache);
            $result = $actionNode->execute($action, $context);
            
            $results[] = $result;
            
            // If any action fails and fail_on_error is true, stop execution
            if ($result['status'] === 'failed' && ($config['fail_on_error'] ?? true)) {
                return [
                    'status' => 'failed',
                    'output' => false,
                    'data' => ['results' => $results]
                ];
            }
        }
        
        return [
            'status' => 'completed',
            'output' => true,
            'data' => ['results' => $results]
        ];
    }
    
    public function validate($config) {
        $errors = [];
        
        if (empty($config['actions']) || !is_array($config['actions'])) {
            $errors[] = 'Actions array is required';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
}

/**
 * Delay Node
 */
class DelayNode extends BaseNode {
    public function execute($config, $context) {
        $delay = $config['delay'] ?? 1; // seconds
        
        $context->addLogEntry("Delaying execution for {$delay} seconds");
        sleep($delay);
        
        return ['status' => 'completed', 'output' => true];
    }
    
    public function validate($config) {
        return ['valid' => true, 'errors' => []];
    }
}

/**
 * Condition Evaluator
 */
class ConditionEvaluator {
    public function evaluate($condition, $context) {
        // Simple condition evaluator
        // In production, use a proper expression parser like Symfony ExpressionLanguage
        
        $variables = $context->getVariables();
        $triggerData = $context->getTriggerData();
        
        // Replace variables in condition
        foreach ($variables as $name => $value) {
            $condition = str_replace("\${$name}", $this->formatValue($value), $condition);
        }
        
        // Replace trigger data
        foreach ($triggerData as $name => $value) {
            $condition = str_replace("trigger.{$name}", $this->formatValue($value), $condition);
        }
        
        // Simple evaluation (SECURITY WARNING: This is unsafe for production)
        // In production, use a proper expression evaluator
        try {
            $result = eval("return {$condition};");
            return (bool)$result;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function formatValue($value) {
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } else {
            return $value;
        }
    }
}

// Additional node types would be implemented here (DatabaseNode, WebhookNode, etc.)
// For brevity, I've included the core structure and a few examples